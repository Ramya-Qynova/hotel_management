import React, { useState, useEffect } from 'react';
import {
  RoomsIcon,
  CheckCircleIcon,
  BookingsIcon,
  RupeeIcon,
  WrenchIcon,
  ArrowRightIcon,
  FileTextIcon,
  SearchIcon,
  XIcon,
} from './Icons';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency, formatDate } from '../utils/formatters';

export default function DashboardView({
  user,
  dashboardData,
  loading,
  revenueDays,
  setRevenueDays,
  onNavigate,
  onOpenNewBooking,
  onViewBooking,
}) {
  const { currencySymbol, settings } = useHotelSettings();
  const [hoveredPoint, setHoveredPoint] = useState(null);
  const [isBreakdownOpen, setIsBreakdownOpen] = useState(false);
  const [breakdownSearch, setBreakdownSearch] = useState('');
  const [breakdownPage, setBreakdownPage] = useState(1);
  const BREAKDOWN_ITEMS_PER_PAGE = 8;

  // ESC key and body scroll lock for breakdown modal
  useEffect(() => {
    if (!isBreakdownOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setIsBreakdownOpen(false);
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isBreakdownOpen]);

  if (loading && !dashboardData) {
    return (
      <div className="dashboard-loading">
        <div className="spinner-large" />
        <p>Loading real-time hotel operations data...</p>
      </div>
    );
  }

  const metrics = dashboardData?.metrics || {
    total_rooms: { value: 0, breakdown: '0 AC • 0 Normal' },
    occupied_rooms: { value: 0, occupancy_rate: 0, label: '0% Occupancy' },
    available_rooms: { value: 0, breakdown: '0 AC • 0 Normal' },
    today_check_ins: { value: 0, trend: '0' },
    today_check_outs: { value: 0, trend: '0' },
    today_revenue: { value: 0, formatted: '₹0', trend: '0%' },
  };

  const userRole = user?.role?.name ? user.role.name.toUpperCase() : '';
  const isHousekeeping = userRole === 'HOUSEKEEPING';
  const isAccountant = userRole === 'ACCOUNTANT';
  const canManageBookings = userRole === 'OWNER' || userRole === 'ADMIN' || userRole === 'RECEPTIONIST';
  const canAccessFinancials = userRole === 'OWNER' || userRole === 'ADMIN' || userRole === 'ACCOUNTANT';
  const canAccessRooms = userRole === 'OWNER' || userRole === 'ADMIN' || userRole === 'RECEPTIONIST' || userRole === 'HOUSEKEEPING';

  const revenueData = dashboardData?.revenue_overview?.data || [];
  const roomStatusCategories = dashboardData?.room_status || [];
  const recentBookings = dashboardData?.recent_bookings || [];
  const roomOverview = dashboardData?.room_overview || [];
  const hotelInfo = dashboardData?.hotel_info || {
    status: 'Hotel Open',
    check_in_time: '2:00 PM',
    check_out_time: '11:00 AM',
  };

  // Compute SVG chart coordinates dynamically from actual data
  const maxRevenue = Math.max(
    ...revenueData.map((d) => d.revenue),
    50000
  );
  const chartHeight = 160;
  const chartWidth = 520;
  const paddingX = 30;
  const paddingY = 20;

  const points = revenueData.map((item, index) => {
    const x = paddingX + (index / Math.max(revenueData.length - 1, 1)) * (chartWidth - paddingX * 2);
    const normalizedY = (item.revenue / maxRevenue) * (chartHeight - paddingY * 2);
    const y = chartHeight - paddingY - normalizedY;
    return { x, y, ...item };
  });

  const pathD = points.length > 0
    ? points.reduce((acc, curr, idx) => {
        return idx === 0 ? `M ${curr.x} ${curr.y}` : `${acc} L ${curr.x} ${curr.y}`;
      }, '')
    : '';

  const areaD = points.length > 0
    ? `${pathD} L ${points[points.length - 1].x} ${chartHeight - paddingY} L ${points[0].x} ${chartHeight - paddingY} Z`
    : '';

  // Peak or latest point for highlight badge
  const activeTooltipPoint = hoveredPoint || (points.length > 0 ? points[points.length - 1] : null);

  const totalRoomsCount = Number(metrics.total_rooms?.value) || 0;
  const availableRoomsCount = Number(metrics.available_rooms?.value) || 0;
  const occupiedRoomsCount = Number(metrics.occupied_rooms?.value) || 0;
  const availableRate = totalRoomsCount > 0 ? Math.round((availableRoomsCount / totalRoomsCount) * 100) : 0;
  const categoryCount = roomStatusCategories.length;

  return (
    <div className="dashboard-content">
      {/* Subheader Banner */}
      <div className="dashboard-subheader">
        <div className="welcome-section">
          <h1 className="welcome-title">{settings?.hotel_name || 'Hotel Dashboard'}</h1>
          <p className="welcome-subtitle">Welcome back, {user?.name || 'Manager'}!</p>
        </div>

        <div className="hotel-status-banner">
          <div className="status-badge open">
            <span className="pulsing-dot" />
            <span className="status-title">{hotelInfo.status}</span>
          </div>
          <span className="timings-text">
            Check-in: {hotelInfo.check_in_time} | Check-out: {hotelInfo.check_out_time}
          </span>
        </div>
      </div>

      {/* Row 1: 6 Metric KPI Cards */}
      <div className="kpi-grid">
        {/* Total Rooms */}
        <div className="kpi-card">
          <div className="kpi-icon-wrapper green-light">
            <RoomsIcon size={22} className="kpi-svg green" />
          </div>
          <div className="kpi-info">
            <span className="kpi-title">Total Rooms</span>
            <span className="kpi-value">{totalRoomsCount}</span>
            <span className="kpi-subtext">
              {categoryCount > 0
                ? `${categoryCount} ${categoryCount === 1 ? 'Category' : 'Categories'}`
                : 'All Inventory'}
            </span>
          </div>
        </div>

        {/* Occupied Rooms */}
        <div className="kpi-card highlight-green">
          <div className="kpi-icon-wrapper solid-green">
            <CheckCircleIcon size={22} className="kpi-svg white" />
          </div>
          <div className="kpi-info">
            <span className="kpi-title">Occupied Rooms ↗</span>
            <span className="kpi-value">{occupiedRoomsCount}</span>
            <span className="kpi-badge-green">
              {metrics.occupied_rooms?.label || `${metrics.occupied_rooms?.occupancy_rate ?? 0}% Occupancy`}
            </span>
          </div>
        </div>

        {/* Available Rooms */}
        <div className="kpi-card">
          <div className="kpi-icon-wrapper green-light">
            <RoomsIcon size={22} className="kpi-svg green" />
          </div>
          <div className="kpi-info">
            <span className="kpi-title">Available Rooms</span>
            <span className="kpi-value">{availableRoomsCount}</span>
            <span className="kpi-badge-green">{availableRate}% Available</span>
          </div>
        </div>

        {/* Today's Check-ins */}
        <div className="kpi-card">
          <div className="kpi-icon-wrapper green-light">
            <BookingsIcon size={22} className="kpi-svg green" />
          </div>
          <div className="kpi-info">
            <span className="kpi-title">Today's Check-ins</span>
            <span className="kpi-value">{metrics.today_check_ins?.value ?? 0}</span>
            <span className="kpi-trend green">↑ {metrics.today_check_ins?.trend || '+0'}</span>
          </div>
        </div>

        {/* Today's Check-outs */}
        <div className="kpi-card peach-tint">
          <div className="kpi-icon-wrapper peach">
            <BookingsIcon size={22} className="kpi-svg orange" />
          </div>
          <div className="kpi-info">
            <span className="kpi-title">Today's Check-outs</span>
            <span className="kpi-value">{metrics.today_check_outs?.value ?? 0}</span>
            <span className="kpi-trend orange">↑ {metrics.today_check_outs?.trend || '+0'}</span>
          </div>
        </div>

        {/* Today's Revenue or Housekeeping Task Metric */}
        {isHousekeeping ? (
          <div className="kpi-card peach-tint">
            <div className="kpi-icon-wrapper peach">
              <WrenchIcon size={22} className="kpi-svg orange" />
            </div>
            <div className="kpi-info">
              <span className="kpi-title">Cleaning & Tasks</span>
              <span className="kpi-value">
                {(roomStatusCategories.find((s) => s.status === 'CLEANING')?.count || 0) +
                  (roomStatusCategories.find((s) => s.status === 'MAINTENANCE')?.count || 0)}{' '}
                Rooms
              </span>
              <span className="kpi-subtext">Active turnover & repair tasks</span>
            </div>
          </div>
        ) : canAccessFinancials ? (
          <div className="kpi-card">
            <div className="kpi-icon-wrapper green-circle">
              <RupeeIcon size={22} className="kpi-svg green" />
            </div>
            <div className="kpi-info">
              <span className="kpi-title">Today's Revenue</span>
              <span className="kpi-value">
                {formatCurrency(metrics.today_revenue?.value || 0, currencySymbol)}
              </span>
              <span className="kpi-trend green">↑ {metrics.today_revenue?.trend || '+0%'}</span>
            </div>
          </div>
        ) : null}
      </div>

      {/* Row 2: Middle Section (Revenue Chart, Room Status Donut, Quick Actions) */}
      <div className="dashboard-middle-grid">
        {/* Left Column: Revenue Chart + Room Status */}
        <div className="chart-status-column">
          {/* Revenue Overview Card */}
          {canAccessFinancials && (
            <div className="card revenue-card">
              <div className="card-header-row">
                <div className="card-header-title">
                  <span className="header-icon">📈</span>
                  <h2>Revenue Overview</h2>
                </div>
                <div className="filter-pill-group">
                  {[7, 30, 90].map((d) => (
                    <button
                      key={d}
                      type="button"
                      onClick={() => setRevenueDays(d)}
                      className={`pill-btn ${revenueDays === d ? 'active' : ''}`}
                    >
                      {d}D
                    </button>
                  ))}
                </div>
              </div>

              <div className="chart-wrapper">
                <svg
                  viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                  className="revenue-svg"
                  preserveAspectRatio="none"
                >
                  <defs>
                    <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="var(--color-primary, #A18FD5)" stopOpacity="0.25" />
                      <stop offset="100%" stopColor="var(--color-primary, #A18FD5)" stopOpacity="0.01" />
                    </linearGradient>
                  </defs>

                  {/* Grid Lines */}
                  {[0.2, 0.4, 0.6, 0.8].map((ratio, i) => {
                    const y = paddingY + ratio * (chartHeight - paddingY * 2);
                    return (
                      <line
                        key={i}
                        x1={paddingX}
                        y1={y}
                        x2={chartWidth - paddingX}
                        y2={y}
                        stroke="var(--color-border, #F0E5F0)"
                        strokeDasharray="4 4"
                      />
                    );
                  })}

                  {/* Area Gradient */}
                  {areaD && <path d={areaD} fill="url(#chartFill)" />}

                  {/* Line Path */}
                  {pathD && (
                    <path
                      d={pathD}
                      fill="none"
                      stroke="var(--color-sidebar-active-text, #7257A8)"
                      strokeWidth="2.5"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  )}

                  {/* Points */}
                  {points.map((pt, i) => (
                    <circle
                      key={i}
                      cx={pt.x}
                      cy={pt.y}
                      r={hoveredPoint?.date === pt.date ? 5 : 3.5}
                      fill="#ffffff"
                      stroke="var(--color-sidebar-active-text, #7257A8)"
                      strokeWidth="2.5"
                      style={{ cursor: 'pointer' }}
                      onMouseEnter={() => setHoveredPoint(pt)}
                    />
                  ))}
                </svg>

                {/* Tooltip Badge */}
                {activeTooltipPoint && (
                  <div
                    className="chart-tooltip-badge"
                    style={{
                      left: `${(activeTooltipPoint.x / chartWidth) * 100}%`,
                      top: `${(activeTooltipPoint.y / chartHeight) * 100}%`,
                    }}
                  >
                    {formatCurrency(activeTooltipPoint.revenue || 0, currencySymbol)}
                  </div>
                )}

                {/* X Axis Labels */}
                <div className="chart-x-labels">
                  {revenueData.map((d, idx) => (
                    <span key={idx} className="axis-label">
                      {d.label}
                    </span>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Room Status Card */}
          <div className="card room-status-card">
            <div className="card-header-row">
              <div className="card-header-title">
                <RoomsIcon size={18} />
                <h2>Room Status</h2>
              </div>
              
            </div>

            {categoryCount === 0 ? (
              <div className="room-status-empty">
                <div className="empty-category-icon">🏨</div>
                <p className="empty-category-title">No categories configured</p>
                <p className="empty-category-sub">Add room categories in room settings.</p>
              </div>
            ) : (
              <div className="category-status-list">
                {roomStatusCategories.slice(0, 4).map((cat, idx) => {
                  const total = Number(cat.total) || 0;
                  const occupied = Number(cat.occupied) || 0;
                  const percentage =
                    total > 0
                      ? Math.round((occupied / total) * 100)
                      : Number(cat.percentage) || 0;

                  return (
                    <div key={cat.room_type_id || idx} className="category-status-row">
                      <div className="cat-row-header">
                        <span className="cat-name" title={cat.name}>
                          {cat.name}
                        </span>
                        <div className="cat-metrics-badge">
                          <span className="cat-count">
                            <strong className="cat-occupied-num">{occupied}</strong>/{total}
                          </span>
                          <span className="cat-pct">{percentage}%</span>
                        </div>
                      </div>
                      <div
                        className="cat-progress-track"
                        role="progressbar"
                        aria-valuenow={percentage}
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label={`${cat.name} occupancy`}
                      >
                        <div
                          className={`cat-progress-fill ${
                            percentage >= 85
                              ? 'pct-high'
                              : percentage >= 50
                              ? 'pct-med'
                              : 'pct-low'
                          }`}
                          style={{ width: `${Math.min(100, Math.max(0, percentage))}%` }}
                        />
                      </div>
                    </div>
                  );
                })}

                {categoryCount > 4 ? (
                  <div className="cat-preview-footer">
                    <span className="cat-preview-count">
                      Showing 4 of {categoryCount} categories
                    </span>
                    <button
                      type="button"
                      onClick={() => {
                        setBreakdownSearch('');
                        setBreakdownPage(1);
                        setIsBreakdownOpen(true);
                      }}
                      className="cat-inline-view-all"
                    >
                      View All ({categoryCount}) →
                    </button>
                  </div>
                ) : (
                  <div className="cat-preview-legend">
                    <span className="cat-legend-item">
                      <span className="cat-dot occupied" /> Occupied
                    </span>
                    <span className="cat-legend-item">
                      <span className="cat-dot available" /> Available
                    </span>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Right Column: Quick Actions Card */}
        <div className="quick-actions-column">
          <div className="card quick-actions-card">
            <div className="card-header-title">
              <span className="header-icon">⚡</span>
              <h2>Quick Actions</h2>
            </div>

            <div className="action-buttons-list">
              {canManageBookings && (
                <>
                  <button
                    type="button"
                    onClick={onOpenNewBooking}
                    className="action-btn primary-green"
                  >
                    <BookingsIcon size={18} />
                    <span>New Booking</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => onNavigate('checkin-checkout')}
                    className="action-btn secondary-btn"
                  >
                    <ArrowRightIcon size={16} />
                    <span>Check-In Guest</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => onNavigate('checkin-checkout')}
                    className="action-btn secondary-btn"
                  >
                    <ArrowRightIcon size={16} />
                    <span>Check-Out Guest</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => onNavigate('bookings')}
                    className="action-btn secondary-btn"
                  >
                    <FileTextIcon size={16} />
                    <span>View All Bookings</span>
                  </button>
                </>
              )}

              {canAccessRooms && (
                <button
                  type="button"
                  onClick={() => onNavigate('rooms')}
                  className={`action-btn ${isHousekeeping ? 'primary-green' : 'secondary-btn'}`}
                >
                  <RoomsIcon size={18} />
                  <span>{isHousekeeping ? 'Room Turnover & Cleaning' : 'Manage Rooms'}</span>
                </button>
              )}

              {isAccountant && (
                <>
                  <button
                    type="button"
                    onClick={() => onNavigate('billing')}
                    className="action-btn primary-green"
                  >
                    <RupeeIcon size={18} />
                    <span>Billing & Payments</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => onNavigate('reports')}
                    className="action-btn secondary-btn"
                  >
                    <FileTextIcon size={16} />
                    <span>Financial Reports</span>
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Row 3: Bottom Section (Recent Bookings + Room Overview) */}
      <div className="dashboard-bottom-grid">
        {/* Recent Bookings Table */}
        {canManageBookings && (
          <div className="card recent-bookings-card">
            <div className="card-header-row">
              <div className="card-header-title">
                <BookingsIcon size={18} />
                <h2>Recent Bookings</h2>
              </div>
              <button
                type="button"
                onClick={() => onNavigate('bookings')}
                className="view-all-link"
              >
                View All →
              </button>
            </div>

            <div className="table-responsive">
              <table className="dashboard-table">
                <thead>
                  <tr>
                    <th>Booking ID</th>
                    <th>Guest Name</th>
                    <th>Room</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {recentBookings.length === 0 ? (
                    <tr>
                      <td colSpan="7" className="empty-row">
                        No bookings recorded yet.
                      </td>
                    </tr>
                  ) : (
                    recentBookings.map((bk) => (
                      <tr key={bk.id}>
                        <td className="code-cell">{bk.booking_number}</td>
                        <td className="name-cell">{bk.guest_name}</td>
                        <td>{bk.rooms}</td>
                        <td>{formatDate(bk.check_in)}</td>
                        <td>{formatDate(bk.check_out)}</td>
                        <td>
                          <span
                            className={`status-pill ${
                              bk.status === 'CONFIRMED'
                                ? 'confirmed'
                                : bk.status === 'CHECKED_IN'
                                ? 'checked-in'
                                : bk.status === 'PENDING_PAYMENT'
                                ? 'pending'
                                : bk.status === 'CHECKED_OUT'
                                ? 'checked-out'
                                : 'cancelled'
                            }`}
                          >
                            {bk.status === 'CONFIRMED'
                              ? 'Confirmed'
                              : bk.status === 'CHECKED_IN'
                              ? 'Checked In'
                              : bk.status === 'PENDING_PAYMENT'
                              ? 'Pending'
                              : bk.status === 'CHECKED_OUT'
                              ? 'Checked Out'
                              : bk.status}
                          </span>
                        </td>
                        <td>
                          <button
                            type="button"
                            onClick={() => onViewBooking(bk.id)}
                            className="table-action-btn"
                          >
                            View
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Room Overview Grid */}
        <div className="card room-overview-card">
          <div className="card-header-row">
            <div className="card-header-title">
              <RoomsIcon size={18} />
              <h2>Room Overview</h2>
            </div>
           
          </div>

          {roomOverview.length === 0 ? (
            <p className="empty-sub">No rooms configured.</p>
          ) : (
            <div className="room-overview-grid">
              {roomOverview.map((rm) => {
                const isMaintenance = rm.status === 'Maintenance';
                const isOccupied = rm.status === 'Occupied';
                const isCleaning = rm.status === 'Cleaning';

                return (
                  <div
                    key={rm.id}
                    className={`room-chip-card ${
                      isMaintenance
                        ? 'maintenance'
                        : isCleaning
                        ? 'cleaning'
                        : isOccupied
                        ? 'occupied'
                        : 'available'
                    }`}
                    title={
                      rm.guest_info
                        ? `${rm.room_number}: ${rm.guest_info.guest_name} (${rm.guest_info.booking_number})`
                        : `${rm.room_number} - ${rm.status}`
                    }
                  >
                    <div className="chip-icon">
                      {isMaintenance ? (
                        <WrenchIcon size={16} className="text-amber-700" />
                      ) : (
                        <RoomsIcon size={16} />
                      )}
                    </div>
                    <div className="chip-number">{rm.room_number}</div>
                    <div className="chip-status">
                      <span className={`status-indicator-dot ${rm.status.toLowerCase()}`} />
                      <span>{rm.status}</span>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* Complete Room Category Occupancy Breakdown Modal */}
      {isBreakdownOpen && (
        <div
          className="modal-overlay"
          onClick={() => setIsBreakdownOpen(false)}
          role="dialog"
          aria-modal="true"
          aria-labelledby="breakdown-modal-title"
        >
          <div
            className="modal-card wide room-breakdown-modal"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <div className="modal-title-row">
                <RoomsIcon size={22} className="text-forest-green" />
                <div>
                  <h2 id="breakdown-modal-title">Room Category Occupancy Breakdown</h2>
                  <p className="modal-subtitle-text">
                    Live inventory and occupancy statistics across all {categoryCount} room {categoryCount === 1 ? 'category' : 'categories'}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsBreakdownOpen(false)}
                className="modal-close-btn"
                aria-label="Close modal"
              >
                <XIcon size={18} />
              </button>
            </div>

            <div className="breakdown-modal-body">
              {/* Summary Stat Chips */}
              <div className="breakdown-summary-grid">
                <div className="breakdown-summary-chip">
                  <span className="chip-label">Categories</span>
                  <span className="chip-val">{categoryCount}</span>
                </div>
                <div className="breakdown-summary-chip">
                  <span className="chip-label">Total Rooms</span>
                  <span className="chip-val">
                    {roomStatusCategories.reduce((s, c) => s + (Number(c.total) || 0), 0)}
                  </span>
                </div>
                <div className="breakdown-summary-chip">
                  <span className="chip-label">Occupied</span>
                  <span className="chip-val text-green">
                    {roomStatusCategories.reduce((s, c) => s + (Number(c.occupied) || 0), 0)}
                  </span>
                </div>
                <div className="breakdown-summary-chip">
                  <span className="chip-label">Available</span>
                  <span className="chip-val text-emerald">
                    {roomStatusCategories.reduce((s, c) => {
                      const t = Number(c.total) || 0;
                      const o = Number(c.occupied) || 0;
                      return s + Math.max(0, t - o);
                    }, 0)}
                  </span>
                </div>
                <div className="breakdown-summary-chip">
                  <span className="chip-label">Overall Rate</span>
                  <span className="chip-val text-forest">
                    {(() => {
                      const t = roomStatusCategories.reduce((s, c) => s + (Number(c.total) || 0), 0);
                      const o = roomStatusCategories.reduce((s, c) => s + (Number(c.occupied) || 0), 0);
                      return t > 0 ? Math.round((o / t) * 100) : 0;
                    })()}%
                  </span>
                </div>
              </div>

              {/* Search Bar for Quick Filtering */}
              {categoryCount > 4 && (
                <div className="breakdown-search-wrap">
                  <SearchIcon size={16} className="search-input-icon" />
                  <input
                    type="text"
                    placeholder="Search category by name..."
                    value={breakdownSearch}
                    onChange={(e) => {
                      setBreakdownSearch(e.target.value);
                      setBreakdownPage(1);
                    }}
                    className="breakdown-search-input"
                  />
                  {breakdownSearch && (
                    <button
                      type="button"
                      onClick={() => {
                        setBreakdownSearch('');
                        setBreakdownPage(1);
                      }}
                      className="search-clear-btn"
                      title="Clear search"
                    >
                      ✕
                    </button>
                  )}
                </div>
              )}

              {/* Controlled Scroll Area Table */}
              <div className="breakdown-table-container">
                <table className="breakdown-table">
                  <thead>
                    <tr>
                      <th>Room Category</th>
                      <th className="text-center">Total Rooms</th>
                      <th className="text-center">Occupied</th>
                      <th className="text-center">Available</th>
                      <th>Occupancy %</th>
                      <th className="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(() => {
                      const filtered = roomStatusCategories.filter((cat) =>
                        (cat.name || '').toLowerCase().includes(breakdownSearch.toLowerCase())
                      );

                      if (filtered.length === 0) {
                        return (
                          <tr>
                            <td colSpan="6" className="empty-breakdown-cell">
                              {breakdownSearch
                                ? `No category matches "${breakdownSearch}"`
                                : 'No room categories configured.'}
                            </td>
                          </tr>
                        );
                      }

                      const startIdx = (breakdownPage - 1) * BREAKDOWN_ITEMS_PER_PAGE;
                      const pageItems = filtered.slice(startIdx, startIdx + BREAKDOWN_ITEMS_PER_PAGE);

                      return pageItems.map((cat, idx) => {
                        const total = Number(cat.total) || 0;
                        const occupied = Number(cat.occupied) || 0;
                        const available = Number(cat.available ?? Math.max(0, total - occupied));
                        const pct = total > 0 ? Math.round((occupied / total) * 100) : (Number(cat.percentage) || 0);

                        return (
                          <tr key={cat.room_type_id || idx}>
                            <td className="cat-name-cell" title={cat.name}>
                              <div className="cat-name-flex">
                                <span className="cat-table-bullet" />
                                <span className="cat-table-name">{cat.name}</span>
                              </div>
                            </td>
                            <td className="text-center font-semibold">{total}</td>
                            <td className="text-center text-forest font-semibold">{occupied}</td>
                            <td className="text-center text-muted">{available}</td>
                            <td className="pct-cell">
                              <div className="table-pct-wrap">
                                <div className="table-progress-track">
                                  <div
                                    className="table-progress-fill"
                                    style={{ width: `${Math.min(100, Math.max(0, pct))}%` }}
                                  />
                                </div>
                                <span className="table-pct-label">{pct}%</span>
                              </div>
                            </td>
                            <td className="text-center">
                              <span
                                className={`cat-status-pill ${
                                  pct === 100
                                    ? 'full'
                                    : pct >= 75
                                    ? 'high'
                                    : pct >= 25
                                    ? 'moderate'
                                    : pct > 0
                                    ? 'low'
                                    : 'empty'
                                }`}
                              >
                                {pct === 100
                                  ? 'Full'
                                  : pct >= 75
                                  ? 'High'
                                  : pct >= 25
                                  ? 'Active'
                                  : pct > 0
                                  ? 'Low'
                                  : 'Vacant'}
                              </span>
                            </td>
                          </tr>
                        );
                      });
                    })()}
                  </tbody>
                </table>
              </div>

              {/* Pagination Controls */}
              {(() => {
                const filtered = roomStatusCategories.filter((cat) =>
                  (cat.name || '').toLowerCase().includes(breakdownSearch.toLowerCase())
                );
                const totalPages = Math.ceil(filtered.length / BREAKDOWN_ITEMS_PER_PAGE);
                if (totalPages <= 1) return null;

                return (
                  <div className="breakdown-pagination">
                    <span className="pagination-info">
                      Showing {(breakdownPage - 1) * BREAKDOWN_ITEMS_PER_PAGE + 1}–
                      {Math.min(breakdownPage * BREAKDOWN_ITEMS_PER_PAGE, filtered.length)} of{' '}
                      {filtered.length} categories
                    </span>
                    <div className="pagination-buttons">
                      <button
                        type="button"
                        disabled={breakdownPage === 1}
                        onClick={() => setBreakdownPage((p) => Math.max(1, p - 1))}
                        className="page-nav-btn"
                      >
                        ← Previous
                      </button>
                      <span className="page-indicator">
                        Page {breakdownPage} of {totalPages}
                      </span>
                      <button
                        type="button"
                        disabled={breakdownPage === totalPages}
                        onClick={() => setBreakdownPage((p) => Math.min(totalPages, p + 1))}
                        className="page-nav-btn"
                      >
                        Next →
                      </button>
                    </div>
                  </div>
                );
              })()}
            </div>

            <div className="breakdown-modal-footer">
              <button
                type="button"
                onClick={() => {
                  setIsBreakdownOpen(false);
                  onNavigate('rooms');
                }}
                className="btn-manage-rooms"
              >
                <RoomsIcon size={16} />
                <span>Manage Rooms & Categories</span>
              </button>
              <button
                type="button"
                onClick={() => setIsBreakdownOpen(false)}
                className="btn-close-breakdown"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

