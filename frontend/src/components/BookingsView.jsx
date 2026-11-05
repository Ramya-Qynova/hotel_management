import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDateRange, formatCurrency } from '../utils/formatters';
import { SearchIcon, PlusIcon, EyeIcon, XIcon } from './Icons';

export default function BookingsView({ onOpenNewBooking, onViewBooking }) {
  const { currencySymbol } = useHotelSettings();
  const [bookings, setBookings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [errorMsg, setErrorMsg] = useState('');

  const fetchBookings = () => {
    setLoading(true);
    setErrorMsg('');
    const params = { page, per_page: 15 };
    if (statusFilter) params.status = statusFilter;
    if (search.trim()) params.search = search.trim();

    hotelApi
      .getBookings(params)
      .then((res) => {
        setBookings(res?.data || []);
        setPagination({
          current_page: res?.current_page || 1,
          last_page: res?.last_page || 1,
          total: res?.total || 0,
        });
      })
      .catch((err) => {
        setErrorMsg(err.response?.data?.message || 'Failed to fetch bookings');
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchBookings();
  }, [statusFilter, page]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    fetchBookings();
  };

  return (
    <div className="view-container">
      {/* Page Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Reservations & Bookings</h1>
          <p className="view-subtitle">
            Manage guest reservations, multi-room rate snapshots, check-in statuses, and guest stay histories.
          </p>
        </div>

        <button
          type="button"
          onClick={onOpenNewBooking}
          className="btn btn-primary"
        >
          <PlusIcon size={16} />
          <span>New Reservation</span>
        </button>
      </div>

      {errorMsg && <div className="alert-box error mb-4">{errorMsg}</div>}

      {/* Filter & Search Bar */}
      <div className="filter-bar">
        <div className="filter-pill-group">
          {[
            { id: '', label: 'All Bookings' },
            { id: 'CONFIRMED', label: 'Confirmed' },
            { id: 'CHECKED_IN', label: 'Checked In' },
            { id: 'CHECKED_OUT', label: 'Checked Out' },
            { id: 'PENDING_PAYMENT', label: 'Pending Hold' },
            { id: 'CANCELLED', label: 'Cancelled' },
          ].map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => {
                setStatusFilter(tab.id);
                setPage(1);
              }}
              className={`filter-pill ${statusFilter === tab.id ? 'active' : ''}`}
            >
              <span>{tab.label}</span>
            </button>
          ))}
        </div>

        <form onSubmit={handleSearchSubmit} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <div className="search-input-wrapper">
            <SearchIcon size={16} className="search-icon-inside" />
            <input
              type="text"
              placeholder="Search Booking ID, Guest Name or Phone..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            {search && (
              <button
                type="button"
                onClick={() => {
                  setSearch('');
                  setPage(1);
                  fetchBookings();
                }}
                style={{ color: 'var(--color-muted)', padding: 0 }}
                title="Clear search"
              >
                <XIcon size={14} />
              </button>
            )}
          </div>
          <button type="submit" className="btn btn-secondary btn-sm">
            Search
          </button>
        </form>
      </div>

      {/* Bookings Table */}
      <div className="table-responsive">
        <table className="dashboard-table full">
          <thead>
            <tr>
              <th>Booking ID</th>
              <th>Guest Name</th>
              <th>Contact Phone</th>
              <th>Allocated Room(s)</th>
              <th>Stay Dates</th>
              <th>Source</th>
              <th className="text-right">Grand Total</th>
              <th>Status</th>
              <th className="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan="9" className="text-center py-8">
                  <div className="spinner-large" style={{ margin: '0 auto 0.5rem auto' }} />
                  <p className="text-muted text-sm">Loading reservations...</p>
                </td>
              </tr>
            ) : bookings.length === 0 ? (
              <tr>
                <td colSpan="9" className="empty-row py-8 text-center text-muted">
                  No reservations found matching the current criteria.
                </td>
              </tr>
            ) : (
              bookings.map((bk) => {
                const guestName = bk.guest
                  ? `${bk.guest.first_name} ${bk.guest.last_name}`
                  : 'Walk-in Guest';
                const roomNumbers = bk.booking_rooms
                  ?.map((br) => br.room?.room_number)
                  .filter(Boolean)
                  .join(', ') || 'Unassigned';

                return (
                  <tr key={bk.id}>
                    <td className="code font-mono bold" style={{ color: 'var(--color-sidebar-active-text)' }}>
                      {bk.booking_number}
                    </td>
                    <td className="font-semibold text-emerald-950">{guestName}</td>
                    <td>{bk.guest?.phone || '—'}</td>
                    <td>
                      <span className="room-pill-tag">{roomNumbers}</span>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {formatDateRange(bk.check_in_date, bk.check_out_date)}
                    </td>
                    <td>
                      <span className="source-tag-small">{bk.booking_source}</span>
                    </td>
                    <td className="text-right bold text-forest-green" style={{ fontSize: '0.95rem' }}>
                      {formatCurrency(bk.grand_total, currencySymbol)}
                    </td>
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
                        <span className="status-dot active" />
                        {bk.status === 'CONFIRMED'
                          ? 'Confirmed'
                          : bk.status === 'CHECKED_IN'
                          ? 'Checked In'
                          : bk.status === 'PENDING_PAYMENT'
                          ? 'Pending Hold'
                          : bk.status === 'CHECKED_OUT'
                          ? 'Checked Out'
                          : bk.status}
                      </span>
                    </td>
                    <td className="text-center" style={{ whiteSpace: 'nowrap' }}>
                      <button
                        type="button"
                        onClick={() => onViewBooking(bk.id)}
                        className="btn btn-secondary btn-xs"
                        title="View Full Booking Folio"
                      >
                        <EyeIcon size={13} />
                        <span>View</span>
                      </button>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Controls */}
      {pagination && pagination.last_page > 1 && (
        <div className="pagination-bar mt-4" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <span className="text-sm text-muted">
            Showing Page <strong>{pagination.current_page}</strong> of <strong>{pagination.last_page}</strong> ({pagination.total} Total Bookings)
          </span>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              className="btn btn-secondary btn-sm"
            >
              ← Previous
            </button>
            <button
              type="button"
              disabled={page >= pagination.last_page}
              onClick={() => setPage((p) => p + 1)}
              className="btn btn-secondary btn-sm"
            >
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
