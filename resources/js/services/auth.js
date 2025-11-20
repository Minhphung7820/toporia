/**
 * Authentication Service
 *
 * Handles all authentication API calls with HttpOnly cookies.
 * Cookies are automatically sent/received by browser.
 * Automatically handles CSRF token via XSRF-TOKEN cookie.
 */

const API_BASE = '/api/auth';
const CSRF_COOKIE_ENDPOINT = '/api/csrf-cookie';
const CSRF_COOKIE_NAME = 'XSRF-TOKEN';
const CSRF_HEADER_NAME = 'X-XSRF-TOKEN';

// Cache to prevent duplicate calls in the same tick
let getUserCache = null;
let getUserPromise = null;
let csrfCookiePromise = null;

/**
 * Get CSRF token from cookie
 *
 * Performance: O(N) where N = number of cookies (typically < 10)
 * Browser automatically URL-decodes cookie values from document.cookie
 *
 * @returns {string|null}
 */
function getCsrfToken() {
  const cookies = document.cookie.split(';');
  for (let cookie of cookies) {
    const [name, value] = cookie.trim().split('=');
    if (name === CSRF_COOKIE_NAME) {
      // Browser already decoded the cookie value
      // Do NOT call decodeURIComponent() again - causes double decoding
      return value;
    }
  }
  return null;
}

/**
 * Ensure CSRF cookie is set (call before state-changing requests)
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
    credentials: 'include', // Important: Send/receive cookies
  })
    .then(() => {
      csrfCookiePromise = null; // Reset after completion
    })
    .catch(error => {
      csrfCookiePromise = null;
      console.error('Failed to get CSRF cookie:', error);
      throw error;
    });

  return csrfCookiePromise;
}

/**
 * Get headers with CSRF token if available
 * @returns {Object}
 */
function getHeaders(includeCsrf = true) {
  const headers = {
    'Content-Type': 'application/json',
  };

  // Add CSRF token header if available
  if (includeCsrf) {
    const token = getCsrfToken();
    if (token) {
      headers[CSRF_HEADER_NAME] = token;
    }
  }

  return headers;
}

export const authService = {
  /**
   * Get CSRF cookie (call this before login/register if needed)
   */
  async getCsrfCookie() {
    await ensureCsrfCookie();
  },
  /**
   * Register new user
   */
  async register(data) {
    // Ensure CSRF cookie is set before registration
    await ensureCsrfCookie();

    const response = await fetch(`${API_BASE}/register`, {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'include', // Important: Send/receive cookies
      body: JSON.stringify(data),
    });
    return response.json();
  },

  /**
   * Login user
   */
  async login(email, password, remember = false) {
    // Ensure CSRF cookie is set before login
    await ensureCsrfCookie();

    const response = await fetch(`${API_BASE}/login`, {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'include', // Important: Send/receive cookies
      body: JSON.stringify({ email, password, remember }),
    });
    const result = await response.json();
    this.clearCache(); // Clear cache after login
    return result;
  },

  /**
   * Get current authenticated user
   * Uses caching to prevent duplicate calls in the same tick
   */
  async getUser() {
    // If there's already a pending request, return that promise
    if (getUserPromise) {
      return getUserPromise;
    }

    // Create new request
    getUserPromise = fetch(`${API_BASE}/user`, {
      credentials: 'include', // Cookie automatically sent
    })
      .then(response => response.json())
      .then(data => {
        getUserCache = data;
        getUserPromise = null; // Reset after completion
        return data;
      })
      .catch(error => {
        getUserPromise = null;
        getUserCache = null;
        throw error;
      });

    return getUserPromise;
  },

  /**
   * Clear user cache (call after login/logout)
   */
  clearCache() {
    getUserCache = null;
    getUserPromise = null;
  },

  /**
   * Logout user
   */
  async logout() {
    const response = await fetch(`${API_BASE}/logout`, {
      method: 'POST',
      credentials: 'include',
    });
    const result = await response.json();
    this.clearCache(); // Clear cache after logout
    return result;
  },

  /**
   * Request password reset
   */
  async forgotPassword(email) {
    // Ensure CSRF cookie is set
    await ensureCsrfCookie();

    const response = await fetch(`${API_BASE}/forgot-password`, {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'include',
      body: JSON.stringify({ email }),
    });
    return response.json();
  },

  /**
   * Reset password with token
   */
  async resetPassword(data) {
    // Ensure CSRF cookie is set
    await ensureCsrfCookie();

    const response = await fetch(`${API_BASE}/reset-password`, {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'include',
      body: JSON.stringify(data),
    });
    return response.json();
  },

  /**
   * Change password (authenticated)
   */
  async changePassword(data) {
    // Ensure CSRF cookie is set
    await ensureCsrfCookie();

    const response = await fetch(`${API_BASE}/change-password`, {
      method: 'POST',
      headers: getHeaders(),
      credentials: 'include',
      body: JSON.stringify(data),
    });
    return response.json();
  },
};

