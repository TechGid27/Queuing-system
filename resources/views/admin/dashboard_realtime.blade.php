@section('scripts')
<script>
    setInterval(() => {
        const el = document.getElementById('topbar-clock');
        if (el) el.innerText = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }, 1000);

    // Real-time queue updates
    if (window.PUSHER_APP_KEY) {
        const pusher = new Pusher(window.PUSHER_APP_KEY, { 
            cluster: window.PUSHER_APP_CLUSTER || 'mt1', 
            forceTLS: true 
        });
        
        pusher.subscribe('queue').bind('queue.updated', function(data) {
            console.log('Queue updated:', data);
            // Reload page to show updated waiting list
            location.reload();
        });
    } else {
        // Fallback: poll every 10 seconds
        setInterval(() => {
            location.reload();
        }, 10000);
    }
</script>
@endsection
