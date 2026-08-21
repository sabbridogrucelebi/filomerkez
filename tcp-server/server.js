require('dotenv').config();
const net = require('net');
const mysql = require('mysql2/promise');
const parser = require('./parser');

// MySQL Connection Pool
const pool = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'filomerkez',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// Store connected devices: { imei: { socket, company_id, vehicle_id } }
const devices = {};

const PORT = process.env.TCP_PORT || 5000;

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
                    
                    // Veritabanindan IMEI'ye ait araci bul
                    const [rows] = await pool.query(
                        'SELECT id, company_id FROM vehicles WHERE device_imei = ? LIMIT 1', 
                        [deviceImei]
                    );

                    if (rows.length > 0) {
                        devices[deviceImei] = {
                            socket: socket,
                            vehicle_id: rows[0].id,
                            company_id: rows[0].company_id
                        };
                        console.log(`[+] Araç eslestirildi: Vehicle ID: ${rows[0].id}, Company ID: ${rows[0].company_id}`);
                    } else {
                        console.log(`[!] Sistemde kayitli olmayan cihaz bağlandi: ${deviceImei}`);
                    }

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

                    if (deviceImei && devices[deviceImei]) {
                        const { vehicle_id, company_id } = devices[deviceImei];
                        
                        // MySQL'e kaydet
                        await pool.query(
                            `INSERT INTO vehicle_locations 
                            (company_id, vehicle_id, imei, latitude, longitude, speed, course, status, recorded_at, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
                            [
                                company_id,
                                vehicle_id,
                                deviceImei,
                                loc.lat,
                                loc.lng,
                                loc.speed,
                                loc.course,
                                JSON.stringify(loc.status || {}), // ACC, Batarya vb.
                                loc.datetime // Cihazin gönderdiği GPS saati
                            ]
                        );
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
