import { ref, onUnmounted } from 'vue';
import { useWebSocket } from './useWebSocket';

/**
 * Composable for tracking job progress via WebSocket with polling fallback.
 *
 * Connects to Toporia Realtime WebSocket server (port 6001)
 * and subscribes to channel: import.jobs.{jobId}
 *
 * Server: php console realtime:serve --transport=websocket --port=6001
 *
 * Usage:
 * ```js
 * const { job, isConnected, connect, disconnect } = useJobProgress();
 *
 * // Start tracking a job
 * connect(jobId);
 *
 * // Watch job progress
 * watch(job, (newJob) => {
 *   console.log('Progress:', newJob.progress);
 * });
 *
 * // Stop tracking
 * disconnect();
 * ```
 */
export function useJobProgress() {
  const job = ref(null);
  const error = ref(null);

  // WebSocket composable
  const {
    isConnected,
    connect: wsConnect,
    disconnect: wsDisconnect,
    subscribe,
    unsubscribe,
    on,
    off,
  } = useWebSocket({
    port: 6001,
    autoReconnect: true,
    maxReconnectAttempts: 10,
  });

  let currentJobId = null;
  let currentChannel = null;
  let pollingInterval = null;

  // Event handlers
  const handleProgress = (data) => {
    if (data) {
      // Merge WebSocket data with existing job data to preserve fields like can_download
      if (job.value) {
        job.value = { ...job.value, ...data };
      } else {
        job.value = data;
      }

      // If job completed/failed/cancelled, fetch full data from API to get can_download etc.
      if (['completed', 'failed', 'cancelled'].includes(data.status) && currentJobId) {
        fetchJobStatus(currentJobId);
        stopPolling();
      }
    }
  };

  const handleDone = (data) => {
    if (data) {
      job.value = { ...job.value, ...data };
      // Fetch full data from API
      if (currentJobId) {
        fetchJobStatus(currentJobId);
        stopPolling();
      }
    }
  };

  const handleError = (data) => {
    error.value = data?.message || 'Unknown error';
  };

  /**
   * Fetch current job status from API.
   */
  const fetchJobStatus = async (jobId) => {
    try {
      const response = await fetch(`/api/admin/posts/jobs/${jobId}`, {
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();

      if (result.success) {
        job.value = result.data;
        error.value = null;
        return result.data;
      }
    } catch (err) {
      console.error('Failed to fetch job status:', err);
    }
    return null;
  };

  /**
   * Connect to job progress stream via WebSocket.
   * Also starts polling as backup to ensure no missed updates.
   */
  const connect = (jobId) => {
    if (!jobId) return;

    // Disconnect existing connection
    disconnect();

    currentJobId = jobId;
    currentChannel = `import.jobs.${jobId}`;

    // Immediately fetch current status to sync state
    fetchJobStatus(jobId);

    // Try WebSocket
    try {
      wsConnect();

      // Subscribe to job channel
      subscribe(currentChannel);

      // Register event handlers for this channel
      on('progress', handleProgress, currentChannel);
      on('done', handleDone, currentChannel);
      on('error', handleError, currentChannel);

      // Also listen for global events (in case server sends without channel prefix)
      on('progress', handleProgress);
      on('done', handleDone);
      on('error', handleError);

      // Always start polling as backup (WebSocket may miss messages due to race condition)
      // Polling will stop automatically when job completes
      startPolling(jobId);

    } catch (err) {
      console.error('Failed to connect WebSocket:', err);
      startPolling(jobId);
    }
  };

  /**
   * Start polling fallback.
   */
  const startPolling = (jobId) => {
    stopPolling();

    const poll = async () => {
      try {
        const response = await fetch(`/api/admin/posts/jobs/${jobId}`, {
          credentials: 'include',
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
          job.value = result.data;
          error.value = null;

          // Stop polling if job finished
          if (['completed', 'failed', 'cancelled'].includes(result.data.status)) {
            stopPolling();
          }
        } else {
          error.value = result.message || 'Failed to fetch job status';
        }
      } catch (err) {
        console.error('Polling error:', err);
        error.value = err.message;
      }
    };

    // Initial poll
    poll();

    // Continue polling every 2 seconds
    pollingInterval = setInterval(poll, 2000);
  };

  /**
   * Stop polling.
   */
  const stopPolling = () => {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  };

  /**
   * Disconnect from job progress stream.
   */
  const disconnect = () => {
    // Remove event handlers
    if (currentChannel) {
      off('progress', handleProgress, currentChannel);
      off('done', handleDone, currentChannel);
      off('error', handleError, currentChannel);
      unsubscribe(currentChannel);
    }

    off('progress', handleProgress);
    off('done', handleDone);
    off('error', handleError);

    // Stop polling
    stopPolling();

    // Disconnect WebSocket
    wsDisconnect();

    currentJobId = null;
    currentChannel = null;
  };

  /**
   * Reset state.
   */
  const reset = () => {
    disconnect();
    job.value = null;
    error.value = null;
  };

  // Cleanup on unmount
  onUnmounted(() => {
    disconnect();
  });

  return {
    job,
    isConnected,
    error,
    connect,
    disconnect,
    reset,
  };
}
