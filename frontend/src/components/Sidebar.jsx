import React, { useState } from 'react';
import hotelExteriorImg from '../assets/hotel-exterior.jpg';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { hasModuleAccess } from '../utils/rbac';
import {
  LotusIcon,
  DashboardIcon,
  BookingsIcon,
  RoomsIcon,
  GuestsIcon,
  CheckInOutIcon,
  ServicesIcon,
  BillingIcon,
  ReportsIcon,
  SettingsIcon,
  LogoutIcon,
  XIcon,
} from './Icons';

export default function Sidebar({ activeTab, setActiveTab, user, onLogout, mobileOpen, onCloseMobile }) {
  const { settings } = useHotelSettings();
  const [imageFailed, setImageFailed] = useState(false);

  const hotelDisplayName = settings?.hotel_name?.trim() || 'Hotel Management';
  const hotelTagline = settings?.hotel_tagline?.trim() || 'Staff Operations Portal';
  const hotelExteriorSource = settings?.hotel_image_url || settings?.hotel_photo_url || hotelExteriorImg;

  const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: DashboardIcon, functional: true },
    { id: 'bookings', label: 'Bookings', icon: BookingsIcon, functional: true },
    { id: 'rooms', label: 'Rooms', icon: RoomsIcon, functional: true },
    { id: 'guests', label: 'Guests', icon: GuestsIcon, functional: true },
    { id: 'checkin-checkout', label: 'Check-in / Check-out', icon: CheckInOutIcon, functional: true },
    { id: 'services', label: 'Services', icon: ServicesIcon, functional: true },
    { id: 'billing', label: 'Billing & Payments', icon: BillingIcon, functional: true },
    { id: 'reports', label: 'Reports', icon: ReportsIcon, functional: true },
    { id: 'settings', label: 'Settings', icon: SettingsIcon, functional: true },
  ];

  return (
    <aside className={`sidebar ${mobileOpen ? 'mobile-open' : ''}`}>
      {/* Brand Header */}
      <div className="sidebar-brand">
        <div className="brand-logo-container">
          {settings?.hotel_logo_url ? (
            <img
              src={settings.hotel_logo_url}
              alt={hotelDisplayName}
              style={{ maxHeight: '32px', maxWidth: '32px', objectFit: 'contain' }}
            />
          ) : (
            <LotusIcon size={32} />
          )}
        </div>
        <div className="brand-titles">
          <h1 className="brand-hotel-name">{hotelDisplayName}</h1>
          <p className="brand-tagline">{hotelTagline}</p>
        </div>
        {onCloseMobile && (
          <button
            type="button"
            onClick={onCloseMobile}
            className="sidebar-mobile-close-btn"
            aria-label="Close navigation drawer"
          >
            <XIcon size={18} />
          </button>
        )}
      </div>

      {/* Navigation List */}
      <nav className="sidebar-nav">
        <ul className="nav-list">
          {navItems.filter((item) => hasModuleAccess(user, item.id)).map((item) => {
            const Icon = item.icon;
            const isActive = activeTab === item.id;

            return (
              <li key={item.id} className="nav-list-item">
                <button
                  type="button"
                  onClick={() => {
                    if (activeTab !== item.id) {
                      setActiveTab(item.id);
                    }
                    if (onCloseMobile) onCloseMobile();
                  }}
                  className={`nav-button ${isActive ? 'active' : ''}`}
                  aria-current={isActive ? 'page' : undefined}
                >
                  <span className="nav-icon">
                    <Icon size={18} />
                  </span>
                  <span className="nav-label">{item.label}</span>
                  {!item.functional && (
                    <span className="upcoming-pill">{item.upcomingBadge}</span>
                  )}
                </button>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Sidebar Footer with Hotel Photo Card & User Info */}
      <div className="sidebar-footer">
        {/* Luxury Hotel Photograph Card */}
        <div className="hotel-building-card">
          {!imageFailed ? (
            <div className="building-photo-wrapper">
              <img
                src={hotelExteriorSource}
                alt={hotelDisplayName}
                className="hotel-photo"
                onError={() => setImageFailed(true)}
              />
              <div className="hotel-photo-overlay">
                <span className="hotel-photo-badge">{hotelDisplayName}</span>
                <span className="hotel-photo-stars">★★★★★</span>
              </div>
            </div>
          ) : (
            /* Elegant Branded Fallback */
            <div className="hotel-photo-fallback">
              <div className="fallback-inner">
                <LotusIcon size={26} />
                <div className="fallback-text">
                  <span className="fallback-title">{hotelDisplayName}</span>
                  <span className="fallback-sub">{hotelTagline}</span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* User Profile Card */}
        <div className="user-profile-row">
          <div className="user-avatar">
            {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
          </div>
          <div className="user-info">
            <span className="user-name">{user?.name || 'Staff User'}</span>
            <span className="user-role">{user?.role?.display_name || user?.role?.name || 'Staff'}</span>
          </div>
        </div>

        {/* Logout Button */}
        <button type="button" onClick={onLogout} className="logout-btn">
          <LogoutIcon size={16} />
          <span>Logout</span>
        </button>
      </div>
    </aside>
  );
}
