import React, { useState, useEffect, useCallback } from 'react';
import api from './api/axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';

export default function App() {
  const [status, setStatus] = useState('loading'); // 'loading' | 'connected' | 'disconnected'
  const [data, setData] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [lastChecked, setLastChecked] = useState(null);

  const checkBackendHealth = useCallback(async () => {
    setStatus('loading');
    setErrorMessage('');
    try {
      const res = await api.get('/health');
      setData(res.data);
      setStatus('connected');
      setLastChecked(new Date());
    } catch (err) {
      setStatus('disconnected');
      setData(null);
      if (err.response) {
        setErrorMessage(`Backend responded with HTTP ${err.response.status}: ${err.response.statusText}`);
      } else if (err.request) {
        setErrorMessage(`Unable to reach backend at ${API_BASE_URL}/health. Make sure Laravel is running.`);
      } else {
        setErrorMessage(err.message || 'Unknown network error');
      }
    }
  }, []);

  useEffect(() => {
    checkBackendHealth();
  }, [checkBackendHealth]);

  return (
    <div className="app-layout">
      {/* Header */}
      <header className="navbar">
        <div className="navbar-container">
          <div className="navbar-brand">
            <span className="brand-icon">🏨</span>
            <span className="brand-title">Hotel Management System</span>
          </div>
          <div className="navbar-meta">
            <span className="env-tag">Dev Environment</span>
            <div className="status-indicator">
              <span
                className="status-dot"
                style={{
                  backgroundColor:
                    status === 'connected'
                      ? '#10b981'
                      : status === 'disconnected'
                      ? '#ef4444'
                      : '#f59e0b',
                }}
              />
              <span className="status-text">
                {status === 'connected'
                  ? 'Connected'
                  : status === 'disconnected'
                  ? 'Disconnected'
                  : 'Checking...'}
              </span>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="main-content">
        <section className="hero-section">
          <div className="badge-pill">Initial Setup Verification</div>
          <h1 className="hero-title">Hotel Management System</h1>
          <p className="hero-subtitle">
            Local development environment configured and verified.
          </p>
        </section>

        <section className="status-grid">
          {/* Health Check Card */}
          <div className={`card status-card ${status}`}>
            <div className="card-header">
              <h2 className="card-title">Backend API Status</h2>
              <button
                onClick={checkBackendHealth}
                disabled={status === 'loading'}
                className="btn btn-secondary btn-sm"
              >
                {status === 'loading' ? 'Checking...' : 'Refresh Status'}
              </button>
            </div>
            <div className="card-body">
              {status === 'loading' && (
                <div className="status-display">
                  <div className="spinner" />
                  <div>
                    <h3 className="status-heading">Connecting to Laravel API...</h3>
                    <p className="status-caption">Target: <code>{API_BASE_URL}/health</code></p>
                  </div>
                </div>
              )}

              {status === 'connected' && (
                <div className="status-display">
                  <div className="status-icon-circle success">✓</div>
                  <div>
                    <h3 className="status-heading">Backend Connected Successfully</h3>
                    <p className="status-caption">
                      API Response: <code>{JSON.stringify(data)}</code>
                    </p>
                    {lastChecked && (
                      <p className="timestamp-note">
                        Verified at: {lastChecked.toLocaleTimeString()}
                      </p>
                    )}
                  </div>
                </div>
              )}

              {status === 'disconnected' && (
                <div className="status-display">
                  <div className="status-icon-circle error">✕</div>
                  <div>
                    <h3 className="status-heading">Backend Disconnected</h3>
                    <p className="status-caption error-text">{errorMessage}</p>
                    <p className="helper-text">
                      Run <code>php artisan serve</code> inside <code>backend/</code> to start the API server.
                    </p>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Environment Configuration Card */}
          <div className="card">
            <div className="card-header">
              <h2 className="card-title">Environment Information</h2>
            </div>
            <div className="card-body">
              <dl className="details-list">
                <div className="detail-row">
                  <dt>Frontend URL</dt>
                  <dd><code>http://localhost:5173</code></dd>
                </div>
                <div className="detail-row">
                  <dt>Backend API Base</dt>
                  <dd><code>{API_BASE_URL}</code></dd>
                </div>
                <div className="detail-row">
                  <dt>Health Endpoint</dt>
                  <dd><code>GET /api/health</code></dd>
                </div>
                <div className="detail-row">
                  <dt>CORS Allowed Origin</dt>
                  <dd><code>http://localhost:5173</code></dd>
                </div>
                <div className="detail-row">
                  <dt>Target Database</dt>
                  <dd><code>hotel_management (MySQL)</code></dd>
                </div>
              </dl>
            </div>
          </div>

          {/* Checklist Card */}
          <div className="card">
            <div className="card-header">
              <h2 className="card-title">Environment Setup Checklist</h2>
            </div>
            <div className="card-body">
              <ul className="checklist">
                <li className="checklist-item done">
                  <span className="check-icon">✓</span>
                  <span>React 19 + Vite initialized</span>
                </li>
                <li className="checklist-item done">
                  <span className="check-icon">✓</span>
                  <span>Axios API client configured</span>
                </li>
                <li className="checklist-item done">
                  <span className="check-icon">✓</span>
                  <span>Laravel 12 REST API & Sanctum installed</span>
                </li>
                <li className="checklist-item done">
                  <span className="check-icon">✓</span>
                  <span>Backend directory structure simplified & cleaned</span>
                </li>
                <li className="checklist-item done">
                  <span className="check-icon">✓</span>
                  <span>CORS configured for <code>http://localhost:5173</code></span>
                </li>
                <li className="checklist-item done">
                  <span className="check-icon">✓</span>
                  <span>Health endpoint <code>/api/health</code> active</span>
                </li>
              </ul>
            </div>
          </div>
        </section>
      </main>

      {/* Footer */}
      <footer className="footer">
        <div className="footer-container">
          <p>© {new Date().getFullYear()} Hotel Management System. Local Development Environment.</p>
          <p className="footer-sub">React + Vite • Laravel REST API • MySQL</p>
        </div>
      </footer>
    </div>
  );
}
