#!/bin/bash
# FiloMerkez TCP Server Güncelleme Scripti
cd /root/tcp-server
pkill -f "node server.js" 2>/dev/null
sleep 1
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/parser.js
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/server.js
nohup node server.js > tcp.log 2>&1 &
echo "TCP Server guncellendi ve yeniden baslatildi!"
