import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { CONFIG } from '../config';

window.Pusher = Pusher;

// Determine host from config API url
// e.g., if CONFIG.API_BASE_URL is 'https://filomerkez.com/api', host is 'filomerkez.com'
let host = 'filomerkez.com'; // Default fallback
try {
    const url = new URL(CONFIG.API_BASE_URL);
    host = url.hostname;
} catch (e) {
    console.warn("Could not parse API_BASE_URL, using default host:", host);
}

const echo = new Echo({
    broadcaster: 'reverb',
    key: '01jcgeu0u2y2sk5nzfsz',
    wsHost: host,
    wsPort: 8080,
    wssPort: 443,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${CONFIG.API_BASE_URL.replace('/v1', '')}/broadcasting/auth`, // broadcasting/auth is typically outside v1
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

export default echo;
