import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import {
  MenuIcon,
  SearchIcon,
  BellIcon,
  BookingsIcon,
  RoomsIcon,
  GuestsIcon,
  BillingIcon,
  CheckIcon,
  XIcon,
  WrenchIcon,
  RupeeIcon,
} from './Icons';

export default function TopHeader({
  onToggleSidebar,
  searchQuery,
  setSearchQuery,
  onNavigate,
  onViewBooking,
}) {
  const { currencySymbol } = useHotelSettings();
  // Date string
  const currentDate = useMemo(() => {
    return new Date().toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }, []);

  // Notifications State
  const [isNotifOpen, setIsNotifOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loadingNotifs, setLoadingNotifs] = useState(false);
  const notifRef = useRef(null);

  // Search Results State
  const [searchResults, setSearchResults] = useState(null);
  const [isSearching, setIsSearching] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const searchRef = useRef(null);

  // Fetch unread count & recent notifications
  const fetchNotifications = useCallback(async () => {
    try {
      const data = await hotelApi.getNotifications({ limit: 15 });
      if (data) {
        setNotifications(data.notifications || []);
        setUnreadCount(data.unread_count || 0);
      }
    } catch (e) {
      console.warn('Failed to fetch notifications:', e);
    }
  }, []);

  useEffect(() => {
    fetchNotifications();
    const interval = setInterval(fetchNotifications, 30000); // Polling every 30s
    return () => clearInterval(interval);
  }, [fetchNotifications]);

  // Click outside listener for notifications and search dropdowns
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (notifRef.current && !notifRef.current.contains(e.target)) {
        setIsNotifOpen(false);
      }
      if (searchRef.current && !searchRef.current.contains(e.target)) {
        setIsSearchOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Debounced live global search
  useEffect(() => {
    if (!searchQuery || searchQuery.trim().length < 2) {
      setSearchResults(null);
      setIsSearchOpen(false);
      return;
    }

    const timer = setTimeout(async () => {
      setIsSearching(true);
      try {
        const results = await hotelApi.search(searchQuery.trim());
        setSearchResults(results);
        setIsSearchOpen(true);
      } catch (e) {
        console.warn('Search query failed:', e);
      } finally {
        setIsSearching(false);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  // Mark single notification read
  const handleMarkAsRead = async (id, e) => {
    e?.stopPropagation();
    try {
      await hotelApi.markNotificationAsRead(id);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
      setUnreadCount((c) => Math.max(0, c - 1));
    } catch (err) {
      console.error('Failed to mark notification read:', err);
    }
  };

  // Mark all read
  const handleMarkAllRead = async () => {
    try {
      await hotelApi.markAllNotificationsAsRead();
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
      setUnreadCount(0);
    } catch (err) {
      console.error('Failed to mark all notifications read:', err);
    }
  };

  // Notification item click -> navigate
  const handleNotificationClick = async (notif) => {
    if (!notif.is_read) {
      handleMarkAsRead(notif.id);
    }
    setIsNotifOpen(false);

    const data = notif.data || {};
    if (data.booking_id && onViewBooking) {
      onViewBooking(data.booking_id);
    } else if (data.target_tab && onNavigate) {
      onNavigate(data.target_tab);
    }
  };

  // Helper for notification type icon and color
  const getNotificationIcon = (type) => {
    switch (type) {
      case 'NEW_BOOKING':
        return <BookingsIcon size={16} className="text-emerald-700" />;
      case 'BOOKING_CANCELLED':
        return <XIcon size={16} className="text-rose-600" />;
      case 'PAYMENT_RECEIVED':
        return <RupeeIcon size={16} className="text-emerald-700" />;
      case 'REFUND_PROCESSED':
        return <BillingIcon size={16} className="text-amber-700" />;
      case 'ROOM_MAINTENANCE':
        return <WrenchIcon size={16} className="text-orange-600" />;
      case 'ROOM_CLEANING_REQUIRED':
      case 'ROOM_CLEANED':
        return <RoomsIcon size={16} className="text-teal-600" />;
      default:
        return <BellIcon size={16} className="text-muted" />;
    }
  };

  // Format relative timestamp
  const formatTimeAgo = (dateStr) => {
    if (!dateStr) return '';
    const diff = Math.floor((new Date() - new Date(dateStr)) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  };

  return (
    <header className="top-header">
      <div className="header-left">
        <button
          type="button"
          onClick={onToggleSidebar}
          className="menu-toggle-btn"
          aria-label="Toggle navigation"
        >
          <MenuIcon size={20} />
        </button>

        {/* Global Search Bar */}
        <div className="search-bar" ref={searchRef}>
          <span className="search-icon">
            <SearchIcon size={16} />
          </span>
          <input
            type="text"
            placeholder="Search bookings, guests, rooms..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onFocus={() => {
              if (searchResults) setIsSearchOpen(true);
            }}
            className="search-input"
          />
          {searchQuery && (
            <button
              type="button"
              className="clear-search-btn"
              onClick={() => {
                setSearchQuery('');
                setIsSearchOpen(false);
              }}
            >
              <XIcon size={14} />
            </button>
          )}

          {/* Live Search Results Dropdown */}
          {isSearchOpen && (
            <div className="global-search-dropdown shadow-lg">
              {isSearching ? (
                <div className="search-loading-row">Searching hotel records...</div>
              ) : (
                <>
                  {/* Bookings */}
                  {searchResults?.bookings?.length > 0 && (
                    <div className="search-section">
                      <div className="search-section-header">
                        <BookingsIcon size={14} />
                        <span>Bookings ({searchResults.bookings.length})</span>
                      </div>
                      <div className="search-results-list">
                        {searchResults.bookings.map((b) => (
                          <div
                            key={b.id}
                            className="search-result-item"
                            onClick={() => {
                              setIsSearchOpen(false);
                              if (onViewBooking) onViewBooking(b.id);
                            }}
                          >
                            <div className="item-main">
                              <span className="item-title font-medium">#{b.booking_number}</span>
                              <span className="item-sub text-muted">{b.guest_name}</span>
                            </div>
                            <div className="item-meta">
                              <span className={`status-pill status-${b.status.toLowerCase()}`}>
                                {b.status}
                              </span>
                              <span className="item-amount">
                                {formatCurrency(b.grand_total, currencySymbol)}
                              </span>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Guests */}
                  {searchResults?.guests?.length > 0 && (
                    <div className="search-section">
                      <div className="search-section-header">
                        <GuestsIcon size={14} />
                        <span>Guests ({searchResults.guests.length})</span>
                      </div>
                      <div className="search-results-list">
                        {searchResults.guests.map((g) => (
                          <div
                            key={g.id}
                            className="search-result-item"
                            onClick={() => {
                              setIsSearchOpen(false);
                              if (onNavigate) onNavigate('guests');
                            }}
                          >
                            <div className="item-main">
                              <span className="item-title font-medium">{g.name}</span>
                              <span className="item-sub text-muted">{g.email || g.phone}</span>
                            </div>
                            {g.vip_status && <span className="badge-vip">VIP</span>}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Rooms */}
                  {searchResults?.rooms?.length > 0 && (
                    <div className="search-section">
                      <div className="search-section-header">
                        <RoomsIcon size={14} />
                        <span>Rooms ({searchResults.rooms.length})</span>
                      </div>
                      <div className="search-results-list">
                        {searchResults.rooms.map((r) => (
                          <div
                            key={r.id}
                            className="search-result-item"
                            onClick={() => {
                              setIsSearchOpen(false);
                              if (onNavigate) onNavigate('rooms');
                            }}
                          >
                            <div className="item-main">
                              <span className="item-title font-medium">Room {r.room_number}</span>
                              <span className="item-sub text-muted">{r.room_type_name} • Floor {r.floor}</span>
                            </div>
                            <span className={`status-pill status-${r.operational_status.toLowerCase()}`}>
                              {r.operational_status}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* No Results */}
                  {searchResults?.bookings?.length === 0 &&
                    searchResults?.guests?.length === 0 &&
                    searchResults?.rooms?.length === 0 && (
                      <div className="search-empty-state">
                        No bookings, guests, or rooms found for "{searchQuery}".
                      </div>
                    )}
                </>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="header-right">
        {/* Notifications Dropdown Container */}
        <div className="notification-wrapper" ref={notifRef}>
          <button
            type="button"
            className="icon-btn"
            aria-label="Notifications"
            onClick={() => {
              setIsNotifOpen(!isNotifOpen);
              if (!isNotifOpen) fetchNotifications();
            }}
          >
            <BellIcon size={20} />
            {unreadCount > 0 && (
              <span className="notification-badge">{unreadCount > 99 ? '99+' : unreadCount}</span>
            )}
          </button>

          {/* Notifications Flyout Popup */}
          {isNotifOpen && (
            <div className="notification-dropdown shadow-lg">
              <div className="notif-header">
                <div className="notif-title-row">
                  <h3>Notifications</h3>
                  {unreadCount > 0 && (
                    <span className="unread-count-pill">{unreadCount} new</span>
                  )}
                </div>
                {unreadCount > 0 && (
                  <button
                    type="button"
                    onClick={handleMarkAllRead}
                    className="mark-all-read-btn"
                  >
                    Mark all read
                  </button>
                )}
              </div>

              <div className="notif-list">
                {notifications.length === 0 ? (
                  <div className="notif-empty-state">
                    <p>No notifications yet</p>
                    <span>Important operational updates will appear here</span>
                  </div>
                ) : (
                  notifications.map((n) => (
                    <div
                      key={n.id}
                      className={`notif-item ${!n.is_read ? 'unread' : ''}`}
                      onClick={() => handleNotificationClick(n)}
                    >
                      <div className="notif-icon-col">
                        <div className="notif-icon-bubble">
                          {getNotificationIcon(n.type)}
                        </div>
                      </div>
                      <div className="notif-content-col">
                        <div className="notif-item-header">
                          <span className="notif-item-title">{n.title}</span>
                          <span className="notif-time">{formatTimeAgo(n.created_at)}</span>
                        </div>
                        <p className="notif-message">{n.message}</p>
                      </div>
                      {!n.is_read && (
                        <div className="notif-action-col">
                          <button
                            type="button"
                            title="Mark as read"
                            className="mark-read-dot-btn"
                            onClick={(e) => handleMarkAsRead(n.id, e)}
                          >
                            <span className="unread-dot" />
                          </button>
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            </div>
          )}
        </div>

        {/* Date Pill Dropdown */}
        <div className="date-pill">
          <BookingsIcon size={16} />
          <span className="date-text">{currentDate}</span>
          <span className="caret">▾</span>
        </div>
      </div>
    </header>
  );
}
