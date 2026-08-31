// CRC-16/X.25 (ITU) hesaplayicisi - Concox standardi
function getCrc16(buffer) {
    let crc = 0xFFFF;
    for (let i = 0; i < buffer.length; i++) {
        crc ^= buffer[i];
        for (let j = 0; j < 8; j++) {
            if ((crc & 1) !== 0) {
                crc = (crc >> 1) ^ 0x8408;
            } else {
                crc >>= 1;
            }
        }
    }
    return (~crc) & 0xFFFF;
}

function parseConcox(buffer) {
    const packets = [];
    let offset = 0;

    // Veri akisi birden fazla paket içerebilir (TCP parcalanmasi/birlestirilmesi)
    while (offset < buffer.length - 5) {
        // Start bit 0x78 0x78
        if (buffer[offset] === 0x78 && buffer[offset + 1] === 0x78) {
            const packetLength = buffer[offset + 2];
            
            if (offset + packetLength + 5 > buffer.length) {
                // Tam paket henuz gelmedi, bir sonraki event'i bekle (basit uygulamada yoksayilir)
                break;
            }

            const protocolId = buffer[offset + 3];
            const packetInfoContent = buffer.subarray(offset + 4, offset + 3 + packetLength - 4);
            const serialNumber = buffer.subarray(offset + 3 + packetLength - 4, offset + 3 + packetLength - 2);
            const crc = buffer.readUInt16BE(offset + 3 + packetLength - 2);
            
            // CRC Kontrolü (Packet Length'ten Serial Number'a kadar olan kisim)
            const crcBuffer = buffer.subarray(offset + 2, offset + 3 + packetLength - 2);
            const calculatedCrc = getCrc16(crcBuffer);

            const packet = {
                valid: crc === calculatedCrc,
                protocolId: protocolId,
                serialNumber: serialNumber.readUInt16BE(0)
            };

            if (packet.valid) {
                // 1. LOGIN PAKETİ (0x01)
                if (protocolId === 0x01) {
                    // IMEI 8 byte'lik hex olarak geliyor (örn: 01 23 45 67 89 01 23 45 -> 123456789012345)
                    let imeiHex = packetInfoContent.toString('hex');
                    // Gelen hex'in içindeki ilk sifiri atarak (veya doğrudan string olarak) IMEI'yi al
                    packet.imei = imeiHex.replace(/^0+/, ''); 
                }
                
                // 2. LOKASYON PAKETİ (0x12)
                else if (protocolId === 0x12) {
                    packet.location = parseLocation(packetInfoContent);
                }

                // 3. HEARTBEAT PAKETİ (0x13)
                // GT06N Heartbeat: Terminal Bilgi Bayt'ı + Voltaj Seviyesi + GSM Sinyal Gücü
                // Terminal Bilgi Bayt'ı (Byte 0):
                //   Bit 0: Yağ/Elektrik Bağlı (1) / Bağlı Değil (0)
                //   Bit 1: GPS İzleme Açık (1) / Kapalı (0)
                //   Bit 2-4: Alarm türleri (000=Normal, 001=SOS, 010=Düşük Pil, 011=Güç Kesildi, 100=Titreşim)
                //   Bit 5: Şarj Durumu (1=Şarjda, 0=Şarj Değil)
                //   Bit 6: ACC/Kontak Durumu (1=AÇIK, 0=KAPALI) ← BU BİZİM İHTİYACIMIZ!
                //   Bit 7: Savunma/Tahkimat durumu
                else if (protocolId === 0x13) {
                    if (packetInfoContent.length >= 1) {
                        const terminalInfo = packetInfoContent[0];
                        const acc = (terminalInfo & 0x40) >> 6;           // Bit 6: ACC (Kontak)
                        const charging = (terminalInfo & 0x20) >> 5;      // Bit 5: Şarj
                        const gpsTracking = (terminalInfo & 0x02) >> 1;   // Bit 1: GPS İzleme
                        const oilElec = terminalInfo & 0x01;              // Bit 0: Yağ/Elektrik
                        const defense = (terminalInfo & 0x80) >> 7;       // Bit 7: Savunma

                        // Alarm türü (Bit 2-4)
                        const alarmBits = (terminalInfo >> 2) & 0x07;
                        const alarmTypes = ['Normal', 'SOS', 'Düşük Pil', 'Güç Kesildi', 'Titreşim', 'Bilinmeyen', 'Bilinmeyen', 'Bilinmeyen'];
                        const alarm = alarmTypes[alarmBits] || 'Bilinmeyen';

                        // Voltaj seviyesi (Byte 1) ve GSM sinyal gücü (Byte 2)
                        const voltageLevel = packetInfoContent.length >= 2 ? packetInfoContent[1] : 0;
                        const gsmSignal = packetInfoContent.length >= 3 ? packetInfoContent[2] : 0;

                        // Voltaj seviyesini gerçek voltaja çevir (GT06N standart aralıkları)
                        // 0=Kapalı, 1=Çok Düşük, 2=Düşük, 3=Orta, 4=Yüksek, 5=Çok Yüksek, 6=Tam
                        const voltageMap = { 0: 0, 1: 10.8, 2: 11.4, 3: 11.8, 4: 12.2, 5: 12.6, 6: 13.2 };
                        const voltage = voltageMap[voltageLevel] || 12.0;

                        packet.heartbeat = {
                            acc: acc === 1,
                            charging: charging === 1,
                            gpsTracking: gpsTracking === 1,
                            oilElecConnected: oilElec === 1,
                            defense: defense === 1,
                            alarm: alarm,
                            voltageLevel: voltageLevel,
                            voltage: voltage,
                            gsmSignal: gsmSignal,
                            terminalInfoRaw: '0x' + terminalInfo.toString(16).padStart(2, '0')
                        };

                        console.log(`[HEARTBEAT] ACC: ${packet.heartbeat.acc ? 'AÇIK ✓' : 'KAPALI ✗'} | Voltaj: ${voltage}V | GSM: ${gsmSignal} | Alarm: ${alarm} | Raw: ${packet.heartbeat.terminalInfoRaw}`);
                    }
                }

                // 4. ALARM PAKETİ (0x16) - Konum + Alarm bilgisi içerir
                else if (protocolId === 0x16) {
                    packet.location = parseLocation(packetInfoContent);
                    
                    // Alarm Paketinin son 5 byte'ı (LBS'den sonraki kısım) Terminal bilgisidir
                    if (packetInfoContent.length >= 23) {
                        const terminalInfo = packetInfoContent[packetInfoContent.length - 5];
                        const voltageLevel = packetInfoContent[packetInfoContent.length - 4];
                        const gsmSignal = packetInfoContent[packetInfoContent.length - 3];
                        const alarmLang = packetInfoContent.readUInt16BE(packetInfoContent.length - 2);

                        const acc = (terminalInfo & 0x02) >> 1;
                        const charging = (terminalInfo & 0x20) >> 5;
                        const alarmType = (alarmLang & 0x38) >> 3;

                        let alarm = null;
                        if (alarmType === 1) alarm = "SOS";
                        else if (alarmType === 2) alarm = "Güç Kesildi";
                        else if (alarmType === 3) alarm = "Titreşim/Sarsıntı";
                        else if (alarmType === 4) alarm = "Düşük Pil";
                        else if (alarmType === 6) alarm = "Hız İhlali";
                        
                        let voltage = (voltageLevel === 6) ? 12 : ((voltageLevel === 5) ? 4.2 : ((voltageLevel === 4) ? 4.0 : 3.8));

                        packet.heartbeat = {
                            acc: acc === 1,
                            charging: charging === 1,
                            alarm: alarm,
                            voltage: voltage
                        };
                    }
                }
            }

            packets.push(packet);
            offset += (packetLength + 5); // Start(2) + Length(1) + PacketLength + CRC(2) + Stop(2) = PacketLength + 5; fakat length pck icinde sayiliyor, pdf'e göre total = 5 + N (Length=N). Stop haric
        } else {
            offset++;
        }
    }

    return packets;
}

function parseLocation(buffer) {
    if (buffer.length < 18) return null;

    // Tarih Saat Parse (Y-A-G H:M:S)
    const year = buffer[0];
    const month = buffer[1];
    const day = buffer[2];
    const hour = buffer[3];
    const minute = buffer[4];
    const second = buffer[5];
    const datetime = `20${year.toString().padStart(2, '0')}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')} ${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}:${second.toString().padStart(2, '0')}`;

    // GPS Bilgi Uzunluğu & Uydu Sayısı (Byte 6)
    const gpsInfoLength = (buffer[6] >> 4) & 0x0F; // Üst 4 bit: GPS bilgi uzunluğu
    const satellites = buffer[6] & 0x0F;             // Alt 4 bit: Uydu sayısı

    // Latitude & Longitude (Ham değerler, Kuzey/Güney ve Doğu/Batı bayrakları ile düzeltilecek)
    const latDec = buffer.readUInt32BE(7);
    const lngDec = buffer.readUInt32BE(11);
    let lat = latDec / 30000 / 60;
    let lng = lngDec / 30000 / 60;

    // Speed
    const speed = buffer[15];

    // Course & Status (2 byte)
    // GT06N Protokolü:
    // Byte 16:
    //   Bit 7 (Bit 15): Reserved
    //   Bit 6 (Bit 14): Reserved
    //   Bit 5 (Bit 13): Longitude (0=East, 1=West)
    //   Bit 4 (Bit 12): Latitude (0=South, 1=North)
    const courseStatus = buffer.readUInt16BE(16);
    const course = courseStatus & 0x03FF;                      // Bit 0-9: Yön açısı
    const gpsPositioned = (courseStatus & 0x0800) >> 11;       // Bit 11: GPS konumlanmış mı
    const isWest = (courseStatus & 0x2000) >> 13;              // Bit 13: 1=West, 0=East
    const isNorth = (courseStatus & 0x1000) >> 12;             // Bit 12: 1=North, 0=South
    const isSouth = isNorth === 0;

    // Enlem/Boylam işaretlerini uygula
    if (isSouth) lat = -lat;
    if (isWest === 1) lng = -lng;

    // NOT: ACC bilgisi bu pakette YOK! GT06N'de ACC sadece Heartbeat (0x13) paketinde bulunur.
    // Konum paketinde ACC durumu heartbeat'ten gelen son bilgiyle birleştirilecek.

    return {
        datetime,
        lat: parseFloat(lat.toFixed(6)),
        lng: parseFloat(lng.toFixed(6)),
        speed,
        course,
        satellites,
        gpsPositioned: gpsPositioned === 1,
        status: { gpsFix: gpsPositioned === 1 }
        // ACC burada yok! server.js'de heartbeat'ten gelen ACC ile birleştirilecek.
    };
}

// Cihaza dönülecek cevap (Response) paketini hazirlar
function createResponse(protocolId, serialNumberNum) {
    const buffer = Buffer.alloc(10);
    buffer[0] = 0x78;
    buffer[1] = 0x78;
    buffer[2] = 0x05; // Packet Length
    buffer[3] = protocolId; // Hangi pakete cevap (0x01, 0x13, 0x16)
    
    // Information Serial Number (Gelen ile ayni)
    buffer.writeUInt16BE(serialNumberNum, 4);
    
    // CRC hesaplama (Length'ten Serial Number sonuna kadar, index 2'den 6'ya)
    const crc = getCrc16(buffer.subarray(2, 6));
    buffer.writeUInt16BE(crc, 6);
    
    // Stop Bit
    buffer[8] = 0x0D;
    buffer[9] = 0x0A;
    
    return buffer;
}

module.exports = {
    parseConcox,
    createResponse
};
