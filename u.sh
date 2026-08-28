#!/bin/bash
# FiloMerkez TCP Server Güncelleme Scripti
cd /root/tcp-server
pkill -f "node server.js" 2>/dev/null
pm2 delete tcp-server 2>/dev/null
sleep 1
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/parser.js
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/server.js
npm install -g pm2 2>/dev/null
pm2 start server.js --name tcp-server
pm2 save
pm2 startup 2>/dev/null
echo "TCP Server pm2 ile baslatildi! Sunucu yeniden baslasa bile otomatik calisacak."
