#!/bin/bash
echo "Starting FiloMerkez VPS TCP Bridge Installation..."

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# Create directory
mkdir -p /root/tcp-server
cd /root/tcp-server

# Download files
echo "Downloading server files..."
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/server.js
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/parser.js
curl -sO https://raw.githubusercontent.com/sabbridogrucelebi/filomerkez/main/tcp-server/package.json

# Install dependencies
echo "Installing NPM packages..."
npm install axios dotenv

# Install PM2
echo "Installing PM2 Process Manager..."
npm install -g pm2

# Start server
echo "Starting TCP Server..."
pm2 stop all || true
pm2 start server.js --name "tcp-bridge"
pm2 save
pm2 startup systemd -u root --hp /root || true

# Open Firewall
ufw allow 36025/tcp || true

echo ""
echo "==========================================================="
echo "✅ SUCCESS: The TCP Server is running on port 36025!"
echo "==========================================================="
echo ""
