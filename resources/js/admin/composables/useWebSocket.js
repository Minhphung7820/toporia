import { ref, onUnmounted } from 'vue';

/**
 * Composable for WebSocket connection to Toporia Realtime server.
 *
 * Server runs at: php console realtime:serve --transport=websocket --port=6001
 *
 * Usage:
 * ```js
 * const { isConnected, subscribe, unsubscribe, on, off, connect, disconnect } = useWebSocket();
 *
 * // Connect to WebSocket server
 * connect();
 *
 * // Subscribe to a channel
 * subscribe('import.jobs.{jobId}');
 *
 * // Listen for events
 * on('progress', (data) => {
 *   console.log('Progress:', data.progress);
 * });
 *
 * // Cleanup
 * disconnect();
 * ```
 */
export function useWebSocket(options = {}) {
  const {
    host = window.location.hostname,
    port = 6001,
    secure = window.location.protocol === 'https:',
    autoReconnect = true,
    reconnectInterval = 3000,
    maxReconnectAttempts = 5,
  } = options;

  const isConnected = ref(false);
  const error = ref(null);

  let socket = null;
  let reconnectAttempts = 0;
  let reconnectTimer = null;
  const eventHandlers = new Map();
  const subscribedChannels = new Set();

  /**
   * Build WebSocket URL.
   */
  const getWsUrl = () => {
    const protocol = secure ? 'wss' : 'ws';
    return `${protocol}://${host}:${port}`;
  };

  /**
   * Connect to WebSocket server.
   */
  const connect = () => {
    if (socket && socket.readyState === WebSocket.OPEN) {
      return;
    }

    try {
      const url = getWsUrl();
      socket = new WebSocket(url);

      socket.onopen = () => {
        isConnected.value = true;
        error.value = null;
        reconnectAttempts = 0;

        // Re-subscribe to channels after reconnect
        subscribedChannels.forEach((channel) => {
          sendMessage({ type: 'subscribe', channel });
        });
      };

      socket.onmessage = (event) => {
        try {
          const message = JSON.parse(event.data);
          handleMessage(message);
        } catch (err) {
          console.error('Failed to parse WebSocket message:', err);
        }
      };

      socket.onerror = (err) => {
        console.error('WebSocket error:', err);
        error.value = 'Connection error';
      };

      socket.onclose = () => {
        isConnected.value = false;

        if (autoReconnect && reconnectAttempts < maxReconnectAttempts) {
          reconnectTimer = setTimeout(() => {
            reconnectAttempts++;
            connect();
          }, reconnectInterval);
        }
      };
    } catch (err) {
      console.error('Failed to create WebSocket:', err);
      error.value = err.message;
    }
  };

  /**
   * Disconnect from WebSocket server.
   */
  const disconnect = () => {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    reconnectAttempts = maxReconnectAttempts; // Prevent auto-reconnect

    if (socket) {
      socket.close();
      socket = null;
    }

    isConnected.value = false;
    subscribedChannels.clear();
    eventHandlers.clear();
  };

  /**
   * Send message to server.
   */
  const sendMessage = (message) => {
    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.send(JSON.stringify(message));
    }
  };

  /**
   * Handle incoming message.
   */
  const handleMessage = (message) => {
    const { event, channel, data } = message;

    if (!event) return;

    // Call event handlers
    const handlers = eventHandlers.get(event);
    if (handlers) {
      handlers.forEach((handler) => {
        try {
          handler(data, channel, message);
        } catch (err) {
          console.error(`Error in event handler for "${event}":`, err);
        }
      });
    }

    // Also call channel-specific handlers
    if (channel) {
      const channelEventKey = `${channel}:${event}`;
      const channelHandlers = eventHandlers.get(channelEventKey);
      if (channelHandlers) {
        channelHandlers.forEach((handler) => {
          try {
            handler(data, channel, message);
          } catch (err) {
            console.error(`Error in channel handler for "${channelEventKey}":`, err);
          }
        });
      }
    }
  };

  /**
   * Subscribe to a channel.
   */
  const subscribe = (channel) => {
    subscribedChannels.add(channel);

    if (socket && socket.readyState === WebSocket.OPEN) {
      sendMessage({ type: 'subscribe', channel });
    }
  };

  /**
   * Unsubscribe from a channel.
   */
  const unsubscribe = (channel) => {
    subscribedChannels.delete(channel);

    if (socket && socket.readyState === WebSocket.OPEN) {
      sendMessage({ type: 'unsubscribe', channel });
    }
  };

  /**
   * Register event handler.
   *
   * @param {string} event Event name (e.g., 'progress', 'done')
   * @param {Function} handler Handler function
   * @param {string|null} channel Optional channel to scope handler
   */
  const on = (event, handler, channel = null) => {
    const key = channel ? `${channel}:${event}` : event;

    if (!eventHandlers.has(key)) {
      eventHandlers.set(key, new Set());
    }
    eventHandlers.get(key).add(handler);
  };

  /**
   * Remove event handler.
   */
  const off = (event, handler, channel = null) => {
    const key = channel ? `${channel}:${event}` : event;

    if (eventHandlers.has(key)) {
      eventHandlers.get(key).delete(handler);
    }
  };

  // Cleanup on unmount
  onUnmounted(() => {
    disconnect();
  });

  return {
    isConnected,
    error,
    connect,
    disconnect,
    subscribe,
    unsubscribe,
    on,
    off,
    sendMessage,
  };
}
