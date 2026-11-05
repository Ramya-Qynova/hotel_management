import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDate, formatCurrency } from '../utils/formatters';
import {
  BookingsIcon,
  ServicesIcon,
  RupeeIcon,
  CreditCardIcon,
  PrintIcon,
  PlusIcon,
  XIcon,
  TrashIcon,
} from './Icons';
import AddServiceModal from './AddServiceModal';
import RecordPaymentModal from './RecordPaymentModal';
import RazorpayPaymentModal from './RazorpayPaymentModal';
import InvoicePrintModal from './InvoicePrintModal';

export default function BookingDetailModal({ bookingId, isOpen, onClose, onBookingUpdated }) {
  const { currencySymbol } = useHotelSettings();
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState('');
  const [cancelling, setCancelling] = useState(false);
  const [cancelReason, setCancelReason] = useState('');
  const [actionLoading, setActionLoading] = useState(false);

  // Sub-Modals
  const [isServiceModalOpen, setIsServiceModalOpen] = useState(false);
  const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
  const [isRazorpayModalOpen, setIsRazorpayModalOpen] = useState(false);
  const [isInvoiceModalOpen, setIsInvoiceModalOpen] = useState(false);

  const reloadBooking = async () => {
    if (!bookingId) return;
    try {
      const data = await hotelApi.getBooking(bookingId);
      setBooking(data);
      if (onBookingUpdated) onBookingUpdated(data);
    } catch (err) {
      console.error('Failed to reload booking:', err);
    }
  };

  // ESC key and body scroll lock
  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') onClose();
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, onClose]);

  useEffect(() => {
    if (!isOpen || !bookingId) return;

    setLoading(true);
    setErrorMsg('');
    hotelApi
      .getBooking(bookingId)
      .then((data) => {
        setBooking(data);
      })
      .catch((err) => {
        setErrorMsg(err.response?.data?.message || 'Failed to load booking details.');
      })
      .finally(() => {
        setLoading(false);
      });
  }, [isOpen, bookingId]);

  if (!isOpen) return null;

  const handleCheckIn = async () => {
    if (!booking) return;
    setActionLoading(true);
    setErrorMsg('');
    try {
      const updated = await hotelApi.checkIn(booking.id);
      setBooking(updated);
      if (onBookingUpdated) onBookingUpdated(updated);
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Check-in failed.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleCheckOut = async () => {
    if (!booking) return;
    setActionLoading(true);
    setErrorMsg('');
    try {
      const updated = await hotelApi.checkOut(booking.id);
      setBooking(updated);
      if (onBookingUpdated) onBookingUpdated(updated);
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Check-out failed.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancelBooking = async (e) => {
    e.preventDefault();
    if (!cancelReason.trim()) {
      setErrorMsg('Please specify a cancellation reason.');
      return;
    }
    setActionLoading(true);
    setErrorMsg('');
    try {
      const updated = await hotelApi.cancelBooking(booking.id, cancelReason.trim());
      setBooking(updated);
      setCancelling(false);
      if (onBookingUpdated) onBookingUpdated(updated);
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Cancellation failed.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleRemoveService = async (serviceItemId) => {
    if (!window.confirm('Remove this chargeable service from reservation?')) return;
    try {
      await hotelApi.removeServiceFromBooking(serviceItemId);
      reloadBooking();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to remove service.');
    }
  };

  const total = Number(booking?.grand_total || 0);
  const paid = Number(booking?.invoice?.paid_amount || 0);
  const balance = Number(booking?.invoice?.balance_due ?? Math.max(0, total - paid));

  return (
    <div className="modal-overlay" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="modal-card wide"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '820px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
      >
        <div className="modal-header" style={{ flexShrink: 0 }}>
          <div className="modal-title-row">
            <BookingsIcon size={22} className="text-forest-green" />
            <h2>Reservation Details {booking?.booking_number && `• ${booking.booking_number}`}</h2>
          </div>
          <button type="button" onClick={onClose} className="modal-close-btn" title="Close modal" aria-label="Close">
            <XIcon size={16} />
          </button>
        </div>

        {loading ? (
          <div className="dashboard-loading" style={{ minHeight: '300px' }}>
            <div className="spinner-large" />
            <p>Loading booking records...</p>
          </div>
        ) : errorMsg && !booking ? (
          <div className="alert-box error m-4">{errorMsg}</div>
        ) : !booking ? null : (
          <div className="booking-modal-body modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto' }}>
            {errorMsg && <div className="alert-box error">{errorMsg}</div>}

            {/* Status Banner */}
            <div className="booking-status-header">
              <div className="status-badges-group">
                <span
                  className={`status-pill ${
                    booking.status === 'CONFIRMED'
                      ? 'confirmed'
                      : booking.status === 'CHECKED_IN'
                      ? 'checked-in'
                      : booking.status === 'PENDING_PAYMENT'
                      ? 'pending'
                      : booking.status === 'CHECKED_OUT'
                      ? 'checked-out'
                      : 'cancelled'
                  }`}
                >
                  {booking.status}
                </span>
                <span className="source-tag">{booking.booking_source}</span>
              </div>

              <div className="action-buttons-group">
                {booking.status === 'CONFIRMED' && (
                  <button
                    type="button"
                    disabled={actionLoading}
                    onClick={handleCheckIn}
                    className="btn btn-primary btn-sm"
                  >
                    Check In Guest
                  </button>
                )}

                {booking.status === 'CHECKED_IN' && (
                  <button
                    type="button"
                    disabled={actionLoading}
                    onClick={handleCheckOut}
                    className="btn btn-warning btn-sm"
                  >
                    Check Out Guest
                  </button>
                )}

                {['CONFIRMED', 'PENDING_PAYMENT'].includes(booking.status) && !cancelling && (
                  <button
                    type="button"
                    onClick={() => setCancelling(true)}
                    className="btn btn-danger-outline btn-sm"
                  >
                    Cancel Booking
                  </button>
                )}
              </div>
            </div>

            {cancelling && (
              <form onSubmit={handleCancelBooking} className="cancel-form-box">
                <h4>Confirm Reservation Cancellation</h4>
                <div className="form-field">
                  <label>Cancellation Reason *</label>
                  <input
                    type="text"
                    placeholder="Guest request, flight cancelled, emergency..."
                    value={cancelReason}
                    onChange={(e) => setCancelReason(e.target.value)}
                    required
                  />
                </div>
                <div className="modal-actions-row">
                  <button
                    type="button"
                    onClick={() => setCancelling(false)}
                    className="btn btn-secondary btn-sm"
                  >
                    Abort
                  </button>
                  <button
                    type="submit"
                    disabled={actionLoading}
                    className="btn btn-danger btn-sm"
                  >
                    {actionLoading ? 'Cancelling...' : 'Confirm Cancellation'}
                  </button>
                </div>
              </form>
            )}

            {/* Details Grid */}
            <div className="details-two-col">
              {/* Guest Profile */}
              <div className="detail-card">
                <h4 className="detail-card-title">Guest Profile</h4>
                <div className="detail-list-simple">
                  <div className="row">
                    <span className="lbl">Full Name</span>
                    <span className="val bold">
                      {booking.guest?.first_name} {booking.guest?.last_name}
                    </span>
                  </div>
                  <div className="row">
                    <span className="lbl">Phone</span>
                    <span className="val">{booking.guest?.phone}</span>
                  </div>
                  <div className="row">
                    <span className="lbl">Email</span>
                    <span className="val">{booking.guest?.email || 'N/A'}</span>
                  </div>
                  {booking.guest?.id_type && (
                    <div className="row">
                      <span className="lbl">{booking.guest.id_type}</span>
                      <span className="val code">{booking.guest.id_number || 'Confidential'}</span>
                    </div>
                  )}
                  {booking.special_requests && (
                    <div className="row notes">
                      <span className="lbl">Special Requests</span>
                      <span className="val italic">{booking.special_requests}</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Stay & Room Allocation */}
              <div className="detail-card">
                <h4 className="detail-card-title">Stay & Timestamps</h4>
                <div className="detail-list-simple">
                  <div className="row">
                    <span className="lbl">Scheduled Check-in</span>
                    <span className="val bold">{formatDate(booking.check_in_date)}</span>
                  </div>
                  <div className="row">
                    <span className="lbl">Scheduled Check-out</span>
                    <span className="val bold">{formatDate(booking.check_out_date)}</span>
                  </div>
                  {booking.actual_check_in_at && (
                    <div className="row">
                      <span className="lbl">Actual Check-in</span>
                      <span className="val highlight-text">
                        {formatDate(booking.actual_check_in_at, true)}
                      </span>
                    </div>
                  )}
                  {booking.actual_check_out_at && (
                    <div className="row">
                      <span className="lbl">Actual Check-out</span>
                      <span className="val highlight-text">
                        {formatDate(booking.actual_check_out_at, true)}
                      </span>
                    </div>
                  )}
                  <div className="row">
                    <span className="lbl">Occupancy</span>
                    <span className="val">
                      {booking.adults_count} Adult(s), {booking.children_count} Children
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {/* Rooms & Snapshot Pricing */}
            <div className="allocated-rooms-section">
              <h4 className="detail-card-title">Allocated Rooms & Historical Rates</h4>
              <div className="table-responsive">
                <table className="dashboard-table">
                  <thead>
                    <tr>
                      <th>Room</th>
                      <th>Category</th>
                      <th>Rate / Night</th>
                      <th>Nights</th>
                      <th>Subtotal</th>
                      <th>Tax ({booking.booking_rooms?.[0]?.tax_rate_percent || 12}%)</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {booking.booking_rooms?.map((br) => (
                      <tr key={br.id}>
                        <td className="code-cell">{br.room?.room_number}</td>
                        <td>{br.room?.room_type?.name}</td>
                        <td>{formatCurrency(br.rate_per_night, currencySymbol)}</td>
                        <td>{br.nights_count}</td>
                        <td>{formatCurrency(br.subtotal, currencySymbol)}</td>
                        <td>{formatCurrency(br.tax_amount, currencySymbol)}</td>
                        <td className="bold text-forest-green">{formatCurrency(br.total_amount, currencySymbol)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Attached Chargeable Services */}
            <div className="allocated-rooms-section mt-4">
              <div className="section-title-between">
                <h4 className="detail-card-title">
                  <ServicesIcon size={18} /> Chargeable Hotel Services Ordered
                </h4>
                {['CONFIRMED', 'CHECKED_IN'].includes(booking.status) && (
                  <button
                    type="button"
                    onClick={() => setIsServiceModalOpen(true)}
                    className="btn btn-secondary btn-sm"
                  >
                    <PlusIcon size={14} /> Add Service
                  </button>
                )}
              </div>

              <div className="table-responsive">
                {(!booking.booking_services || booking.booking_services.length === 0) ? (
                  <p className="text-muted p-3 text-center">No additional chargeable services ordered yet.</p>
                ) : (
                  <table className="dashboard-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Delivered To</th>
                        <th className="text-right">Qty</th>
                        <th className="text-right">Unit Price</th>
                        <th className="text-right">Tax</th>
                        <th className="text-right">Subtotal</th>
                        <th className="text-right">Total</th>
                        <th className="text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {booking.booking_services.map((bs) => (
                        <tr key={bs.id}>
                          <td>{formatDate(bs.service_date)}</td>
                          <td className="bold">{bs.service?.name}</td>
                          <td>{bs.room ? `Room ${bs.room.room_number}` : 'Folio'}</td>
                          <td className="text-right">{bs.quantity}</td>
                          <td className="text-right">{formatCurrency(bs.unit_price, currencySymbol)}</td>
                          <td className="text-right">{formatCurrency(bs.tax_amount, currencySymbol)}</td>
                          <td className="text-right">{formatCurrency(bs.subtotal, currencySymbol)}</td>
                          <td className="text-right bold text-forest-green">
                            {formatCurrency(bs.total_amount, currencySymbol)}
                          </td>
                          <td className="text-center">
                            {['CONFIRMED', 'CHECKED_IN'].includes(booking.status) && (
                              <button
                                type="button"
                                onClick={() => handleRemoveService(bs.id)}
                                className="btn-link-danger"
                                title="Remove Service"
                              >
                                <TrashIcon size={14} />
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>

            {/* Financial Summary & Billing Actions Bar */}
            <div className="financial-summary-panel mt-4">
              <div className="folio-metric-item">
                <span className="lbl">Grand Total</span>
                <span className="val bold">{formatCurrency(total, currencySymbol)}</span>
              </div>
              <div className="folio-metric-item">
                <span className="lbl">Total Paid</span>
                <span className="val bold text-forest-green">{formatCurrency(paid, currencySymbol)}</span>
              </div>
              <div className="folio-metric-item">
                <span className="lbl">Balance Due</span>
                <span className={`val bold ${balance > 0 ? 'text-danger' : 'text-forest-green'}`}>
                  {formatCurrency(balance, currencySymbol)}
                </span>
              </div>

              <div className="billing-action-pills">
                <button
                  type="button"
                  onClick={() => setIsPaymentModalOpen(true)}
                  className="btn btn-primary btn-sm"
                >
                  <RupeeIcon size={14} /> Record Payment
                </button>
                <button
                  type="button"
                  onClick={() => setIsRazorpayModalOpen(true)}
                  className="btn btn-success-outline btn-sm"
                >
                  <CreditCardIcon size={14} /> Pay via Razorpay
                </button>
                <button
                  type="button"
                  onClick={() => setIsInvoiceModalOpen(true)}
                  className="btn btn-secondary btn-sm"
                >
                  <PrintIcon size={14} /> Tax Invoice
                </button>
              </div>
            </div>

            {/* Payment Transactions Log */}
            {booking.payments && booking.payments.length > 0 && (
              <div className="audit-history-section mt-4">
                <h4 className="detail-card-title">Payments Log</h4>
                <div className="table-responsive">
                  <table className="dashboard-table">
                    <thead>
                      <tr>
                        <th>Transaction Ref</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th className="text-right">Amount</th>
                        <th>Timestamp</th>
                      </tr>
                    </thead>
                    <tbody>
                      {booking.payments.map((p) => (
                        <tr key={p.id}>
                          <td className="code">{p.transaction_reference}</td>
                          <td>{p.payment_method}</td>
                          <td>{p.payment_type}</td>
                          <td>
                            <span className={`status-pill ${p.status === 'SUCCESS' ? 'confirmed' : 'pending'}`}>
                              {p.status}
                            </span>
                          </td>
                          <td className="text-right bold text-forest-green">{formatCurrency(p.amount, currencySymbol)}</td>
                          <td style={{ whiteSpace: 'nowrap' }}>{formatDate(p.created_at, true)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Lifecycle Status Transitions */}
            {booking.status_histories && booking.status_histories.length > 0 && (
              <div className="audit-history-section mt-4">
                <h4 className="detail-card-title">Audit Log & Lifecycle History</h4>
                <ul className="timeline-list">
                  {booking.status_histories.map((hist) => (
                    <li key={hist.id} className="timeline-item">
                      <div className="timeline-bullet" />
                      <div className="timeline-content">
                        <div className="timeline-header">
                          <span className="timeline-status">
                            {hist.from_status ? `${hist.from_status} → ` : ''}
                            {hist.to_status}
                          </span>
                          <span className="timeline-time">
                            {new Date(hist.created_at).toLocaleString()}
                          </span>
                        </div>
                        <p className="timeline-reason">{hist.reason}</p>
                        {hist.changed_by && (
                          <span className="timeline-actor">
                            By staff: {hist.changed_by.name || `User #${hist.changed_by}`}
                          </span>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}

        {/* Sub-Modals */}
        {isServiceModalOpen && booking && (
          <AddServiceModal
            isOpen={isServiceModalOpen}
            booking={booking}
            onClose={() => setIsServiceModalOpen(false)}
            onServiceAdded={() => reloadBooking()}
          />
        )}

        {isPaymentModalOpen && booking && (
          <RecordPaymentModal
            isOpen={isPaymentModalOpen}
            booking={booking}
            onClose={() => setIsPaymentModalOpen(false)}
            onPaymentRecorded={() => reloadBooking()}
          />
        )}

        {isRazorpayModalOpen && booking && (
          <RazorpayPaymentModal
            isOpen={isRazorpayModalOpen}
            booking={booking}
            onClose={() => setIsRazorpayModalOpen(false)}
            onPaymentSuccess={() => reloadBooking()}
          />
        )}

        {isInvoiceModalOpen && booking && (
          <InvoicePrintModal
            isOpen={isInvoiceModalOpen}
            bookingId={booking.id}
            onClose={() => setIsInvoiceModalOpen(false)}
            onInvoiceUpdated={() => reloadBooking()}
          />
        )}
      </div>
    </div>
  );
}