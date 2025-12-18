/**
 * HTTP Client Service with Centralized Error Handling
 *
 * Provides a configured HTTP client with automatic:
 * - CSRF token handling
 * - Authentication via cookies
 * - Centralized error handling and redirects
 * - Request/response interceptors
 *
 * Performance optimizations:
 * - Single axios instance (reused)
 * - Lazy CSRF token fetching (only when needed)
 * - Error page redirects (avoid redundant API calls)
 */

import axios from 'axios';

// CSRF token management
const CSRF_COOKIE_ENDPOINT = '/api/csrf-cookie';
const CSRF_COOKIE_NAME = 'XSRF-TOKEN';
const CSRF_HEADER_NAME = 'X-XSRF-TOKEN';

let csrfCookiePromise = null;

/**
 * Get CSRF token from cookie
 * @returns {string|null}
 */
function getCsrfToken() {
  const cookies = document.cookie.split(';');
  for (let cookie of cookies) {
    const [name, value] = cookie.trim().split('=');
    if (name === CSRF_COOKIE_NAME) {
      return decodeURIComponent(value);
    }
  }
  return null;
}

/**
 * Ensure CSRF cookie is set (for state-changing requests)
 * @returns {Promise<void>}
 */
async function ensureCsrfCookie() {
  // If already fetching, return existing promise
  if (csrfCookiePromise) {
    return csrfCookiePromise;
  }

  // Check if cookie already exists
  if (getCsrfToken()) {
    return Promise.resolve();
  }

  // Fetch CSRF cookie
  csrfCookiePromise = fetch(CSRF_COOKIE_ENDPOINT, {
    method: 'GET',
    credentials: 'include',
  })
    .then(() => {
      csrfCookiePromise = null;
    })
    .catch(error => {
      csrfCookiePromise = null;
      console.error('[HTTP] Failed to get CSRF cookie:', error);
      throw error;
    });

  return csrfCookiePromise;
}

/**
 * Create configured axios instance
 */
const http = axios.create({
  baseURL: '/api',
  withCredentials: true, // Important: Send/receive cookies
  timeout: 30000, // 30 seconds
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

/**
 * Request Interceptor
 * - Add CSRF token for state-changing requests
 * - Ensure CSRF cookie is set
 */
http.interceptors.request.use(
  async config => {
    // For state-changing requests, ensure CSRF cookie and add header
    if (['post', 'put', 'patch', 'delete'].includes(config.method.toLowerCase())) {
      await ensureCsrfCookie();
      const token = getCsrfToken();
      if (token) {
        config.headers[CSRF_HEADER_NAME] = token;
      }
    }

    return config;
  },
  error => {
    console.error('[HTTP] Request error:', error);
    return Promise.reject(error);
  }
);

/**
 * Response Interceptor
 * - Centralized error handling
 * - Auto redirect to error pages using window.location (works across all Vue apps)
 */
http.interceptors.response.use(
  response => {
    // Success responses (2xx)
    return response;
  },
  error => {
    // Error responses
    if (!error.response) {
      // Network error (no response from server)
      console.error('[HTTP] Network error:', error.message);
      // Let component handle network errors - don't redirect
      return Promise.reject(error);
    }

    const { status, data } = error.response;

    // Handle specific error codes
    switch (status) {
      case 400:
        // Bad Request - Usually validation errors
        // Let component handle this (return error to caller)
        console.warn('[HTTP] Bad Request (400):', data);
        break;

      case 401:
        // Unauthorized - Not authenticated
        // Use full page redirect to ensure proper navigation across different Vue apps (main/admin)
        console.warn('[HTTP] Unauthorized (401)');
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        break;

      case 403:
        // Forbidden - Authenticated but not authorized
        console.warn('[HTTP] Forbidden (403)');
        window.location.href = '/error/403';
        break;

      case 404:
        // Not Found - Let component handle this (API 404 is normal for CRUD operations)
        console.warn('[HTTP] Not Found (404):', data);
        break;

      case 419:
        // CSRF token mismatch - Refresh the page to get new token
        console.warn('[HTTP] CSRF token mismatch (419)');
        csrfCookiePromise = null; // Reset CSRF cache
        // Reload current page to refresh CSRF token
        window.location.reload();
        break;

      case 422:
        // Unprocessable Entity - Validation errors
        // Let component handle this (return error to caller)
        console.warn('[HTTP] Validation Error (422):', data);
        break;

      case 429:
        // Too Many Requests - Rate limiting
        console.warn('[HTTP] Too Many Requests (429)');
        // Let component handle this with a toast/alert
        break;

      case 500:
      case 502:
      case 503:
      case 504:
        // Server errors - Use full page redirect
        console.error('[HTTP] Server Error:', status, data);
        window.location.href = '/error/500';
        break;

      default:
        // Other errors - Log and let component handle
        console.error('[HTTP] Error:', status, data);
    }

    // Always reject to allow component-level error handling if needed
    return Promise.reject(error);
  }
);

/**
 * Clear CSRF cookie cache (call after logout or CSRF errors)
 */
export function clearCsrfCache() {
  csrfCookiePromise = null;
}

/**
 * Export configured axios instance
 */
export default http;
