require('dotenv').config();
const net = require('net');
const axios = require('axios');
const parser = require('./parser');

// Store connected devices: { imei: { socket } }
const devices = {};

const PORT = process.env.TCP_PORT || 36025;
const API_URL = process.env.API_URL || 'https://mehmetcelebiturizm.com/api/v1/vehicle-tracking/telemetry';
const API_SECRET = process.env.API_SECRET || 'filo-telemetry-2026-secret';

const server = net.createServer((socket) => {
    console.log(`[+] Yeni Cihaz Baglandi: ${socket.remoteAddress}:${socket.remotePort}`);
    let deviceImei = null;

    socket.on('data', async (data) => {
        try {
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
                    
                    devices[deviceImei] = {
                        socket: socket
                    };

                    // Cihaza Login Response gönder
                    const response = parser.createResponse(0x01, packet.serialNumber);
                    socket.write(response);
                }

                // 2. HEARTBEAT PAKETI (0x13)
                else if (packet.protocolId === 0x13) {
                    console.log(`[HEARTBEAT] IMEI: ${deviceImei || 'Bilinmiyor'}`);
                    // Cihaza Heartbeat Response gönder (Cihazın kopmamasını saglar)
                    const response = parser.createResponse(0x13, packet.serialNumber);
                    socket.write(response);
                }

                // 3. LOKASYON PAKETI (0x12) veya ALARM PAKETI (0x16)
                else if (packet.protocolId === 0x12 || packet.protocolId === 0x16) {
                    const loc = packet.location;
                    if (!loc) continue;

                    console.log(`[LOCATION] IMEI: ${deviceImei} | Enlem: ${loc.lat}, Boylam: ${loc.lng}, Hiz: ${loc.speed} km/s`);

                    if (deviceImei) {
                        try {
                            // API'ye HTTP POST gonder
                            await axios.post(API_URL, {
                                imei: deviceImei,
                                latitude: loc.lat,
                                longitude: loc.lng,
                                speed: loc.speed,
                                course: loc.course,
                                status: loc.status || {},
                                recorded_at: loc.datetime
                            }, {
                                headers: {
                                    'X-Telemetry-Secret': API_SECRET,
                                    'Content-Type': 'application/json'
                                },
                                timeout: 5000
                            });
                            console.log(`[+] Veri API'ye basariyla gonderildi.`);
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
            delete devices[deviceImei];
        }
    });

    socket.on('error', (err) => {
        console.error('Socket Hatasi:', err.message);
    });
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`GPS TCP Server ${PORT} portunda dinleniyor...`);
});
