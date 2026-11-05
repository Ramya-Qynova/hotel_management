import React, { useState } from 'react';
import authService, { formatApiError } from '../api/auth';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { LotusIcon } from './Icons';

export default function LoginView({ onLoginSuccess }) {
  const { settings } = useHotelSettings();
  const [email, setEmail] = useState('owner@hotel.com');
  const [password, setPassword] = useState('Password@123');
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrorMessage('');

    try {
      const res = await authService.login({ email, password });
      const user = res.data?.user || (await authService.getMe()).data;
      if (onLoginSuccess) {
        onLoginSuccess(user);
      }
    } catch (err) {
      const formatted = formatApiError(err);
      setErrorMessage(formatted.message || 'Login failed. Please check credentials.');
    } finally {
      setLoading(false);
    }
  };

  const hotelDisplayName = settings?.hotel_name?.trim() || 'Hotel Management System';
  const hotelTagline = settings?.hotel_tagline?.trim() || 'Staff Operations Portal';

  return (
    <div className="login-screen-wrap">
      <div className="login-card">
        <div className="login-brand-header">
          <div className="brand-logo-container mb-2">
            {settings?.hotel_logo_url ? (
              <img
                src={settings.hotel_logo_url}
                alt={hotelDisplayName}
                style={{ maxHeight: '48px', maxWidth: '100%', objectFit: 'contain' }}
              />
            ) : (
              <LotusIcon size={42} />
            )}
          </div>
          <h1 className="brand-hotel-name text-2xl">{hotelDisplayName}</h1>
          <p className="brand-tagline">{hotelTagline}</p>
        </div>

        {errorMessage && <div className="alert-box error mb-4">{errorMessage}</div>}

        <form onSubmit={handleSubmit} className="login-form">
          <div className="form-field">
            <label>Staff Email Address</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="e.g. owner@hotel.com"
              required
            />
          </div>

          <div className="form-field">
            <label>Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••••••"
              required
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="btn btn-primary w-full py-3 mt-2"
          >
            {loading ? 'Authenticating Session...' : 'Sign In to Hotel Dashboard'}
          </button>
        </form>

        <div className="login-footer-hint">
          <p>Local Development Credentials:</p>
          <code>owner@hotel.com / Password@123</code>
        </div>
      </div>
    </div>
  );
}
