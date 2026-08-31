require('dotenv').config();
const net = require('net');
const axios = require('axios');
const parser = require('./parser');

// Store connected devices: { imei: { socket, lastAcc, lastVoltage, lastLat, lastLng, lastSpeed, lastCourse } }
const devices = {};

const PORT = process.env.TCP_PORT || 36025;
const API_URL = process.env.API_URL || 'https://mehmetcelebiturizm.com/app/api/v1/vehicle-tracking/telemetry';
const API_SECRET = process.env.API_SECRET || 'filo-telemetry-2026-secret';

const server = net.createServer((socket) => {
    console.log(`[+] Yeni Cihaz Baglandi: ${socket.remoteAddress}:${socket.remotePort}`);
    let deviceImei = null;

    socket.on('data', async (data) => {
        try {
            console.log(`[RAW DATA] HEX: ${data.toString('hex')}`);
            console.log(`[RAW DATA] ASC: ${data.toString('ascii')}`);
            
            // Hex veriyi parse et
            const parsedPackets = parser.parseConcox(data);

            for (const packet of parsedPackets) {
                if (!packet.valid) {
                    console.log(`[-] Gecersiz paket (CRC Hatasi)`);
                    continue;
                }

                // 1. LOGIN PAKETI (0x01)
                if (packet.protocolId === 0x01) {
                    deviceImei = packet.imei;
                    console.log(`[LOGIN] Cihaz IMEI: ${deviceImei}`);
                    
                    // Cihaz state'ini başlat veya güncelle
                    if (!devices[deviceImei]) {
                        devices[deviceImei] = {
                            socket: socket,
                            lastAcc: false,
                            lastVoltage: null,
                            lastLat: null,
                            lastLng: null,
                            lastSpeed: 0,
                            lastCourse: 0
                        };
                    } else {
                        devices[deviceImei].socket = socket;
                    }

                    // Cihaza Login Response gönder
                    const response = parser.createResponse(0x01, packet.serialNumber);
                    socket.write(response);
                }

                // 2. HEARTBEAT PAKETI (0x13)
                // GT06N'de ACC (Kontak) bilgisi SADECE burada bulunur!
                else if (packet.protocolId === 0x13) {
                    console.log(`[HEARTBEAT] IMEI: ${deviceImei || 'Bilinmiyor'}`);
                    
                    // Cihaza Heartbeat Response gönder (Cihazın kopmamasını saglar)
                    const response = parser.createResponse(0x13, packet.serialNumber);
                    socket.write(response);

                    // Heartbeat'ten ACC ve voltaj bilgisini al ve sakla
                    if (packet.heartbeat && deviceImei && devices[deviceImei]) {
                        const prevAcc = devices[deviceImei].lastAcc;
                        const newAcc = packet.heartbeat.acc;
                        
                        devices[deviceImei].lastAcc = newAcc;
                        devices[deviceImei].lastVoltage = packet.heartbeat.voltage;
                        devices[deviceImei].lastCharging = packet.heartbeat.charging;
                        devices[deviceImei].lastAlarm = packet.heartbeat.alarm;

                        console.log(`[HEARTBEAT] IMEI: ${deviceImei} | ACC: ${prevAcc ? 'AÇIK' : 'KAPALI'} → ${newAcc ? 'AÇIK' : 'KAPALI'} | Voltaj: ${packet.heartbeat.voltage}V | Charging: ${packet.heartbeat.charging}`);

                        // ACC durumu değiştiğinde VEYA periyodik olarak durumu API'ye bildir
                        // (Son bilinen konum ile gönder, böylece cihaz duruyor olsa bile ACC güncellensin)
                        if (devices[deviceImei].lastLat !== null) {
                            try {
                                await axios.post(API_URL, {
                                    imei: deviceImei,
                                    latitude: devices[deviceImei].lastLat,
                                    longitude: devices[deviceImei].lastLng,
                                    speed: devices[deviceImei].lastSpeed,
                                    course: devices[deviceImei].lastCourse,
                                    status: {
                                        acc: newAcc,
                                        voltage: packet.heartbeat.voltage,
                                        gsmSignal: packet.heartbeat.gsmSignal,
                                        charging: packet.heartbeat.charging,
                                        alarm: packet.heartbeat.alarm,
                                        defense: packet.heartbeat.defense
                                    },
                                    recorded_at: new Date().toISOString().replace('T', ' ').split('.')[0]
                                }, {
                                    headers: {
                                        'X-Telemetry-Secret': API_SECRET,
                                        'Content-Type': 'application/json'
                                    },
                                    timeout: 5000
                                });
                                console.log(`[+] Heartbeat ACC verisi API'ye gonderildi (ACC: ${newAcc ? 'AÇIK' : 'KAPALI'}).`);
                            } catch (apiError) {
                                console.error(`[-] Heartbeat API'ye gonderilemedi:`, apiError.message);
                            }
                        } else {
                            console.log(`[HEARTBEAT] Henuz konum verisi yok, API'ye gonderilmedi.`);
                        }
                    }
                }

                // 3. LOKASYON PAKETI (0x12) veya ALARM PAKETI (0x16)
                else if (packet.protocolId === 0x12 || packet.protocolId === 0x16) {
                    const loc = packet.location;
                    if (!loc) continue;

                    console.log(`[LOCATION] IMEI: ${deviceImei} | Enlem: ${loc.lat}, Boylam: ${loc.lng}, Hiz: ${loc.speed} km/s | Uydu: ${loc.satellites}`);

                    if (deviceImei) {
                        // Son bilinen konumu cihaz state'ine kaydet
                        if (devices[deviceImei]) {
                            devices[deviceImei].lastLat = loc.lat;
                            devices[deviceImei].lastLng = loc.lng;
                            devices[deviceImei].lastSpeed = loc.speed;
                            devices[deviceImei].lastCourse = loc.course;
                            
                            // 0x16 paketinden gelen heartbeat / alarm verisini de kaydet
                            if (packet.protocolId === 0x16 && packet.heartbeat) {
                                devices[deviceImei].lastAcc = packet.heartbeat.acc;
                                devices[deviceImei].lastCharging = packet.heartbeat.charging;
                                devices[deviceImei].lastAlarm = packet.heartbeat.alarm;
                                devices[deviceImei].lastVoltage = packet.heartbeat.voltage;
                                console.log(`[ALARM/0x16] IMEI: ${deviceImei} | Charging: ${packet.heartbeat.charging}, Alarm: ${packet.heartbeat.alarm}`);
                            }
                        }

                        // Heartbeat'ten gelen son durumlari konum verisiyle birleştir
                        const currentAcc = devices[deviceImei] ? devices[deviceImei].lastAcc : false;
                        const currentVoltage = devices[deviceImei] ? devices[deviceImei].lastVoltage : null;
                        const currentCharging = devices[deviceImei] && devices[deviceImei].lastCharging !== undefined ? devices[deviceImei].lastCharging : true;
                        const currentAlarm = devices[deviceImei] ? devices[deviceImei].lastAlarm : null;

                        const statusPayload = {
                            ...(loc.status || {}),
                            acc: currentAcc,  // Heartbeat'ten gelen gerçek ACC
                            charging: currentCharging,
                            alarm: currentAlarm
                        };
                        if (currentVoltage !== null) {
                            statusPayload.voltage = currentVoltage;
                        }

                        try {
                            // API'ye HTTP POST gonder
                            await axios.post(API_URL, {
                                imei: deviceImei,
                                latitude: loc.lat,
                                longitude: loc.lng,
                                speed: loc.speed,
                                course: loc.course,
                                status: statusPayload,
                                recorded_at: loc.datetime
                            }, {
                                headers: {
                                    'X-Telemetry-Secret': API_SECRET,
                                    'Content-Type': 'application/json'
                                },
                                timeout: 5000
                            });
                            console.log(`[+] Veri API'ye basariyla gonderildi. (ACC: ${currentAcc ? 'AÇIK ✓' : 'KAPALI ✗'})`);
                        } catch (apiError) {
                            console.error(`[-] API'ye gonderilemedi:`, apiError.message);
                        }
                    }


                    // Alarm paketi ise cihaza cevap dönmek gerekir
                    if (packet.protocolId === 0x16) {
                        const response = parser.createResponse(0x16, packet.serialNumber);
                        socket.write(response);
                    }
                }
            }
        } catch (error) {
            console.error('Veri isleme hatasi:', error);
        }
    });

    socket.on('close', () => {
        console.log(`[-] Cihaz Baglantisi Koptu: ${socket.remoteAddress}:${socket.remotePort}`);
        if (deviceImei && devices[deviceImei]) {
            // Bağlantı koptuğunda ACC'yi false yap (Cihaz kapandı veya sinyal kesildi)
            devices[deviceImei].lastAcc = false;
            devices[deviceImei].socket = null;
            // Cihazı sil (bellek yönetimi için)
            // delete devices[deviceImei]; // İsteğe bağlı: Reconnect'te son durumu korumak için silme
        }
    });

    socket.on('error', (err) => {
        console.error('Socket Hatasi:', err.message);
    });
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`GPS TCP Server ${PORT} portunda dinleniyor...`);
    console.log(`API URL: ${API_URL}`);
    console.log(`Concox GT06N Protokolü: ACC bilgisi Heartbeat (0x13) paketinden okunuyor.`);
});
