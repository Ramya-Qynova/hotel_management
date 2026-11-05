import React, { useState, useEffect, useCallback } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDate, formatCurrency } from '../utils/formatters';
import {
  RupeeIcon,
  BookingsIcon,
  RoomsIcon,
  BillingIcon,
  DownloadIcon,
  PrintIcon,
  RefreshIcon,
  CheckCircleIcon,
  XIcon,
  CalendarIcon,
  SparklesIcon,
  TrendingUpIcon,
} from './Icons';

export default function ReportsView({ user }) {
  const { currencySymbol } = useHotelSettings();

  const isFinancialRole = user?.role?.name === 'OWNER' || user?.role?.name === 'ADMIN' || user?.role?.name === 'ACCOUNTANT';

  // Date range state
  const [rangeType, setRangeType] = useState('30d');
  const [fromDate, setFromDate] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - 29);
    return d.toISOString().split('T')[0];
  });
  const [toDate, setToDate] = useState(() => new Date().toISOString().split('T')[0]);

  // Active Report Tab - default to occupancy for non-financial staff to prevent blank screens
  const [reportTab, setReportTab] = useState(isFinancialRole ? 'overview' : 'occupancy');

  // Loading & Data State
  const [loading, setLoading] = useState(true);
  const [summaryData, setSummaryData] = useState(null);
  const [occupancyData, setOccupancyData] = useState(null);
  const [bookingData, setBookingData] = useState(null);
  const [paymentData, setPaymentData] = useState(null);
  const [serviceData, setServiceData] = useState(null);
  const [expenseData, setExpenseData] = useState(null);
  const [cashRegisterData, setCashRegisterData] = useState(null);

  // Fetch report data
  const fetchReports = useCallback(async () => {
    setLoading(true);
    const params = {
      range_type: rangeType,
      from_date: fromDate,
      to_date: toDate,
    };

    try {
      // Fetch operational reports
      const [occ, bkg, svc] = await Promise.all([
        hotelApi.getReportOccupancy(params),
        hotelApi.getReportBookings(params),
        hotelApi.getReportServices(params),
      ]);
      setOccupancyData(occ);
      setBookingData(bkg);
      setServiceData(svc);

      // Fetch financial reports if authorized
      if (isFinancialRole) {
        const [sum, pay, exp, cr] = await Promise.all([
          hotelApi.getReportSummary(params),
          hotelApi.getReportPayments(params),
          hotelApi.getReportExpenses(params),
          hotelApi.getReportCashRegister(params),
        ]);
        setSummaryData(sum);
        setPaymentData(pay);
        setExpenseData(exp);
        setCashRegisterData(cr);
      }
    } catch (err) {
      console.error('Failed to load reports:', err);
    } finally {
      setLoading(false);
    }
  }, [rangeType, fromDate, toDate, isFinancialRole]);

  useEffect(() => {
    fetchReports();
  }, [fetchReports]);

  useEffect(() => {
    if (!isFinancialRole && (reportTab === 'overview' || reportTab === 'payments' || reportTab === 'services_expenses')) {
      setReportTab('occupancy');
    }
  }, [isFinancialRole, reportTab]);

  // Export CSV handler
  const handleExportCsv = (type = 'summary') => {
    hotelApi.downloadReportCsv(type, {
      range_type: rangeType,
      from_date: fromDate,
      to_date: toDate,
    });
  };

  // SVG Trend calculations for summary daily trend
  const dailyTrend = summaryData?.daily_trend || [];
  const maxCollection = Math.max(...dailyTrend.map((d) => Math.max(d.collections, d.expenses)), 25000);
  const chartHeight = 160;
  const chartWidth = 600;
  const paddingX = 40;
  const paddingY = 25;

  const collectionPoints = dailyTrend.map((d, i) => {
    const x = paddingX + (i / Math.max(dailyTrend.length - 1, 1)) * (chartWidth - paddingX * 2);
    const y = chartHeight - paddingY - (d.collections / maxCollection) * (chartHeight - paddingY * 2);
    return { x, y, ...d };
  });

  const expensePoints = dailyTrend.map((d, i) => {
    const x = paddingX + (i / Math.max(dailyTrend.length - 1, 1)) * (chartWidth - paddingX * 2);
    const y = chartHeight - paddingY - (d.expenses / maxCollection) * (chartHeight - paddingY * 2);
    return { x, y, ...d };
  });

  const collectionPath = collectionPoints.reduce((acc, p, i) => (i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`), '');
  const expensePath = expensePoints.reduce((acc, p, i) => (i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`), '');

  return (
    <div className="reports-container view-container">
      {/* Page Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Hotel Performance & Financial Reports</h1>
          <p className="view-subtitle">
            Audited financial statements, room occupancy analytics, collections reconciliation, and operating expense ledger.
          </p>
        </div>
        <div className="view-header-actions">
          <button
            type="button"
            onClick={() => handleExportCsv(reportTab === 'overview' ? 'summary' : reportTab)}
            className="btn btn-outline-green"
          >
            <DownloadIcon size={16} />
            <span>Export CSV</span>
          </button>
          <button
            type="button"
            onClick={() => window.print()}
            className="btn btn-secondary print-hide"
          >
            <PrintIcon size={16} />
            <span>Print Report</span>
          </button>
          <button
            type="button"
            onClick={fetchReports}
            className="btn-icon-secondary"
            title="Reload report data"
          >
            <RefreshIcon size={16} />
          </button>
        </div>
      </div>

      {/* Date Range Filter Bar */}
      <div className="filter-bar">
        <div className="filter-pill-group">
          {[
            { id: 'today', label: 'Today' },
            { id: '7d', label: 'Last 7 Days' },
            { id: 'this_month', label: 'This Month' },
            { id: '30d', label: 'Last 30 Days' },
            { id: 'custom', label: 'Custom Range' },
          ].map((pill) => (
            <button
              key={pill.id}
              type="button"
              onClick={() => setRangeType(pill.id)}
              className={`filter-pill ${rangeType === pill.id ? 'active' : ''}`}
            >
              <span>{pill.label}</span>
            </button>
          ))}
        </div>

        {/* Custom Date Pickers */}
        {rangeType === 'custom' && (
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', flexWrap: 'wrap' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.35rem', fontSize: '0.82rem', fontWeight: 600 }}>
              <span>From:</span>
              <input
                type="date"
                value={fromDate}
                onChange={(e) => setFromDate(e.target.value)}
                style={{ padding: '0.35rem 0.65rem' }}
              />
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.35rem', fontSize: '0.82rem', fontWeight: 600 }}>
              <span>To:</span>
              <input
                type="date"
                value={toDate}
                onChange={(e) => setToDate(e.target.value)}
                style={{ padding: '0.35rem 0.65rem' }}
              />
            </label>
            <button type="button" onClick={fetchReports} className="btn btn-primary btn-sm">
              Apply
            </button>
          </div>
        )}

        <div className="period-badge-wrapper" style={{ marginLeft: 'auto' }}>
          <span className="balance-badge settled" style={{ fontSize: '0.8rem' }}>
            Period: <strong>{summaryData?.period?.label || occupancyData?.period || 'Selected Range'}</strong>
          </span>
        </div>
      </div>

      {/* Section Sub-Navigation Tabs */}
      <div className="sub-tabs-bar">
        {isFinancialRole && (
          <button
            type="button"
            onClick={() => setReportTab('overview')}
            className={`sub-tab-btn ${reportTab === 'overview' ? 'active' : ''}`}
          >
            <TrendingUpIcon size={16} />
            <span>Financial Summary & Profit</span>
          </button>
        )}
        <button
          type="button"
          onClick={() => setReportTab('occupancy')}
          className={`sub-tab-btn ${reportTab === 'occupancy' ? 'active' : ''}`}
        >
          <RoomsIcon size={16} />
          <span>Room Occupancy & ADR</span>
        </button>
        <button
          type="button"
          onClick={() => setReportTab('bookings')}
          className={`sub-tab-btn ${reportTab === 'bookings' ? 'active' : ''}`}
        >
          <BookingsIcon size={16} />
          <span>Booking Statistics</span>
        </button>
        {isFinancialRole && (
          <>
            <button
              type="button"
              onClick={() => setReportTab('payments')}
              className={`sub-tab-btn ${reportTab === 'payments' ? 'active' : ''}`}
            >
              <RupeeIcon size={16} />
              <span>Payment Collections by Method</span>
            </button>
            <button
              type="button"
              onClick={() => setReportTab('services_expenses')}
              className={`sub-tab-btn ${reportTab === 'services_expenses' ? 'active' : ''}`}
            >
              <SparklesIcon size={16} />
              <span>Services & Operating Expenses</span>
            </button>
            <button
              type="button"
              onClick={() => setReportTab('cash_register')}
              className={`sub-tab-btn ${reportTab === 'cash_register' ? 'active' : ''}`}
            >
              <BillingIcon size={16} />
              <span>Cash Register Audits</span>
            </button>
          </>
        )}
      </div>

      {loading ? (
        <div className="dashboard-loading py-8" style={{ minHeight: '300px' }}>
          <div className="spinner-large" style={{ margin: '0 auto 1rem auto' }} />
          <p className="text-muted">Compiling audited hotel reports...</p>
        </div>
      ) : (
        <div className="report-content-body">
          {/* TAB 1: EXECUTIVE FINANCIAL SUMMARY & PROFIT */}
          {reportTab === 'overview' && summaryData && (
            <div className="tab-pane-content">
              {/* Financial KPI Cards Grid */}
              <div className="kpi-grid mb-6">
                <div className="kpi-card">
                  <div className="kpi-icon-wrapper green-light">
                    <RupeeIcon size={20} className="kpi-svg green" />
                  </div>
                  <div className="kpi-info">
                    <span className="kpi-title">Total Booking Value</span>
                    <span className="kpi-value">{formatCurrency(summaryData.financials?.total_booking_value, currencySymbol)}</span>
                    <span className="kpi-breakdown">Gross reservations in period</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-icon-wrapper green-light">
                    <RoomsIcon size={20} className="kpi-svg green" />
                  </div>
                  <div className="kpi-info">
                    <span className="kpi-title">Room Tariff Revenue</span>
                    <span className="kpi-value">{formatCurrency(summaryData.financials?.room_revenue, currencySymbol)}</span>
                    <span className="kpi-breakdown">+{formatCurrency(summaryData.financials?.tax_collected, currencySymbol)} Tax/GST</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-icon-wrapper green-light">
                    <BillingIcon size={20} className="kpi-svg green" />
                  </div>
                  <div className="kpi-info">
                    <span className="kpi-title">Service Revenue</span>
                    <span className="kpi-value">{formatCurrency(summaryData.financials?.service_revenue, currencySymbol)}</span>
                    <span className="kpi-breakdown">Meals, laundry, add-ons</span>
                  </div>
                </div>

                <div className="kpi-card highlight-green">
                  <div className="kpi-icon-wrapper solid-green">
                    <CheckCircleIcon size={20} className="kpi-svg white" />
                  </div>
                  <div className="kpi-info">
                    <span className="kpi-title">Net Collections</span>
                    <span className="kpi-value">{formatCurrency(summaryData.financials?.net_collections, currencySymbol)}</span>
                    <span className="kpi-badge-green">Gross: {formatCurrency(summaryData.financials?.amount_collected, currencySymbol)}</span>
                  </div>
                </div>

                <div className="kpi-card peach-tint">
                  <div className="kpi-icon-wrapper peach">
                    <XIcon size={20} className="kpi-svg orange" />
                  </div>
                  <div className="kpi-info">
                    <span className="kpi-title">Refunds Paid</span>
                    <span className="kpi-value text-danger">{formatCurrency(summaryData.financials?.refunds, currencySymbol)}</span>
                    <span className="kpi-breakdown">Customer returns/cancels</span>
                  </div>
                </div>

                <div className="kpi-card peach-tint">
                  <div className="kpi-icon-wrapper peach">
                    <RupeeIcon size={20} className="kpi-svg orange" />
                  </div>
                  <div className="kpi-info">
                    <span className="kpi-title">Operating Expenses</span>
                    <span className="kpi-value text-danger">{formatCurrency(summaryData.financials?.expenses, currencySymbol)}</span>
                    <span className="kpi-breakdown">Utilities, maintenance, staff</span>
                  </div>
                </div>
              </div>

              {/* Profitability Calculation Basis Comparison */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                {/* Cash Basis Card */}
                <div className="card profit-card border-l-4 border-emerald-600">
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-semibold text-lg text-emerald-950">Cash Basis Net Profit</h3>
                    <span className="badge-pill bg-emerald-100 text-emerald-800">Actual Cash Realized</span>
                  </div>
                  <div className="profit-value-row my-3">
                    <span className="text-3xl font-bold text-emerald-700">
                      {formatCurrency(summaryData.financials?.cash_profit, currencySymbol)}
                    </span>
                  </div>
                  <div className="profit-formula bg-emerald-50 p-3 rounded text-sm text-emerald-950 font-mono mb-2">
                    {summaryData.profit_explanation?.cash_basis?.formula}
                  </div>
                  <p className="text-xs text-muted">
                    {summaryData.profit_explanation?.cash_basis?.description}
                  </p>
                </div>

                {/* Accrual Basis Card */}
                <div className="card profit-card border-l-4 border-teal-600">
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-semibold text-lg text-teal-900">Accrual Basis Net Profit</h3>
                    <span className="badge-pill bg-teal-100 text-teal-800">Earned Accounting Profit</span>
                  </div>
                  <div className="profit-value-row my-3">
                    <span className="text-3xl font-bold text-teal-700">
                      {formatCurrency(summaryData.financials?.accrual_profit, currencySymbol)}
                    </span>
                  </div>
                  <div className="profit-formula bg-teal-50 p-3 rounded text-sm text-teal-950 font-mono mb-2">
                    {summaryData.profit_explanation?.accrual_basis?.formula}
                  </div>
                  <p className="text-xs text-muted">
                    {summaryData.profit_explanation?.accrual_basis?.description}
                  </p>
                </div>
              </div>

              {/* Collections vs Expenses Daily Trend Chart */}
              <div className="card mb-6">
                <div className="card-header-row mb-4" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div>
                    <h3 className="card-section-title" style={{ margin: 0, border: 'none' }}>
                      Collections vs Expenses Trend
                    </h3>
                    <p className="text-xs text-muted">Daily cash receipts vs expense outlays across the period</p>
                  </div>
                  <div className="flex items-center gap-4 text-xs font-medium">
                    <span className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-full bg-emerald-600 inline-block" /> Collections
                    </span>
                    <span className="flex items-center gap-1.5">
                      <span className="w-3 h-3 rounded-full bg-rose-500 inline-block" /> Expenses
                    </span>
                  </div>
                </div>

                <div className="chart-wrapper">
                  <svg viewBox={`0 0 ${chartWidth} ${chartHeight}`} className="revenue-svg">
                    {/* Grid Lines */}
                    {[0.25, 0.5, 0.75].map((r, i) => (
                      <line
                        key={i}
                        x1={paddingX}
                        y1={paddingY + r * (chartHeight - paddingY * 2)}
                        x2={chartWidth - paddingX}
                        y2={paddingY + r * (chartHeight - paddingY * 2)}
                        stroke="var(--color-border, #F0E5F0)"
                        strokeDasharray="4 4"
                      />
                    ))}
                    {/* Collections Path */}
                    {collectionPath && (
                      <path
                        d={collectionPath}
                        fill="none"
                        stroke="var(--color-primary, #A18FD5)"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                      />
                    )}
                    {/* Expense Path */}
                    {expensePath && (
                      <path
                        d={expensePath}
                        fill="none"
                        stroke="var(--color-danger, #EDBBC5)"
                        strokeWidth="2"
                        strokeDasharray="5 3"
                        strokeLinecap="round"
                      />
                    )}
                  </svg>
                </div>
              </div>
            </div>
          )}

          {/* TAB 2: ROOM OCCUPANCY & ADR */}
          {reportTab === 'occupancy' && occupancyData && (
            <div className="tab-pane-content">
              {/* Occupancy Metrics Grid */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Average Occupancy</span>
                    <span className="kpi-value text-emerald-800">{occupancyData.occupancy_rate_percent}%</span>
                    <span className="kpi-breakdown">
                      {occupancyData.total_nights_occupied} of {occupancyData.total_nights_available} nights
                    </span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Average Daily Rate (ADR)</span>
                    <span className="kpi-value">{formatCurrency(occupancyData.average_daily_rate, currencySymbol)}</span>
                    <span className="kpi-breakdown">Room revenue per booked night</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">RevPAR</span>
                    <span className="kpi-value">{formatCurrency(occupancyData.revenue_per_available_room, currencySymbol)}</span>
                    <span className="kpi-breakdown">Revenue per available room</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Active Rooms</span>
                    <span className="kpi-value">{occupancyData.total_rooms}</span>
                    <span className="kpi-breakdown">Over {occupancyData.days} days in range</span>
                  </div>
                </div>
              </div>

              {/* Room Category Performance Table */}
              <div className="card">
                <div className="card-header-row mb-4" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <h3 className="card-section-title" style={{ margin: 0, border: 'none' }}>
                    Room Category Breakdown
                  </h3>
                  <button
                    type="button"
                    onClick={() => handleExportCsv('occupancy')}
                    className="btn btn-secondary btn-xs"
                  >
                    <DownloadIcon size={13} />
                    <span>Export Category CSV</span>
                  </button>
                </div>
                <div className="table-responsive">
                  <table className="dashboard-table">
                    <thead>
                      <tr>
                        <th>Category</th>
                        <th className="text-right">Inventory</th>
                        <th className="text-right">Nights Available</th>
                        <th className="text-right">Nights Booked</th>
                        <th className="text-right">Occupancy %</th>
                        <th className="text-right">Revenue</th>
                        <th className="text-right">ADR</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(occupancyData.room_categories || []).map((cat) => (
                        <tr key={cat.room_type_id}>
                          <td className="bold" style={{ color: 'var(--color-heading)' }}>{cat.name}</td>
                          <td className="text-right">{cat.total_rooms}</td>
                          <td className="text-right">{cat.nights_available}</td>
                          <td className="text-right font-medium">{cat.nights_occupied}</td>
                          <td className="text-right">
                            <span className="font-semibold text-forest-green">{cat.occupancy_rate}%</span>
                          </td>
                          <td className="text-right font-medium">{formatCurrency(cat.revenue, currencySymbol)}</td>
                          <td className="text-right">{formatCurrency(cat.adr, currencySymbol)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* TAB 3: BOOKINGS */}
          {reportTab === 'bookings' && bookingData && (
            <div className="tab-pane-content">
              {/* Distribution Grid */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Total Bookings</span>
                    <span className="kpi-value">{bookingData.total_bookings}</span>
                    <span className="kpi-breakdown">Reservations in period</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Cancellation Rate</span>
                    <span className="kpi-value text-danger">{bookingData.cancellation_rate}%</span>
                    <span className="kpi-breakdown">
                      {bookingData.status_distribution?.CANCELLED || 0} cancellations
                    </span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Avg Booking Value</span>
                    <span className="kpi-value">{formatCurrency(bookingData.average_booking_value, currencySymbol)}</span>
                    <span className="kpi-breakdown">Per non-cancelled reservation</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Avg Length of Stay</span>
                    <span className="kpi-value">{bookingData.average_stay_length_nights} Nights</span>
                    <span className="kpi-breakdown">Average room duration</span>
                  </div>
                </div>
              </div>

              {/* Status Breakdown List */}
              <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                {Object.entries(bookingData.status_distribution || {}).map(([st, cnt]) => (
                  <div key={st} className="status-metric-box p-3 bg-white border rounded text-center">
                    <span className="text-xs uppercase text-muted block">{st.replace('_', ' ')}</span>
                    <span className="text-xl font-bold text-emerald-950 mt-1 block">{cnt}</span>
                  </div>
                ))}
              </div>

              {/* Bookings Table */}
              <div className="card">
                <div className="card-header-row mb-4" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <h3 className="card-section-title" style={{ margin: 0, border: 'none' }}>
                    Bookings Audit List
                  </h3>
                  <button
                    type="button"
                    onClick={() => handleExportCsv('bookings')}
                    className="btn btn-secondary btn-xs"
                  >
                    <DownloadIcon size={13} />
                    <span>Export Bookings CSV</span>
                  </button>
                </div>
                <div className="table-responsive">
                  <table className="dashboard-table">
                    <thead>
                      <tr>
                        <th>Booking #</th>
                        <th>Guest Name</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                        <th className="text-right">Grand Total</th>
                        <th>Created</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(bookingData.bookings || []).length === 0 ? (
                        <tr>
                          <td colSpan="7" className="text-center py-6 text-muted">
                            No reservations found for the selected date range.
                          </td>
                        </tr>
                      ) : (
                        bookingData.bookings.map((b) => (
                          <tr key={b.id}>
                            <td className="code bold" style={{ color: 'var(--color-sidebar-active-text)' }}>{b.booking_number}</td>
                            <td className="font-semibold">{b.guest_name}</td>
                            <td style={{ whiteSpace: 'nowrap' }}>{formatDate(b.check_in)}</td>
                            <td style={{ whiteSpace: 'nowrap' }}>{formatDate(b.check_out)}</td>
                            <td>
                              <span className={`status-pill ${b.status.toLowerCase()}`}>
                                {b.status}
                              </span>
                            </td>
                            <td className="text-right bold">{formatCurrency(b.grand_total, currencySymbol)}</td>
                            <td className="text-muted text-xs" style={{ whiteSpace: 'nowrap' }}>{formatDate(b.created_at, true)}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* TAB 4: COLLECTIONS & PAYMENTS */}
          {reportTab === 'payments' && paymentData && (
            <div className="tab-pane-content">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div className="card">
                  <h3 className="card-section-title">Collections by Payment Method</h3>
                  <div className="table-responsive">
                    <table className="dashboard-table">
                      <thead>
                        <tr>
                          <th>Payment Method</th>
                          <th className="text-right">Transactions</th>
                          <th className="text-right">Collected</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(paymentData.payment_methods || []).map((pm) => (
                          <tr key={pm.method}>
                            <td className="font-semibold">{pm.method}</td>
                            <td className="text-right">{pm.count}</td>
                            <td className="text-right bold text-forest-green">
                              {formatCurrency(pm.amount, currencySymbol)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="card">
                  <h3 className="card-section-title">Payments by Gateway / Processing Type</h3>
                  <div className="table-responsive">
                    <table className="dashboard-table">
                      <thead>
                        <tr>
                          <th>Processing Gateway</th>
                          <th className="text-right">Count</th>
                          <th className="text-right">Net Value</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(paymentData.gateways || []).map((gw) => (
                          <tr key={gw.gateway}>
                            <td className="font-semibold">{gw.gateway || 'Manual Direct'}</td>
                            <td className="text-right">{gw.count}</td>
                            <td className="text-right bold text-forest-green">
                              {formatCurrency(gw.amount, currencySymbol)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* TAB 5: SERVICES & EXPENSES */}
          {reportTab === 'services_expenses' && (
            <div className="tab-pane-content">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Services Revenue Card */}
                <div className="card">
                  <h3 className="card-section-title">Service Revenues by Category</h3>
                  <div className="table-responsive">
                    <table className="dashboard-table">
                      <thead>
                        <tr>
                          <th>Category</th>
                          <th className="text-right">Orders</th>
                          <th className="text-right">Gross Revenue</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(serviceData?.by_category || []).length === 0 ? (
                          <tr>
                            <td colSpan="3" className="text-center py-4 text-muted">
                              No service orders recorded in this period.
                            </td>
                          </tr>
                        ) : (
                          serviceData.by_category.map((sc) => (
                            <tr key={sc.category}>
                              <td className="font-semibold">{sc.category.replace('_', ' ')}</td>
                              <td className="text-right">{sc.count}</td>
                              <td className="text-right bold text-forest-green">
                                {formatCurrency(sc.total_amount, currencySymbol)}
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Operating Expenses Card */}
                <div className="card">
                  <h3 className="card-section-title">Operating Expenses by Category</h3>
                  <div className="table-responsive">
                    <table className="dashboard-table">
                      <thead>
                        <tr>
                          <th>Expense Category</th>
                          <th className="text-right">Entries</th>
                          <th className="text-right">Total Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(expenseData?.by_category || []).length === 0 ? (
                          <tr>
                            <td colSpan="3" className="text-center py-4 text-muted">
                              No operating expenses recorded in this period.
                            </td>
                          </tr>
                        ) : (
                          expenseData.by_category.map((ec) => (
                            <tr key={ec.category_id}>
                              <td className="font-semibold">{ec.name}</td>
                              <td className="text-right">{ec.count}</td>
                              <td className="text-right bold text-danger">
                                {formatCurrency(ec.total_amount, currencySymbol)}
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* TAB 6: CASH REGISTER RECONCILIATION */}
          {reportTab === 'cash_register' && cashRegisterData && (
            <div className="tab-pane-content">
              {/* Cash Register Summary Cards */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Total Cash Inflows</span>
                    <span className="kpi-value text-forest-green">{formatCurrency(cashRegisterData.total_cash_in, currencySymbol)}</span>
                    <span className="kpi-breakdown">Cash collected at front desk</span>
                  </div>
                </div>

                <div className="kpi-card">
                  <div className="kpi-info">
                    <span className="kpi-title">Total Cash Outflows</span>
                    <span className="kpi-value text-danger">{formatCurrency(cashRegisterData.total_cash_out, currencySymbol)}</span>
                    <span className="kpi-breakdown">Cash refunds & disbursements</span>
                  </div>
                </div>

                <div className="kpi-card highlight-green">
                  <div className="kpi-info">
                    <span className="kpi-title">Net Register Flow</span>
                    <span className="kpi-value">{formatCurrency(cashRegisterData.net_cash_flow, currencySymbol)}</span>
                    <span className="kpi-badge-green">Entries: {cashRegisterData.transactions_count}</span>
                  </div>
                </div>
              </div>

              {/* Transactions Ledger */}
              <div className="card">
                <div className="card-header-row mb-4">
                  <h3 className="card-section-title" style={{ margin: 0, border: 'none' }}>
                    Cash Drawer Audit Trail
                  </h3>
                </div>
                <div className="table-responsive">
                  <table className="dashboard-table">
                    <thead>
                      <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th className="text-right">Amount</th>
                        <th className="text-right">Balance After</th>
                        <th>Performed By</th>
                        <th>Notes</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(cashRegisterData.transactions || []).length === 0 ? (
                        <tr>
                          <td colSpan="7" className="text-center py-4 text-muted">
                            No cash drawer movements recorded in this period.
                          </td>
                        </tr>
                      ) : (
                        cashRegisterData.transactions.map((tx) => (
                          <tr key={tx.id}>
                            <td className="text-xs" style={{ whiteSpace: 'nowrap' }}>
                              {formatDate(tx.created_at || tx.date, true)}
                            </td>
                            <td>
                              <span
                                className={`status-pill ${
                                  tx.type === 'CASH_IN' ? 'confirmed' : 'cancelled'
                                }`}
                              >
                                {tx.type}
                              </span>
                            </td>
                            <td className="text-xs font-mono">{tx.category}</td>
                            <td
                              className={`text-right font-semibold ${
                                tx.type === 'CASH_IN' ? 'text-forest-green' : 'text-danger'
                              }`}
                            >
                              {tx.type === 'CASH_IN' ? '+' : '-'}
                              {formatCurrency(tx.amount, currencySymbol)}
                            </td>
                            <td className="text-right font-mono font-medium">
                              {formatCurrency(tx.balance_after, currencySymbol)}
                            </td>
                            <td>{tx.performed_by}</td>
                            <td className="text-xs text-muted">{tx.notes || '—'}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {/* Meaningful Empty State when genuinely no data exists for selected tab */}
          {((reportTab === 'overview' && !summaryData) ||
            (reportTab === 'occupancy' && !occupancyData) ||
            (reportTab === 'bookings' && !bookingData) ||
            (reportTab === 'payments' && !paymentData) ||
            (reportTab === 'cash_register' && !cashRegisterData)) && (
            <div className="empty-state-card">
              <div className="empty-state-icon-wrapper">
                <CalendarIcon size={32} />
              </div>
              <h3 className="font-bold text-lg text-emerald-950">No Data Available in Selected Period</h3>
              <p className="text-muted" style={{ maxWidth: '440px', margin: '0 auto' }}>
                There are no recorded transactions or reservations for the period between {formatDate(fromDate)} and {formatDate(toDate)}.
                Try selecting "This Month" or "Last 30 Days" above.
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
