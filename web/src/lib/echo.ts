import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { api, TOKEN_KEY } from './api';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

window.Pusher = Pusher;

/** Connects to Laravel Reverb. Private/presence channels are authorised with our API token. */
export function connectEcho(): Echo<'reverb'> {
    if (window.Echo) return window.Echo;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8085),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8085),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, callback: (error: Error | null, data: any) => void) => {
                api.post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name }, {
                    baseURL: `${import.meta.env.VITE_API_URL ?? 'http://localhost:8002'}/api`,
                    headers: { Authorization: `Bearer ${localStorage.getItem(TOKEN_KEY)}` },
                })
                    .then((response) => callback(null, response.data))
                    .catch((error) => callback(error, null));
            },
        }),
    });

    return window.Echo;
}

export function disconnectEcho(): void {
    window.Echo?.disconnect();
    window.Echo = undefined;
}
