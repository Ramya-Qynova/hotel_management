import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDate, formatDateRange, formatCurrency } from '../utils/formatters';
import {
  BillingIcon,
  ReceiptIcon,
  ServicesIcon,
  RupeeIcon,
  CreditCardIcon,
  PrintIcon,
  XIcon,
  CheckCircleIcon,
  AlertCircleIcon,
} from './Icons';

export default function FolioInspectorModal({
  isOpen,
  booking,
  onClose,
  onOpenRecordPayment,
  onOpenRazorpay,
  onOpenAddService,
  onOpenInvoice,
  onOpenRefund,
}) {
  const { currencySymbol } = useHotelSettings();
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');

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

  // Load live billing summary from backend
  useEffect(() => {
    if (!isOpen || !booking) return;

    let isMounted = true;
    setLoading(true);
    setErrorMessage('');

    hotelApi
      .getBillingSummary(booking.id)
      .then((data) => {
        if (isMounted) setSummary(data);
      })
      .catch((err) => {
        if (isMounted) {
          setErrorMessage(err.response?.data?.message || 'Failed to load folio billing summary.');
        }
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [isOpen, booking]);

  if (!isOpen || !booking) return null;

  const fin = summary?.financials;
  const grandTotal = Number(fin?.grand_total ?? booking.grand_total ?? 0);
  const totalPaid = Number(fin?.total_paid ?? booking.invoice?.paid_amount ?? 0);
  const totalRefunded = Number(fin?.total_refunded ?? 0);
  const outstanding = Number(fin?.outstanding_balance ?? booking.invoice?.balance_due ?? Math.max(0, grandTotal - totalPaid));
  const isSettled = outstanding <= 0;

  return (
    <div className="modal-overlay" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="modal-card wide folio-inspector-modal"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '880px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
      >
        {/* Pinned Modal Header */}
        <div className="modal-header" style={{ flexShrink: 0 }}>
          <div className="modal-title-row">
            <ReceiptIcon size={22} className="text-forest-green" />
            <div>
              <h2 style={{ fontSize: '1.2rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                Folio Inspector • {booking.booking_number}
              </h2>
              <p className="modal-subtitle-text" style={{ margin: 0, fontSize: '0.8rem', color: 'var(--color-text-secondary)' }}>
                Itemized guest folio statement, taxes, and financial transaction ledger
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="modal-close-btn"
            title="Close folio inspector"
            aria-label="Close"
          >
            <XIcon size={18} />
          </button>
        </div>

        {/* Scrollable Modal Body */}
        <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
          {loading ? (
            <div className="dashboard-loading" style={{ minHeight: '300px' }}>
              <div className="spinner-large" />
              <p>Compiling itemized folio statement...</p>
            </div>
          ) : errorMessage ? (
            <div className="alert-box error mb-4">
              <AlertCircleIcon size={18} />
              <span>{errorMessage}</span>
            </div>
          ) : (
            <>
              {/* Quick Actions Bar */}
              <div className="folio-inspector-actions-bar mb-4">
                <span className="font-semibold text-xs text-muted" style={{ textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                  Folio Actions:
                </span>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
                  <button
                    type="button"
                    onClick={() => onOpenAddService(booking)}
                    className="btn btn-secondary btn-sm"
                    title="Add Room Service or Amenities"
                  >
                    <ServicesIcon size={14} />
                    <span>Order Service</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => onOpenRecordPayment(booking)}
                    className="btn btn-primary btn-sm"
                    title="Record Cash/Card/UPI Payment"
                  >
                    <RupeeIcon size={14} />
                    <span>Record Payment</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => onOpenRazorpay(booking)}
                    className="btn btn-success-outline btn-sm"
                    title="Generate Razorpay Online Checkout Link"
                  >
                    <CreditCardIcon size={14} />
                    <span>Razorpay Checkout</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => onOpenInvoice(booking)}
                    className="btn btn-secondary btn-sm"
                    title="Generate & Print Official GST Tax Invoice"
                  >
                    <PrintIcon size={14} />
                    <span>Tax Invoice</span>
                  </button>
                </div>
              </div>

              {/* Top 4 KPI Summary Cards */}
              <div className="register-summary-grid mb-4" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))' }}>
                <div className="metric-card">
                  <span className="metric-label">Grand Total</span>
                  <span className="metric-value">{formatCurrency(grandTotal, currencySymbol)}</span>
                  <span className="metric-hint">Room + Services + GST</span>
                </div>
                <div className="metric-card">
                  <span className="metric-label">Total Paid</span>
                  <span className="metric-value text-forest-green">{formatCurrency(totalPaid, currencySymbol)}</span>
                  <span className="metric-hint">Received in drawer / bank</span>
                </div>
                <div className="metric-card">
                  <span className="metric-label">Total Refunded</span>
                  <span className="metric-value text-danger">{formatCurrency(totalRefunded, currencySymbol)}</span>
                  <span className="metric-hint">Returned to guest</span>
                </div>
                <div className="metric-card highlight">
                  <span className="metric-label">Outstanding Balance</span>
                  <span className="metric-value">
                    {isSettled ? (
                      <span className="text-forest-green font-bold">Settled</span>
                    ) : (
                      formatCurrency(outstanding, currencySymbol)
                    )}
                  </span>
                  <span className="metric-hint font-semibold">
                    {isSettled ? 'No balance pending' : 'Pending payment settlement'}
                  </span>
                </div>
              </div>

              {/* Guest Profile & Stay Details */}
              <div className="card mb-4" style={{ padding: '1rem 1.25rem' }}>
                <h3 className="section-title" style={{ margin: '0 0 0.75rem 0' }}>Guest & Reservation Details</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '0.75rem', fontSize: '0.88rem' }}>
                  <div>
                    <span className="text-muted block text-xs">Primary Guest</span>
                    <strong className="block text-emerald-950 font-semibold">
                      {summary?.guest?.name || `${booking.guest?.first_name} ${booking.guest?.last_name}`}
                    </strong>
                    <span className="text-muted text-xs">
                      Phone: {summary?.guest?.phone || booking.guest?.phone || '—'}
                    </span>
                  </div>
                  <div>
                    <span className="text-muted block text-xs">Stay Dates</span>
                    <strong className="block text-emerald-950 font-semibold">
                      {formatDateRange(booking.check_in_date, booking.check_out_date)}
                    </strong>
                    <span className="text-muted text-xs">
                      Status:{' '}
                      <span className="bold" style={{ textTransform: 'capitalize' }}>
                        {booking.status?.toLowerCase().replace('_', ' ')}
                      </span>
                    </span>
                  </div>
                  <div>
                    <span className="text-muted block text-xs">Guest Email</span>
                    <span className="block text-truncate" title={summary?.guest?.email || booking.guest?.email || ''}>
                      {summary?.guest?.email || booking.guest?.email || '—'}
                    </span>
                  </div>
                </div>
              </div>

              {/* Itemized Room Charges Breakdown */}
              <div className="card mb-4" style={{ padding: '1rem 1.25rem' }}>
                <h3 className="section-title" style={{ margin: '0 0 0.75rem 0' }}>
                  1. Room Accommodation Charges
                </h3>
                <div className="table-responsive">
                  <table className="dashboard-table" style={{ fontSize: '0.85rem' }}>
                    <thead>
                      <tr>
                        <th>Room No</th>
                        <th>Room Category</th>
                        <th className="text-right">Rate / Night</th>
                        <th className="text-center">Nights</th>
                        <th className="text-right">Subtotal</th>
                        <th className="text-right">Tax (GST)</th>
                        <th className="text-right">Line Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {summary?.rooms && summary.rooms.length > 0 ? (
                        summary.rooms.map((rm) => (
                          <tr key={rm.id}>
                            <td className="bold code" style={{ color: 'var(--color-sidebar-active-text)' }}>
                              Room {rm.room_number || '—'}
                            </td>
                            <td>{rm.room_type || 'Standard'}</td>
                            <td className="text-right">{formatCurrency(rm.rate_per_night, currencySymbol)}</td>
                            <td className="text-center">{rm.nights_count}</td>
                            <td className="text-right">{formatCurrency(rm.subtotal, currencySymbol)}</td>
                            <td className="text-right">{formatCurrency(rm.tax_amount, currencySymbol)}</td>
                            <td className="text-right bold text-forest-green">
                              {formatCurrency(rm.total_amount, currencySymbol)}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={7} className="text-center text-muted p-3">
                            No room charge records found for this reservation.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Itemized Add-on Services Breakdown */}
              <div className="card mb-4" style={{ padding: '1rem 1.25rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                  <h3 className="section-title" style={{ margin: 0 }}>
                    2. Add-on Services & Amenities
                  </h3>
                  <button
                    type="button"
                    onClick={() => onOpenAddService(booking)}
                    className="btn btn-secondary btn-xs"
                  >
                    <ServicesIcon size={12} />
                    <span>Add Service</span>
                  </button>
                </div>
                <div className="table-responsive">
                  <table className="dashboard-table" style={{ fontSize: '0.85rem' }}>
                    <thead>
                      <tr>
                        <th>Service Name</th>
                        <th>Room</th>
                        <th className="text-center">Qty</th>
                        <th className="text-right">Unit Price</th>
                        <th className="text-right">Subtotal</th>
                        <th className="text-right">Tax (GST)</th>
                        <th className="text-right">Total</th>
                        <th>Service Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {summary?.services && summary.services.length > 0 ? (
                        summary.services.map((srv) => (
                          <tr key={srv.id}>
                            <td className="bold">{srv.service_name}</td>
                            <td>{srv.room_number ? `Room ${srv.room_number}` : 'All Rooms'}</td>
                            <td className="text-center">{srv.quantity}</td>
                            <td className="text-right">{formatCurrency(srv.unit_price, currencySymbol)}</td>
                            <td className="text-right">{formatCurrency(srv.subtotal, currencySymbol)}</td>
                            <td className="text-right">{formatCurrency(srv.tax_amount, currencySymbol)}</td>
                            <td className="text-right bold text-forest-green">
                              {formatCurrency(srv.total_amount, currencySymbol)}
                            </td>
                            <td style={{ whiteSpace: 'nowrap' }}>{formatDate(srv.service_date)}</td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={8} className="text-center text-muted p-3">
                            No additional chargeable services recorded.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Financial Charges & Tax Summary Box */}
              <div className="quote-summary-card card mb-4" style={{ padding: '1.15rem 1.25rem' }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '1rem' }}>
                  <div>
                    <span className="q-label">Base Room Charges:</span>
                    <span className="q-val font-semibold">{formatCurrency(fin?.base_room_charges || 0, currencySymbol)}</span>
                  </div>
                  <div>
                    <span className="q-label">Service Charges:</span>
                    <span className="q-val font-semibold">{formatCurrency(fin?.total_service_charges || 0, currencySymbol)}</span>
                  </div>
                  <div>
                    <span className="q-label">Applicable Taxes (GST):</span>
                    <span className="q-val font-semibold">{formatCurrency(fin?.total_tax_amount || 0, currencySymbol)}</span>
                  </div>
                  <div>
                    <span className="q-label">Grand Invoiced Total:</span>
                    <span className="q-val total font-bold">{formatCurrency(grandTotal, currencySymbol)}</span>
                  </div>
                </div>
              </div>

              {/* Payment Ledger & Transaction History */}
              <div className="card mb-4" style={{ padding: '1rem 1.25rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}>
                  <h3 className="section-title" style={{ margin: 0 }}>
                    3. Payment Ledger & Receipts
                  </h3>
                  <button
                    type="button"
                    onClick={() => onOpenRecordPayment(booking)}
                    className="btn btn-primary btn-xs"
                  >
                    <RupeeIcon size={12} />
                    <span>Record Payment</span>
                  </button>
                </div>
                <div className="table-responsive">
                  <table className="dashboard-table" style={{ fontSize: '0.85rem' }}>
                    <thead>
                      <tr>
                        <th>Date & Time</th>
                        <th>Transaction Ref</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th className="text-right">Amount</th>
                        <th>Status</th>
                        <th>Received By</th>
                        <th className="text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      {summary?.payments && summary.payments.length > 0 ? (
                        summary.payments.map((pmt) => (
                          <tr key={pmt.id}>
                            <td style={{ whiteSpace: 'nowrap' }}>{formatDate(pmt.paid_at, true)}</td>
                            <td className="code bold" style={{ color: 'var(--color-sidebar-active-text)' }}>
                              {pmt.transaction_reference}
                            </td>
                            <td>
                              <span className="payment-method-pill">{pmt.method}</span>
                            </td>
                            <td>{pmt.type}</td>
                            <td className="text-right bold text-forest-green">
                              {formatCurrency(pmt.amount, currencySymbol)}
                            </td>
                            <td>
                              <span
                                className={`status-pill ${
                                  pmt.status === 'SUCCESS' ? 'confirmed' : pmt.status === 'REFUNDED' ? 'refunded' : 'cancelled'
                                }`}
                              >
                                {pmt.status}
                              </span>
                            </td>
                            <td>{pmt.received_by || 'Cashier'}</td>
                            <td className="text-center">
                              {pmt.status === 'SUCCESS' && pmt.refundable_balance > 0 && (
                                <button
                                  type="button"
                                  onClick={() => onOpenRefund(pmt)}
                                  className="btn btn-danger-outline btn-xs"
                                  title="Issue refund against this transaction"
                                >
                                  Refund
                                </button>
                              )}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={8} className="text-center text-muted p-3">
                            No payment transactions recorded for this reservation yet.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Refunds Ledger (if any) */}
              {summary?.refunds && summary.refunds.length > 0 && (
                <div className="card mb-4" style={{ padding: '1rem 1.25rem' }}>
                  <h3 className="section-title text-danger" style={{ margin: '0 0 0.75rem 0' }}>
                    4. Authorized Refunds
                  </h3>
                  <div className="table-responsive">
                    <table className="dashboard-table" style={{ fontSize: '0.85rem' }}>
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Refund Ref</th>
                          <th>Payment Ref</th>
                          <th>Method</th>
                          <th>Status</th>
                          <th className="text-right">Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        {summary.refunds.map((ref) => (
                          <tr key={ref.id}>
                            <td style={{ whiteSpace: 'nowrap' }}>{formatDate(ref.created_at, true)}</td>
                            <td className="code bold text-danger">{ref.refund_reference}</td>
                            <td className="code">{ref.payment_reference || '—'}</td>
                            <td>{ref.method}</td>
                            <td>
                              <span className="status-pill refunded">{ref.status}</span>
                            </td>
                            <td className="text-right bold text-danger">
                              {formatCurrency(ref.amount, currencySymbol)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </>
          )}
        </div>

        {/* Pinned Modal Footer */}
        <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
          <button type="button" onClick={() => onOpenInvoice(booking)} className="btn btn-secondary">
            <PrintIcon size={14} />
            <span>Print Tax Invoice</span>
          </button>
          <button type="button" onClick={onClose} className="btn btn-primary">
            Close Folio Inspector
          </button>
        </div>
      </div>
    </div>
  );
}

