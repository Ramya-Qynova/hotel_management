import axios from 'axios';

/**
 * Dynamically resolves the API base URL.
 * Automatically aligns hostnames between 'localhost' and '127.0.0.1' to prevent
 * cross-domain cookie isolation and CSRF token mismatches during local development.
 */
export const getApiBaseUrl = () => {
  const envUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  if (typeof window !== 'undefined' && window.location) {
    try {
      const currentHost = window.location.hostname;
      const parsed = new URL(envUrl, window.location.origin);
      if (
        (currentHost === 'localhost' || currentHost === '127.0.0.1') &&
        (parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1')
      ) {
        parsed.hostname = currentHost;
        return parsed.toString().replace(/\/$/, '');
      }
    } catch {
      // Fallback to configured envUrl
    }
  }
  return envUrl;
};

export const getBackendUrl = () => {
  const envBackend = import.meta.env.VITE_BACKEND_URL;
  if (envBackend) return envBackend.replace(/\/$/, '');
  const apiBase = getApiBaseUrl();
  return apiBase.replace(/\/api\/?$/, '').replace(/\/$/, '');
};

export const API_BASE_URL = getApiBaseUrl();
export const BACKEND_URL = getBackendUrl();

/**
 * Pre-configured Axios instance for Laravel Sanctum SPA Cookie-based Authentication.
 */
const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  timeout: 15000,
});

// Request interceptor to ensure current baseURL aligns with host and XSRF header is populated
api.interceptors.request.use((config) => {
  const dynamicBase = getApiBaseUrl();
  if (config.baseURL && (config.baseURL.includes('localhost') || config.baseURL.includes('127.0.0.1'))) {
    config.baseURL = dynamicBase;
  }

  // Backup extraction of XSRF-TOKEN cookie if withXSRFToken hasn't attached it
  if (typeof document !== 'undefined' && !config.headers['X-XSRF-TOKEN']) {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    if (match) {
      config.headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
    }
  }

  return config;
});

/**
 * Initialize CSRF protection by requesting the Sanctum CSRF cookie.
 * Must be invoked prior to sensitive POST/PUT/PATCH/DELETE authentication requests.
 */
export const initCsrf = async () => {
  const backend = getBackendUrl();
  return axios.get(`${backend}/sanctum/csrf-cookie`, {
    withCredentials: true,
    headers: {
      'Accept': 'application/json',
    },
  });
};

/**
 * Extract a human-readable, centralized error payload from an Axios response.
 */
export const formatApiError = (error) => {
  if (error?.response?.data) {
    const data = error.response.data;
    const errors = data.errors || null;
    let detailList = [];

    if (errors && typeof errors === 'object') {
      detailList = Object.values(errors).flat();
    }

    return {
      status: error.response.status,
      message: data.message || 'An unexpected error occurred.',
      errors,
      detailList,
    };
  }

  if (error?.request) {
    return {
      status: 0,
      message: 'Unable to reach the server. Please verify your network or backend status.',
      errors: null,
      detailList: [],
    };
  }

  return {
    status: -1,
    message: error?.message || 'Unknown application error.',
    errors: null,
    detailList: [],
  };
};

// Response Interceptor for Centralized Error Handling & Status Codes
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status;

    if (status === 401) {
      if (import.meta.env.DEV) {
        console.warn('[Sanctum Auth]: Unauthenticated or session expired (401).');
      }
      if (typeof window !== 'undefined') {
        window.dispatchEvent(
          new CustomEvent('auth:unauthorized', {
            detail: error.response?.data || { message: 'Unauthenticated' },
          })
        );
      }
    } else if (status === 403) {
      if (import.meta.env.DEV) {
        console.warn('[Sanctum Auth]: Access forbidden / insufficient role (403).');
      }
      if (typeof window !== 'undefined') {
        window.dispatchEvent(
          new CustomEvent('auth:forbidden', {
            detail: error.response?.data || { message: 'Forbidden' },
          })
        );
      }
    } else if (status === 422) {
      if (import.meta.env.DEV) {
        console.warn('[Sanctum Validation]: Request failed validation (422):', error.response?.data);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
