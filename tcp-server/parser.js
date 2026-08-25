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
    
    // Cihaz UTC (GMT+0) zamanı gönderir
    const date = new Date(Date.UTC(2000 + year, month - 1, day, hour, minute, second));
    
    // Türkiye saati (UTC+3) için 3 saat ekle
    date.setUTCHours(date.getUTCHours() + 3);

    const trYear = date.getUTCFullYear();
    const trMonth = (date.getUTCMonth() + 1).toString().padStart(2, '0');
    const trDay = date.getUTCDate().toString().padStart(2, '0');
    const trHour = date.getUTCHours().toString().padStart(2, '0');
    const trMinute = date.getUTCMinutes().toString().padStart(2, '0');
    const trSecond = date.getUTCSeconds().toString().padStart(2, '0');

    const datetime = `${trYear}-${trMonth}-${trDay} ${trHour}:${trMinute}:${trSecond}`;

    // Latitude & Longitude
    const latDec = buffer.readUInt32BE(7);
    const lngDec = buffer.readUInt32BE(11);
    const lat = latDec / 30000 / 60;
    const lng = lngDec / 30000 / 60;

    // Speed
    const speed = buffer[15];

    // Course & Status
    const courseStatus = buffer.readUInt16BE(16);
    const course = courseStatus & 0x03FF; // Ilk 10 bit
    const acc = (courseStatus & 0x2000) >> 13; // 13. bit (0x2000) ACC durumudur
    const gpsFix = (courseStatus & 0x1000) >> 12;

    // Gerçek LBS ve status bitleri PDF'e göre çok daha detayli ama temelleri aldik.

    return {
        datetime,
        lat: parseFloat(lat.toFixed(6)),
        lng: parseFloat(lng.toFixed(6)),
        speed,
        course,
        status: { acc: acc === 1, gpsFix: gpsFix === 1 }
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
