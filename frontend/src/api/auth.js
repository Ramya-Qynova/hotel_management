import api, { initCsrf, formatApiError } from './axios';

/**
 * Authentication API Service using Laravel Sanctum SPA cookies.
 */
export const authService = {
  /**
   * Log in user with credentials.
   * Ensures CSRF cookie is initialized before posting.
   */
  async login({ email, password, remember = false }) {
    try {
      await initCsrf();
    } catch (csrfError) {
      if (import.meta.env.DEV) {
        console.warn('[Sanctum Auth]: Initial CSRF cookie fetch warning:', csrfError?.message);
      }
    }
    const response = await api.post('/auth/login', {
      email,
      password,
      remember,
    });
    return response.data;
  },

  /**
   * Log out currently authenticated session.
   */
  async logout() {
    const response = await api.post('/auth/logout');
    return response.data;
  },

  /**
   * Retrieve current authenticated user profile and assigned role.
   */
  async getMe() {
    const response = await api.get('/auth/me');
    return response.data;
  },

  /**
   * Change authenticated user's password.
   */
  async changePassword({ current_password, password, password_confirmation }) {
    const response = await api.post('/auth/change-password', {
      current_password,
      password,
      password_confirmation,
    });
    return response.data;
  },
};

export { initCsrf, formatApiError };
export default authService;

