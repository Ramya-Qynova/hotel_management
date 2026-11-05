import React, { useState, useEffect, useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import authService from './api/auth';
import hotelApi from './api/hotelApi';
import Sidebar from './components/Sidebar';
import TopHeader from './components/TopHeader';
import PageTransition from './components/PageTransition';
import DashboardView from './components/DashboardView';
import BookingsView from './components/BookingsView';
import RoomsView from './components/RoomsView';
import GuestsView from './components/GuestsView';
import CheckInOutView from './components/CheckInOutView';
import ServicesView from './components/ServicesView';
import BillingView from './components/BillingView';
import ReportsView from './components/ReportsView';
import SettingsView from './components/SettingsView';
import NewBookingModal from './components/NewBookingModal';
import BookingDetailModal from './components/BookingDetailModal';
import LoginView from './components/LoginView';
import { HotelSettingsProvider, useHotelSettings } from './context/HotelSettingsContext';
import { hasModuleAccess } from './utils/rbac';

const ROUTE_TAB_MAP = {
  '': 'dashboard',
  'dashboard': 'dashboard',
  'bookings': 'bookings',
  'rooms': 'rooms',
  'guests': 'guests',
  'checkin-checkout': 'checkin-checkout',
  'services': 'services',
  'billing': 'billing',
  'reports': 'reports',
  'settings': 'settings',
};

const TAB_ROUTE_MAP = {
  'dashboard': '/',
  'bookings': '/bookings',
  'rooms': '/rooms',
  'guests': '/guests',
  'checkin-checkout': '/checkin-checkout',
  'services': '/services',
  'billing': '/billing',
  'reports': '/reports',
  'settings': '/settings',
};

function AppContent({ user, setUser, authChecked, checkAuth }) {
  const { settings } = useHotelSettings();
  const location = useLocation();
  const navigate = useNavigate();

  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [sidebarMobileOpen, setSidebarMobileOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  // Handle sidebar toggling for desktop (collapse) vs mobile (drawer)
  const handleToggleSidebar = useCallback(() => {
    if (typeof window !== 'undefined' && window.innerWidth <= 1024) {
      setSidebarMobileOpen((prev) => !prev);
    } else {
      setSidebarCollapsed((prev) => !prev);
    }
  }, []);

  // Resolve activeTab from URL pathname or hash for back/forward and deep link support
  const getActiveTab = useCallback(() => {
    if (location.hash) {
      const rawHash = location.hash.replace(/^#[/]?/, '').toLowerCase();
      if (ROUTE_TAB_MAP[rawHash]) {
        return ROUTE_TAB_MAP[rawHash];
      }
    }
    const rawPath = location.pathname.replace(/^\/+|\/+$/g, '').toLowerCase();
    return ROUTE_TAB_MAP[rawPath] || 'dashboard';
  }, [location.pathname, location.hash]);

  const activeTab = getActiveTab();

  // Smooth navigation handler supporting View Transitions and browser history
  const handleTabChange = useCallback((newTab) => {
    setSidebarMobileOpen(false);
    if (!newTab || newTab === activeTab) return;
    const targetRoute = TAB_ROUTE_MAP[newTab] || '/';

    if (
      typeof document !== 'undefined' &&
      typeof document.startViewTransition === 'function' &&
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      document.startViewTransition(() => {
        navigate(targetRoute);
      });
    } else {
      navigate(targetRoute);
    }
  }, [activeTab, navigate]);

  // Dashboard Data State
  const [dashboardData, setDashboardData] = useState(null);
  const [loadingDashboard, setLoadingDashboard] = useState(false);
  const [revenueDays, setRevenueDays] = useState(7);

  // Modals
  const [isNewBookingOpen, setIsNewBookingOpen] = useState(false);
  const [viewingBookingId, setViewingBookingId] = useState(null);

  // Fetch Dashboard Data
  const fetchDashboard = useCallback(async () => {
    if (!user) return;
    setLoadingDashboard(true);
    try {
      const data = await hotelApi.getDashboard(revenueDays);
      setDashboardData(data);
    } catch (err) {
      console.error('Failed to load dashboard metrics:', err);
    } finally {
      setLoadingDashboard(false);
    }
  }, [user, revenueDays]);

  useEffect(() => {
    if (user) {
      fetchDashboard();
    }
  }, [user, revenueDays, fetchDashboard]);

  // Handle Logout
  const handleLogout = async () => {
    try {
      await authService.logout();
    } catch (e) {
      console.warn('Logout request failed:', e);
    } finally {
      setUser(null);
      setDashboardData(null);
      navigate('/', { replace: true });
    }
  };

  // Top header search submit -> navigate to bookings tab if authorized
  const handleSearchSubmit = (query) => {
    if (!query) return;
    if (hasModuleAccess(user, 'bookings')) {
      handleTabChange('bookings');
    }
  };

  // If session is still checking
  if (!authChecked) {
    return (
      <div className="app-splash-loading">
        <div className="spinner-large" />
        <p className="mt-3 text-muted">
          Initializing {settings.hotel_name || 'Hotel'} Operations...
        </p>
      </div>
    );
  }

  // If user is not authenticated, show Staff Login Screen
  if (!user) {
    return (
      <LoginView
        onLoginSuccess={(authedUser) => {
          setUser(authedUser);
        }}
      />
    );
  }

  return (
    <div className={`hotel-app-layout ${sidebarCollapsed ? 'sidebar-collapsed' : ''} ${sidebarMobileOpen ? 'sidebar-mobile-open' : ''}`}>
      {/* Mobile Backdrop */}
      {sidebarMobileOpen && (
        <div
          className="sidebar-mobile-backdrop"
          onClick={() => setSidebarMobileOpen(false)}
          aria-label="Close navigation"
        />
      )}

      {/* Sidebar Navigation */}
      <Sidebar
        activeTab={activeTab}
        setActiveTab={handleTabChange}
        user={user}
        onLogout={handleLogout}
        mobileOpen={sidebarMobileOpen}
        onCloseMobile={() => setSidebarMobileOpen(false)}
      />

      {/* Main Container */}
      <div className="main-wrapper">
        {/* Top Header */}
        <TopHeader
          onToggleSidebar={handleToggleSidebar}
          searchQuery={searchQuery}
          setSearchQuery={setSearchQuery}
          onSearchSubmit={handleSearchSubmit}
          onNavigate={handleTabChange}
          onViewBooking={(id) => setViewingBookingId(id)}
        />

        {/* View Routing */}
        <main className="content-area">
          <PageTransition pageKey={activeTab}>
            {!hasModuleAccess(user, activeTab) ? (
              <div className="view-container">
                <div
                  className="card text-center"
                  style={{
                    padding: '3.5rem 1.5rem',
                    maxWidth: '560px',
                    margin: '3rem auto',
                    borderRadius: '16px',
                    boxShadow: '0 10px 25px -5px rgba(0, 0, 0, 0.05)',
                  }}
                >
                  <div style={{ fontSize: '3rem', marginBottom: '1rem' }}>🔒</div>
                  <h2 style={{ fontSize: '1.4rem', fontWeight: '700', marginBottom: '0.75rem', color: 'var(--text-primary)' }}>
                    Access Restricted
                  </h2>
                  <p style={{ color: 'var(--text-muted)', lineHeight: '1.6', marginBottom: '1.75rem' }}>
                    Your role (<strong>{user?.role?.display_name || user?.role?.name || 'Staff'}</strong>) does not have authorization to access the <strong>{activeTab}</strong> module.
                  </p>
                  <div>
                    <button
                      type="button"
                      onClick={() => handleTabChange('dashboard')}
                      className="btn btn-primary"
                    >
                      Return to Dashboard
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <>
                {activeTab === 'dashboard' && (
                  <DashboardView
                    user={user}
                    dashboardData={dashboardData}
                    loading={loadingDashboard}
                    revenueDays={revenueDays}
                    setRevenueDays={setRevenueDays}
                    onNavigate={handleTabChange}
                    onOpenNewBooking={() => setIsNewBookingOpen(true)}
                    onViewBooking={(id) => setViewingBookingId(id)}
                  />
                )}

                {activeTab === 'bookings' && (
                  <BookingsView
                    onOpenNewBooking={() => setIsNewBookingOpen(true)}
                    onViewBooking={(id) => setViewingBookingId(id)}
                  />
                )}

                {activeTab === 'rooms' && (
                  <RoomsView user={user} onRoomChanged={fetchDashboard} />
                )}

                {activeTab === 'guests' && (
                  <GuestsView
                    onOpenBookingWithGuest={(_guest) => {
                      setIsNewBookingOpen(true);
                    }}
                  />
                )}

                {activeTab === 'checkin-checkout' && (
                  <CheckInOutView
                    user={user}
                    onViewBooking={(id) => setViewingBookingId(id)}
                  />
                )}

                {activeTab === 'services' && <ServicesView user={user} />}

                {activeTab === 'billing' && <BillingView user={user} />}

                {activeTab === 'reports' && <ReportsView user={user} />}

                {activeTab === 'settings' && <SettingsView user={user} />}
              </>
            )}
          </PageTransition>
        </main>
      </div>

      {/* New Booking Modal */}
      <NewBookingModal
        isOpen={isNewBookingOpen}
        onClose={() => setIsNewBookingOpen(false)}
        onBookingCreated={() => {
          fetchDashboard();
        }}
      />

      {/* Booking Detail Modal */}
      <BookingDetailModal
        bookingId={viewingBookingId}
        isOpen={Boolean(viewingBookingId)}
        onClose={() => setViewingBookingId(null)}
        onBookingUpdated={() => {
          fetchDashboard();
        }}
      />
    </div>
  );
}

export default function App() {
  const [user, setUser] = useState(null);
  const [authChecked, setAuthChecked] = useState(false);

  // Load authenticated user profile
  const checkAuth = useCallback(async () => {
    try {
      const res = await authService.getMe();
      if (res?.data) {
        setUser(res.data?.user || res.data);
      } else {
        setUser(null);
      }
    } catch {
      setUser(null);
    } finally {
      setAuthChecked(true);
    }
  }, []);

  useEffect(() => {
    checkAuth();

    // Listen for unauthorized events from axios interceptor
    const handleUnauthorized = () => {
      setUser(null);
    };
    window.addEventListener('auth:unauthorized', handleUnauthorized);
    return () => window.removeEventListener('auth:unauthorized', handleUnauthorized);
  }, [checkAuth]);

  return (
    <HotelSettingsProvider user={user}>
      <AppContent
        user={user}
        setUser={setUser}
        authChecked={authChecked}
        checkAuth={checkAuth}
      />
    </HotelSettingsProvider>
  );
}
