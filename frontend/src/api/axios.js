import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 10000,
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Log errors in development for debugging
    if (import.meta.env.DEV) {
      console.error('[API Error]:', error?.response || error.message);
    }
    return Promise.reject(error);
  }
);

export default api;

