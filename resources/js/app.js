import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher and Echo available globally (used inline in blade templates)
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: window.PUSHER_APP_KEY,
    cluster: window.PUSHER_APP_CLUSTER,
    forceTLS: window.PUSHER_SCHEME === 'https',
});
