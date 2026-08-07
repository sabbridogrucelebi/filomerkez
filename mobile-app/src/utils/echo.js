import Echo from 'laravel-echo';
import { CONFIG } from '../config';

// pusher-js react-native dist exports { Pusher: class }
// We need the actual constructor class, not the module wrapper
const PusherModule = require('pusher-js');
const PusherClass = PusherModule.Pusher || PusherModule.default || PusherModule;

// Determine host from config API url
let host = 'filomerkez.com'; // Default fallback
try {
    host = CONFIG.API_BASE_URL.replace(/^https?:\/\//, '').split('/')[0];
} catch (e) {
    console.warn("Could not parse API_BASE_URL, using default host:", host);
}

let echo = null;

try {
    echo = new Echo({
        broadcaster: 'pusher',
        // Pass the CLASS via "Pusher" option (capital P) so laravel-echo does: new Pusher(key, opts)
        // Do NOT use "client" — that expects an already-constructed instance
        Pusher: PusherClass,
        key: '01jcgeu0u2y2sk5nzfsz',
        wsHost: host,
        wsPort: 8080,
        wssPort: 443,
        forceTLS: true,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${CONFIG.API_BASE_URL.replace('/v1', '')}/broadcasting/auth`,
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    import('../api/axios').then(({ default: api }) => {
                        api.post('/broadcasting/auth', {
                            socket_id: socketId,
                            channel_name: channel.name
                        })
                        .then(response => callback(false, response.data))
                        .catch(error => callback(true, error));
                    });
                }
            };
        },
    });
} catch (e) {
    console.warn('Echo initialization failed, real-time features disabled:', e.message);
}

export default echo;
