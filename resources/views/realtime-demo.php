<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toporia Realtime - Laravel-style Auth Demo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }

        .header {
            text-align: center;
            padding: 30px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p { color: rgba(255,255,255,0.7); font-size: 0.9rem; }

        .status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ff4757;
        }
        .status-dot.connected { background: #2ed573; }
        .status-dot.authenticated { background: #ffa502; }

        .auth-panel {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .auth-panel h3 {
            margin-bottom: 15px;
            color: #667eea;
            font-size: 1rem;
        }
        .auth-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .auth-form input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 5px;
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 0.9rem;
        }
        .auth-form button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-primary:hover { background: #5a6fd6; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        .btn-danger { background: #ff4757; color: #fff; }
        .btn-danger:hover { background: #ff3344; }

        .channel-panel {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .channel-panel h3 {
            margin-bottom: 15px;
            color: #667eea;
            font-size: 1rem;
        }
        .channel-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .channel-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: rgba(102, 126, 234, 0.3);
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .channel-tag.private { background: rgba(255, 165, 2, 0.3); }
        .channel-tag.presence { background: rgba(46, 213, 115, 0.3); }
        .channel-tag .remove {
            cursor: pointer;
            opacity: 0.7;
        }
        .channel-tag .remove:hover { opacity: 1; }

        .subscribe-form {
            display: flex;
            gap: 10px;
        }
        .subscribe-form input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 5px;
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 0.85rem;
        }

        .panel {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .panel-title {
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #667eea;
        }

        .event-list { max-height: 400px; overflow-y: auto; }
        .event-item {
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 3px solid #667eea;
            animation: slideIn 0.3s ease-out;
            font-size: 0.85rem;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .event-item.success { border-left-color: #2ed573; }
        .event-item.warning { border-left-color: #ffa502; }
        .event-item.error { border-left-color: #ff4757; }
        .event-item .event-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .event-item .event-name { font-weight: 600; color: #667eea; }
        .event-item .event-channel { color: rgba(255,255,255,0.5); font-size: 0.8rem; }
        .event-item .timestamp { font-size: 0.75rem; color: rgba(255,255,255,0.4); }
        .event-item .event-data {
            color: rgba(255,255,255,0.7);
            font-family: monospace;
            font-size: 0.8rem;
            background: rgba(0,0,0,0.2);
            padding: 8px;
            border-radius: 4px;
            overflow-x: auto;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: rgba(255,255,255,0.5);
        }

        .api-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.8rem;
        }
        .api-info h4 { margin-bottom: 10px; color: #667eea; font-size: 0.9rem; }
        .api-info pre {
            background: rgba(0,0,0,0.5);
            padding: 12px;
            border-radius: 5px;
            overflow-x: auto;
            color: #2ed573;
        }
        .api-info code { color: #ffa502; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Toporia Realtime</h1>
            <p>Laravel-style Broadcasting with Private/Presence Channels</p>
        </header>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-item">
                <span class="status-dot" id="connDot"></span>
                <span id="connStatus">Disconnected</span>
            </div>
            <div class="status-item">
                <span class="status-dot" id="authDot"></span>
                <span id="authStatus">Not authenticated</span>
            </div>
            <div class="status-item">
                <span>Socket ID: <code id="socketId">-</code></span>
            </div>
        </div>

        <!-- Authentication Panel -->
        <div class="auth-panel">
            <h3>1. Authentication</h3>

            <!-- Session Auth (Primary for web) -->
            <div style="margin-bottom: 15px; padding: 15px; background: rgba(46, 213, 115, 0.1); border-radius: 8px; border: 1px solid rgba(46, 213, 115, 0.3);">
                <h4 style="color: #2ed573; font-size: 0.9rem; margin-bottom: 10px;">Session Auth (Recommended for Web)</h4>
                <div class="auth-form">
                    <button class="btn-primary" onclick="authenticateWithSession()" style="background: #2ed573;">
                        Authenticate with Session
                    </button>
                    <span id="sessionStatus" style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">
                        Session ID: <code id="sessionIdDisplay">-</code>
                    </span>
                </div>
                <p style="margin-top: 8px; font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                    Uses your current PHP session. Login via <code>/login</code> page first.
                </p>
            </div>

            <!-- JWT Auth (Alternative) -->
            <div style="padding: 15px; background: rgba(102, 126, 234, 0.1); border-radius: 8px; border: 1px solid rgba(102, 126, 234, 0.3);">
                <h4 style="color: #667eea; font-size: 0.9rem; margin-bottom: 10px;">JWT Token Auth (For API)</h4>
                <div class="auth-form">
                    <input type="text" id="tokenInput" placeholder="Enter JWT token">
                    <button class="btn-primary" onclick="authenticateWithToken()">Authenticate</button>
                    <button class="btn-secondary" onclick="generateDemoToken()">Demo Token</button>
                </div>
                <p style="margin-top: 8px; font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                    Get token from <code>POST /api/auth/login</code>
                </p>
            </div>

            <div style="margin-top: 15px;">
                <button class="btn-danger" onclick="logout()">Logout / Clear Auth</button>
            </div>
        </div>

        <!-- Channel Subscription Panel -->
        <div class="channel-panel">
            <h3>2. Channel Subscriptions</h3>
            <div class="channel-list" id="channelList">
                <span style="color: rgba(255,255,255,0.5);">No channels subscribed</span>
            </div>
            <div class="subscribe-form">
                <input type="text" id="channelInput" placeholder="Channel name (e.g., notifications, private-user.123, presence-chat.room1)">
                <button class="btn-primary" onclick="subscribeChannel()">Subscribe</button>
            </div>
            <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-secondary" onclick="quickSubscribe('notifications')">Public: notifications</button>
                <button class="btn-secondary" id="btnPrivateUser" onclick="quickSubscribePrivateUser()">Private: user.<span id="privateUserIdBtn">?</span></button>
                <button class="btn-secondary" onclick="quickSubscribe('presence-chat.room1')">Presence: chat.room1</button>
            </div>
        </div>

        <!-- Events Panel -->
        <div class="panel">
            <h3 class="panel-title">
                <span>Events</span>
                <span id="eventCount" style="margin-left: auto; background: #667eea; padding: 2px 10px; border-radius: 15px; font-size: 0.8rem;">0</span>
                <button class="btn-secondary" onclick="clearEvents()" style="padding: 5px 10px; font-size: 0.8rem;">Clear</button>
            </h3>
            <div class="event-list" id="eventList">
                <div class="empty-state">Waiting for events...</div>
            </div>
        </div>

        <!-- API Info -->
        <div class="api-info">
            <h4>Send Test Notification</h4>
            <pre>curl -X POST http://localhost:8000/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{"type":"success","title":"Hello!","message":"Test message"}'</pre>

            <h4 style="margin-top: 15px;">Private Channel (with auth)</h4>
            <pre># First get auth signature
curl -X POST http://localhost:8000/api/broadcasting/auth \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"socket_id":"YOUR_SOCKET_ID","channel_name":"private-user.123"}'

# Response: {"auth":"toporia:HMAC_SIGNATURE"}</pre>
        </div>
    </div>

    <!-- Socket.IO Client Library -->
    <script src="https://cdn.socket.io/4.7.4/socket.io.min.js"></script>

    <script>
        const SOCKET_HOST = '<?= e($wsHost ?? '127.0.0.1') ?>';
        const SOCKET_PORT = '<?= e($wsPort ?? '3000') ?>';
        const API_BASE = 'http://localhost:8000/api';

        let socket = null;
        let socketId = null;
        let jwtToken = null;
        let subscribedChannels = [];
        let events = [];
        let currentUserId = null;

        // DOM Elements
        const connDot = document.getElementById('connDot');
        const connStatus = document.getElementById('connStatus');
        const authDot = document.getElementById('authDot');
        const authStatus = document.getElementById('authStatus');
        const socketIdEl = document.getElementById('socketId');
        const tokenInput = document.getElementById('tokenInput');
        const channelInput = document.getElementById('channelInput');
        const channelList = document.getElementById('channelList');
        const eventList = document.getElementById('eventList');
        const eventCount = document.getElementById('eventCount');

        // =========================================================================
        // Socket.IO Connection
        // =========================================================================
        function connect() {
            const url = `http://${SOCKET_HOST}:${SOCKET_PORT}`;

            socket = io(url, {
                transports: ['websocket', 'polling'],
                reconnection: true,
                reconnectionAttempts: 10,
                reconnectionDelay: 1000
            });

            socket.on('connect', () => {
                connDot.classList.add('connected');
                connStatus.textContent = 'Connected';
                addEvent('system', 'connect', { message: 'Connected to server' }, 'success');
            });

            socket.on('disconnect', (reason) => {
                connDot.classList.remove('connected');
                connStatus.textContent = 'Disconnected: ' + reason;
                socketIdEl.textContent = '-';
                addEvent('system', 'disconnect', { reason }, 'error');
            });

            socket.on('connect_error', (error) => {
                connDot.classList.remove('connected');
                connStatus.textContent = 'Connection error';
                addEvent('system', 'connect_error', { message: error.message }, 'error');
            });

            // Socket.IO CONNECT acknowledgment (contains sid)
            socket.io.on('open', () => {
                // SID is available after Engine.IO open
                setTimeout(() => {
                    socketId = socket.id;
                    socketIdEl.textContent = socketId || '-';
                }, 100);
            });

            // Authentication response
            socket.on('authenticated', (data) => {
                authDot.classList.add('connected');
                const method = data.method || 'unknown';
                authStatus.textContent = `User #${data.user_id} (${method})`;
                socketId = data.socket_id;
                socketIdEl.textContent = socketId || socket.id || '-';

                // Store user ID and update button
                currentUserId = data.user_id;
                updatePrivateUserButton();

                addEvent('auth', 'authenticated', data, 'success');
            });

            socket.on('auth_error', (data) => {
                authDot.classList.remove('connected');
                authStatus.textContent = 'Auth failed';
                addEvent('auth', 'auth_error', data, 'error');
            });

            // Subscription responses
            socket.on('subscribed', (data) => {
                if (!subscribedChannels.includes(data.channel)) {
                    subscribedChannels.push(data.channel);
                    renderChannels();
                }
                addEvent(data.channel, 'subscribed', data, 'success');
            });

            socket.on('subscription_error', (data) => {
                addEvent(data.channel || 'unknown', 'subscription_error', data, 'error');
            });

            // Notification events
            socket.on('notification', (data) => {
                addEvent(data.channel || 'notification', 'notification', data, data.type || 'info');
            });

            // Presence events
            socket.on('presence:member_added', (data) => {
                addEvent(data.channel || 'presence', 'member_added', data, 'success');
            });

            socket.on('presence:member_removed', (data) => {
                addEvent(data.channel || 'presence', 'member_removed', data, 'warning');
            });

            // Generic event handler for channel messages
            socket.onAny((eventName, ...args) => {
                if (!['connect', 'disconnect', 'authenticated', 'auth_error', 'subscribed', 'subscription_error', 'notification', 'presence:member_added', 'presence:member_removed'].includes(eventName)) {
                    const data = args[0] || {};
                    addEvent(data.channel || eventName, eventName, data, 'info');
                }
            });
        }

        // =========================================================================
        // Authentication
        // =========================================================================

        // Get session ID from cookie
        function getSessionId() {
            const match = document.cookie.match(/PHPSESSID=([^;]+)/);
            return match ? match[1] : null;
        }

        // Display session ID on page load
        function displaySessionId() {
            const sessionId = getSessionId();
            const sessionIdDisplay = document.getElementById('sessionIdDisplay');
            if (sessionId) {
                sessionIdDisplay.textContent = sessionId.substring(0, 12) + '...';
                sessionIdDisplay.title = sessionId;
            } else {
                sessionIdDisplay.textContent = 'Not found';
            }
        }

        // Session-based authentication (for web users)
        function authenticateWithSession() {
            const sessionId = getSessionId();

            if (!sessionId) {
                addEvent('auth', 'session_error', {
                    message: 'No PHP session found. Please login first via /login page.'
                }, 'error');
                alert('No session found. Please login via the web form first.');
                return;
            }

            addEvent('auth', 'session_auth', {
                message: 'Authenticating with session...',
                session_id: sessionId.substring(0, 12) + '...'
            }, 'info');

            // Send session auth to server
            socket.emit('auth', {
                session_id: sessionId,
                guard: 'web'
            });
        }

        // JWT token authentication (for API users)
        function authenticateWithToken() {
            const token = tokenInput.value.trim();

            if (!token) {
                alert('Please enter a JWT token or click "Demo Token"');
                return;
            }

            jwtToken = token;
            addEvent('auth', 'jwt_auth', { message: 'Authenticating with JWT token...' }, 'info');
            socket.emit('auth', { token, guard: 'api' });
        }

        // Legacy function name for compatibility
        function authenticate() {
            authenticateWithToken();
        }

        function generateDemoToken() {
            // Create a simple demo JWT (NOT FOR PRODUCTION!)
            // This is just for testing - in production, get token from /api/auth/login
            const header = btoa(JSON.stringify({ alg: 'HS256', typ: 'JWT' })).replace(/=/g, '');
            const payload = btoa(JSON.stringify({
                sub: 1,
                name: 'Demo User',
                email: 'demo@example.com',
                roles: ['user'],
                iat: Math.floor(Date.now() / 1000),
                exp: Math.floor(Date.now() / 1000) + 3600
            })).replace(/=/g, '');

            // Note: This signature is fake - real JWT needs proper HMAC with secret
            const signature = 'demo_signature_for_testing_only';

            tokenInput.value = `${header}.${payload}.${signature}`;
            addEvent('system', 'demo_token', { message: 'Demo token generated (for testing only)' }, 'warning');
        }

        function logout() {
            jwtToken = null;
            currentUserId = null;
            authDot.classList.remove('connected');
            authStatus.textContent = 'Not authenticated';
            updatePrivateUserButton();

            // Unsubscribe from all private/presence channels
            subscribedChannels.filter(ch => ch.startsWith('private-') || ch.startsWith('presence-')).forEach(ch => {
                socket.emit('unsubscribe', { channel: ch });
            });
            subscribedChannels = subscribedChannels.filter(ch => !ch.startsWith('private-') && !ch.startsWith('presence-'));
            renderChannels();

            addEvent('auth', 'logout', { message: 'Logged out' }, 'info');
        }

        // =========================================================================
        // Channel Subscription
        // =========================================================================
        function subscribeChannel() {
            const channel = channelInput.value.trim();
            if (!channel) return;

            if (subscribedChannels.includes(channel)) {
                addEvent(channel, 'already_subscribed', { message: 'Already subscribed' }, 'warning');
                return;
            }

            const isPrivate = channel.startsWith('private-') || channel.startsWith('presence-');

            if (isPrivate && !jwtToken) {
                // For private channels without auth, try direct subscription
                // Server will check if connection is already authenticated
                socket.emit('subscribe', { channel });
            } else if (isPrivate && jwtToken) {
                // Laravel-style: Get auth signature from server first
                subscribeWithAuth(channel);
            } else {
                // Public channel - direct subscribe
                socket.emit('subscribe', { channel });
            }

            channelInput.value = '';
        }

        async function subscribeWithAuth(channel) {
            try {
                addEvent(channel, 'auth_request', { message: 'Requesting auth signature...' }, 'info');

                const response = await fetch(`${API_BASE}/broadcasting/auth`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${jwtToken}`
                    },
                    body: JSON.stringify({
                        socket_id: socketId || socket.id,
                        channel_name: channel
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    addEvent(channel, 'auth_denied', error, 'error');
                    return;
                }

                const authData = await response.json();
                addEvent(channel, 'auth_success', authData, 'success');

                // Subscribe with auth signature
                socket.emit('subscribe', {
                    channel,
                    auth: authData.auth,
                    channel_data: authData.channel_data
                });

            } catch (error) {
                addEvent(channel, 'auth_error', { message: error.message }, 'error');
            }
        }

        function quickSubscribe(channel) {
            channelInput.value = channel;
            subscribeChannel();
        }

        function quickSubscribePrivateUser() {
            if (!currentUserId) {
                alert('Please authenticate first to subscribe to your private channel');
                return;
            }
            quickSubscribe(`private-user.${currentUserId}`);
        }

        function updatePrivateUserButton() {
            const btn = document.getElementById('privateUserIdBtn');
            if (btn) {
                btn.textContent = currentUserId || '?';
            }
        }

        function unsubscribeChannel(channel) {
            socket.emit('unsubscribe', { channel });
            subscribedChannels = subscribedChannels.filter(ch => ch !== channel);
            renderChannels();
            addEvent(channel, 'unsubscribed', { channel }, 'info');
        }

        function renderChannels() {
            if (subscribedChannels.length === 0) {
                channelList.innerHTML = '<span style="color: rgba(255,255,255,0.5);">No channels subscribed</span>';
                return;
            }

            channelList.innerHTML = subscribedChannels.map(ch => {
                let type = 'public';
                if (ch.startsWith('private-')) type = 'private';
                if (ch.startsWith('presence-')) type = 'presence';

                return `<span class="channel-tag ${type}">
                    ${ch}
                    <span class="remove" onclick="unsubscribeChannel('${ch}')">&times;</span>
                </span>`;
            }).join('');
        }

        // =========================================================================
        // Events Display
        // =========================================================================
        function addEvent(channel, event, data, type = 'info') {
            events.unshift({
                id: Date.now(),
                channel,
                event,
                data,
                type,
                timestamp: new Date().toLocaleTimeString()
            });

            if (events.length > 100) events.pop();
            renderEvents();
        }

        function renderEvents() {
            eventCount.textContent = events.length;

            if (events.length === 0) {
                eventList.innerHTML = '<div class="empty-state">Waiting for events...</div>';
                return;
            }

            eventList.innerHTML = events.map(e => `
                <div class="event-item ${e.type}">
                    <div class="event-header">
                        <span>
                            <span class="event-name">${e.event}</span>
                            <span class="event-channel">${e.channel}</span>
                        </span>
                        <span class="timestamp">${e.timestamp}</span>
                    </div>
                    <div class="event-data">${JSON.stringify(e.data, null, 2)}</div>
                </div>
            `).join('');
        }

        function clearEvents() {
            events = [];
            renderEvents();
        }

        // =========================================================================
        // Initialize
        // =========================================================================
        connect();
        renderEvents();
        displaySessionId();
    </script>
</body>
</html>
