const fs = require('fs');
const { Client } = require('ssh2');

const conn = new Client();
const ip = '37.148.214.241';
const password = 'Ll3#Ri3!Er8!Fe7!';

console.log('Connecting to VPS...');

conn.on('ready', () => {
    console.log('Client :: ready');
    
    // First, upload files via SFTP
    conn.sftp((err, sftp) => {
        if (err) throw err;
        
        console.log('SFTP session started. Uploading TCP Server files...');
        
        // Create directory
        sftp.mkdir('/root/tcp-server', (err) => {
            // Ignore error if it exists
            const serverCode = fs.readFileSync('tcp-server/server.js', 'utf8');
            const parserCode = fs.readFileSync('tcp-server/parser.js', 'utf8');
            const packageJson = fs.readFileSync('tcp-server/package.json', 'utf8');
            
            sftp.writeFile('/root/tcp-server/server.js', serverCode, (err) => {
                if (err) throw err;
                console.log('- server.js uploaded');
                sftp.writeFile('/root/tcp-server/parser.js', parserCode, (err) => {
                    if (err) throw err;
                    console.log('- parser.js uploaded');
                    sftp.writeFile('/root/tcp-server/package.json', packageJson, (err) => {
                        if (err) throw err;
                        console.log('- package.json uploaded');
                        
                        // Execute setup commands
                        const cmds = [
                            'curl -fsSL https://deb.nodesource.com/setup_20.x | bash -',
                            'apt-get install -y nodejs',
                            'cd /root/tcp-server && npm install axios dotenv mysql2',
                            'npm install -g pm2',
                            'cd /root/tcp-server && pm2 stop all || true',
                            'cd /root/tcp-server && pm2 start server.js --name "tcp-bridge"',
                            'pm2 save',
                            'pm2 startup systemd -u root --hp /root || true',
                            'ufw allow 36025/tcp || true'
                        ].join(' && ');

                        console.log('Running setup commands on VPS. This may take 1-2 minutes...');
                        conn.exec(cmds, (err, stream) => {
                            if (err) throw err;
                            stream.on('close', (code, signal) => {
                                console.log('VPS Setup Complete! Code: ' + code);
                                conn.end();
                            }).on('data', (data) => {
                                console.log('STDOUT: ' + data);
                            }).stderr.on('data', (data) => {
                                console.log('STDERR: ' + data);
                            });
                        });
                    });
                });
            });
        });
    });
}).on('error', (err) => {
    console.log('Connection error: ', err);
}).connect({
    host: ip,
    port: 22,
    username: 'root',
    password: password,
    readyTimeout: 20000
});
