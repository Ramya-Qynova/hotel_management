import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { formatDate, formatDateRange } from '../utils/formatters';
import {
  CheckCircleIcon,
  RefreshIcon,
  XIcon,
  CheckIcon,
  ClockIcon,
  EyeIcon,
  BedIcon,
} from './Icons';

export default function CheckInOutView({ onViewBooking }) {
  const [activeTab, setActiveTab] = useState('arrivals'); // 'arrivals' | 'departures' | 'cleaning'
  const [arrivals, setArrivals] = useState([]);
  const [departures, setDepartures] = useState([]);
  const [cleaningRooms, setCleaningRooms] = useState([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [feedbackMsg, setFeedbackMsg] = useState({ type: '', text: '' });

  // Late checkout modal state
  const [lateModalBooking, setLateModalBooking] = useState(null);
  const [lateTime, setLateTime] = useState('');

  // ESC key and body scroll lock for modal
  useEffect(() => {
    if (!lateModalBooking) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') setLateModalBooking(null);
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [lateModalBooking]);

  const loadData = () => {
    setLoading(true);
    setFeedbackMsg({ type: '', text: '' });

    Promise.all([
      hotelApi.getTodayArrivals(),
      hotelApi.getTodayDepartures(),
      hotelApi.getRooms({ operational_status: 'CLEANING' }),
    ])
      .then(([arr, dep, clean]) => {
        setArrivals(arr || []);
        setDepartures(dep || []);
        setCleaningRooms(clean || []);
      })
      .catch((err) => {
        setFeedbackMsg({
          type: 'error',
          text: err.response?.data?.message || 'Failed to load check-in/out records',
        });
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

  // Perform Check In
  const handleCheckIn = async (bookingId) => {
    setActionLoading(true);
    try {
      await hotelApi.checkIn(bookingId);
      setFeedbackMsg({ type: 'success', text: 'Guest checked in successfully!' });
      loadData();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Check-in failed. Ensure room is READY.',
      });
    } finally {
      setActionLoading(false);
    }
  };

  // Perform Standard Check Out
  const handleCheckOut = async (bookingId, checkoutTime = null) => {
    setActionLoading(true);
    try {
      await hotelApi.checkOut(bookingId, checkoutTime);
      setFeedbackMsg({
        type: 'success',
        text: 'Guest checked out successfully! Rooms moved to CLEANING status.',
      });
      setLateModalBooking(null);
      loadData();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Check-out failed.',
      });
    } finally {
      setActionLoading(false);
    }
  };

  // Housekeeping mark cleaned
  const handleMarkCleaned = async (roomId, roomNumber) => {
    setActionLoading(true);
    try {
      await hotelApi.markRoomCleaned(roomId);
      setFeedbackMsg({
        type: 'success',
        text: `Room ${roomNumber} has been verified clean and returned to READY status!`,
      });
      loadData();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update cleaning status.',
      });
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <div className="view-container">
      {/* Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Front Desk Check-In & Check-Out</h1>
          <p className="view-subtitle">
            Manage daily arrivals, guest check-outs, room turnover cleaning transitions and front desk key handover.
          </p>
        </div>

        <button
          type="button"
          onClick={loadData}
          disabled={loading || actionLoading}
          className="btn btn-secondary btn-sm"
        >
          <RefreshIcon size={15} />
          <span>{loading ? 'Refreshing...' : 'Refresh Status'}</span>
        </button>
      </div>

      {feedbackMsg.text && (
        <div className={`alert-box ${feedbackMsg.type} mb-4`}>
          {feedbackMsg.type === 'success' ? <CheckCircleIcon size={18} /> : <XIcon size={18} />}
          <span>{feedbackMsg.text}</span>
        </div>
      )}

      {/* Tabs Bar */}
      <div className="sub-tabs-bar">
        <button
          type="button"
          onClick={() => setActiveTab('arrivals')}
          className={`sub-tab-btn ${activeTab === 'arrivals' ? 'active' : ''}`}
        >
          <span>Today's Expected Arrivals ({arrivals.length})</span>
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('departures')}
          className={`sub-tab-btn ${activeTab === 'departures' ? 'active' : ''}`}
        >
          <span>In-House Guests / Departures ({departures.length})</span>
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('cleaning')}
          className={`sub-tab-btn ${activeTab === 'cleaning' ? 'active' : ''}`}
        >
          <BedIcon size={15} />
          <span>Housekeeping Cleaning Queue ({cleaningRooms.length})</span>
        </button>
      </div>

      {/* TAB 1: ARRIVALS */}
      {activeTab === 'arrivals' && (
        <div className="table-responsive">
          <table className="dashboard-table full">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Guest Name</th>
                <th>Contact</th>
                <th>Room(s)</th>
                <th>Room State</th>
                <th>Stay Dates</th>
                <th>Booking Status</th>
                <th className="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="8" className="text-center py-6">
                    <div className="spinner-large" style={{ margin: '0 auto 0.5rem auto' }} />
                    <p className="text-muted text-sm">Loading arrivals...</p>
                  </td>
                </tr>
              ) : arrivals.length === 0 ? (
                <tr>
                  <td colSpan="8" className="empty-row py-8 text-center text-muted">
                    No pending check-ins scheduled for today.
                  </td>
                </tr>
              ) : (
                arrivals.map((b) => {
                  const roomStatuses = b.booking_rooms?.map((br) => ({
                    num: br.room?.room_number,
                    status: br.room?.operational_status,
                  })) || [];

                  const hasUnreadyRoom = roomStatuses.some((r) => r.status !== 'READY');

                  return (
                    <tr key={b.id}>
                      <td className="code font-mono bold" style={{ color: 'var(--color-sidebar-active-text)' }}>
                        {b.booking_number}
                      </td>
                      <td className="bold">
                        {b.guest?.first_name} {b.guest?.last_name}
                      </td>
                      <td>{b.guest?.phone || '—'}</td>
                      <td>
                        {roomStatuses.map((r) => (
                          <span key={r.num} className="room-pill-tag">
                            {r.num}
                          </span>
                        ))}
                      </td>
                      <td>
                        {roomStatuses.map((r) => (
                          <span
                            key={r.num}
                            className={`status-pill ${
                              r.status === 'READY'
                                ? 'confirmed'
                                : r.status === 'CLEANING'
                                ? 'pending'
                                : 'cancelled'
                            }`}
                            style={{ marginRight: '4px' }}
                          >
                            <span className="status-dot active" />
                            {r.num}: {r.status}
                          </span>
                        ))}
                      </td>
                      <td style={{ whiteSpace: 'nowrap' }}>
                        {formatDateRange(b.check_in_date, b.check_out_date)}
                      </td>
                      <td>
                        <span className={`status-pill ${b.status.toLowerCase()}`}>
                          {b.status}
                        </span>
                      </td>
                      <td className="text-center" style={{ whiteSpace: 'nowrap' }}>
                        <div style={{ display: 'inline-flex', gap: '0.4rem' }}>
                          <button
                            type="button"
                            disabled={actionLoading || hasUnreadyRoom}
                            onClick={() => handleCheckIn(b.id)}
                            className={`btn btn-sm ${
                              hasUnreadyRoom ? 'btn-secondary opacity-50' : 'btn-primary'
                            }`}
                            title={
                              hasUnreadyRoom
                                ? 'Cannot check in: room must be READY'
                                : 'Confirm Guest Check-In'
                            }
                          >
                            <CheckIcon size={14} />
                            <span>Check In</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => onViewBooking(b.id)}
                            className="btn btn-secondary btn-sm"
                            title="View Booking Details"
                          >
                            <EyeIcon size={14} />
                            <span>View</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 2: DEPARTURES / IN-HOUSE */}
      {activeTab === 'departures' && (
        <div className="table-responsive">
          <table className="dashboard-table full">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Guest Name</th>
                <th>Room(s)</th>
                <th>Checked In At</th>
                <th>Scheduled Departure</th>
                <th>Status</th>
                <th className="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="7" className="text-center py-6">
                    <div className="spinner-large" style={{ margin: '0 auto 0.5rem auto' }} />
                    <p className="text-muted text-sm">Loading departures...</p>
                  </td>
                </tr>
              ) : departures.length === 0 ? (
                <tr>
                  <td colSpan="7" className="empty-row py-8 text-center text-muted">
                    No in-house guests currently awaiting check-out.
                  </td>
                </tr>
              ) : (
                departures.map((b) => (
                  <tr key={b.id}>
                    <td className="code font-mono bold" style={{ color: 'var(--color-sidebar-active-text)' }}>
                      {b.booking_number}
                    </td>
                    <td className="bold">
                      {b.guest?.first_name} {b.guest?.last_name}
                    </td>
                    <td>
                      {b.booking_rooms?.map((br) => (
                        <span key={br.id} className="room-pill-tag">
                          {br.room?.room_number}
                        </span>
                      ))}
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {b.actual_check_in_at
                        ? formatDate(b.actual_check_in_at, true)
                        : '—'}
                    </td>
                    <td className="bold" style={{ whiteSpace: 'nowrap' }}>
                      {formatDate(b.check_out_date)}
                    </td>
                    <td>
                      <span className="status-pill checked-in">
                        <span className="status-dot active" />
                        In-House
                      </span>
                    </td>
                    <td className="text-center" style={{ whiteSpace: 'nowrap' }}>
                      <div style={{ display: 'inline-flex', gap: '0.4rem' }}>
                        <button
                          type="button"
                          disabled={actionLoading}
                          onClick={() => handleCheckOut(b.id)}
                          className="btn btn-warning btn-sm"
                          title="Complete Guest Check-Out"
                        >
                          Check Out
                        </button>
                        <button
                          type="button"
                          onClick={() => setLateModalBooking(b)}
                          className="btn btn-secondary btn-sm"
                          title="Authorize Late Check-Out"
                        >
                          <ClockIcon size={14} />
                          <span>Late Checkout</span>
                        </button>
                        <button
                          type="button"
                          onClick={() => onViewBooking(b.id)}
                          className="btn btn-secondary btn-sm"
                        >
                          <EyeIcon size={14} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* TAB 3: HOUSEKEEPING CLEANING QUEUE */}
      {activeTab === 'cleaning' && (
        <div className="card">
          <div className="card-header-row mb-4" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <h3 className="section-title font-semibold text-lg" style={{ color: 'var(--color-card-heading)' }}>
                Rooms Awaiting Housekeeping & Sanitization
              </h3>
              <p className="text-muted text-sm">
                When guests check out, rooms automatically enter CLEANING state. Front desk can only assign rooms once marked READY.
              </p>
            </div>
            <span className="balance-badge due" style={{ fontSize: '0.82rem' }}>
              {cleaningRooms.length} Pending Cleaning
            </span>
          </div>

          <div className="table-responsive">
            <table className="dashboard-table full">
              <thead>
                <tr>
                  <th>Room #</th>
                  <th>Category</th>
                  <th>Floor</th>
                  <th>Current State</th>
                  <th className="text-center">Housekeeping Action</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan="5" className="text-center py-6">
                      <div className="spinner-large" style={{ margin: '0 auto 0.5rem auto' }} />
                      <p className="text-muted text-sm">Checking housekeeping turnover queue...</p>
                    </td>
                  </tr>
                ) : cleaningRooms.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="empty-row py-8 text-center text-muted">
                      ✓ All rooms are cleaned, sanitized and verified READY!
                    </td>
                  </tr>
                ) : (
                  cleaningRooms.map((rm) => (
                    <tr key={rm.id}>
                      <td className="code bold" style={{ fontSize: '1rem', color: 'var(--color-sidebar-active-text)' }}>
                        Room {rm.room_number}
                      </td>
                      <td className="bold">{rm.room_type?.name}</td>
                      <td>Floor {rm.floor}</td>
                      <td>
                        <span className="status-pill pending">
                          <span className="status-dot" style={{ background: 'var(--color-warning)' }} />
                          CLEANING IN PROGRESS
                        </span>
                      </td>
                      <td className="text-center" style={{ whiteSpace: 'nowrap' }}>
                        <button
                          type="button"
                          disabled={actionLoading}
                          onClick={() => handleMarkCleaned(rm.id, rm.room_number)}
                          className="btn btn-primary btn-sm"
                          title="Verify room cleanliness and transition to READY"
                        >
                          <CheckIcon size={14} />
                          <span>Mark Cleaned & Return to READY</span>
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

      {/* LATE CHECKOUT MODAL */}
      {lateModalBooking && (
        <div className="modal-overlay" onClick={() => setLateModalBooking(null)} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '460px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <ClockIcon size={20} className="text-forest-green" />
                <h3 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                  Grant Late Check-Out: {lateModalBooking.booking_number}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setLateModalBooking(null)}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}>
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <p className="text-sm text-muted mb-4">
                  Standard check-out is 11:00 AM. Enter the authorized extended check-out boundary timestamp.
                </p>
                <div className="form-group">
                  <label>Late Check-Out Until (Date & Time) *</label>
                  <input
                    type="datetime-local"
                    value={lateTime}
                    onChange={(e) => setLateTime(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button
                  type="button"
                  onClick={() => setLateModalBooking(null)}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={!lateTime || actionLoading}
                  onClick={() => {
                    const formatted = lateTime.replace('T', ' ') + ':00';
                    handleCheckOut(lateModalBooking.id, formatted);
                  }}
                  className="btn btn-primary"
                >
                  Confirm Late Check-Out
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
