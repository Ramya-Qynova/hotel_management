import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDate, formatCurrency } from '../utils/formatters';
import { LotusIcon, PrintIcon, PlusIcon, XIcon } from './Icons';

export default function InvoicePrintModal({ invoiceId, bookingId, isOpen, onClose, onInvoiceUpdated }) {
  const { settings, currencySymbol } = useHotelSettings();
  const [invoice, setInvoice] = useState(null);
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');
  const [showAdjForm, setShowAdjForm] = useState(false);
  const [adjType, setAdjType] = useState('DISCOUNT');
  const [adjAmount, setAdjAmount] = useState('');
  const [adjReason, setAdjReason] = useState('');
  const [submittingAdj, setSubmittingAdj] = useState(false);

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
    if (!isOpen) return;

    const loadInvoice = async () => {
      setLoading(true);
      setErrorMessage('');
      try {
        let invData = null;
        if (invoiceId) {
          invData = await hotelApi.getPrintableInvoice(invoiceId);
        } else if (bookingId) {
          invData = await hotelApi.generateBookingInvoice(bookingId);
        }

        // Normalize structured printable invoice vs Eloquent model invoice
        if (invData?.invoice) {
          invData = {
            ...invData.invoice,
            hotel: invData.hotel,
            guest: invData.guest,
            booking: invData.booking,
            items: invData.items,
            payments: invData.payments,
          };
        }

        setInvoice(invData);
      } catch (err) {
        setErrorMessage(err.response?.data?.message || 'Failed to load invoice details.');
      } finally {
        setLoading(false);
      }
    };

    loadInvoice();
  }, [isOpen, invoiceId, bookingId]);

  if (!isOpen) return null;

  const handlePrint = () => {
    window.print();
  };

  const handleAddAdjustment = async (e) => {
    e.preventDefault();
    if (!adjAmount || parseFloat(adjAmount) <= 0 || !adjReason.trim()) {
      alert('Please enter a valid adjustment amount and reason.');
      return;
    }

    setSubmittingAdj(true);
    try {
      const updated = await hotelApi.addInvoiceAdjustment(invoice.id, {
        item_type: adjType,
        amount: parseFloat(adjAmount),
        description: adjReason.trim(),
      });
      setInvoice(updated);
      setShowAdjForm(false);
      setAdjAmount('');
      setAdjReason('');
      if (onInvoiceUpdated) onInvoiceUpdated(updated);
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to record invoice adjustment.');
    } finally {
      setSubmittingAdj(false);
    }
  };

  const hotelName = invoice?.hotel?.name || settings.hotel_name || 'Hotel Folio';
  const hotelTagline = invoice?.hotel?.tagline || settings.hotel_tagline || '';
  const hotelAddress = invoice?.hotel?.address || settings.hotel_address || '';
  const hotelPhone = invoice?.hotel?.phone || settings.hotel_phone || '';
  const hotelEmail = invoice?.hotel?.email || settings.hotel_email || '';
  const hotelGstin = invoice?.hotel?.gstin || settings.hotel_gstin || '';

  const guestName =
    invoice?.guest?.name ||
    (invoice?.guest?.first_name ? `${invoice.guest.first_name} ${invoice.guest.last_name || ''}`.trim() : 'Guest');

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-card printable-modal-card" onClick={(e) => e.stopPropagation()}>
        {/* Top Control Bar */}
        <div className="modal-header print-hide">
          <div className="modal-title-row">
            <PrintIcon size={20} className="text-forest-green" />
            <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0 }}>
              Folio Tax Invoice {invoice?.invoice_number ? `#${invoice.invoice_number}` : ''}
            </h2>
          </div>
          <div className="modal-header-actions">
            {!showAdjForm && (
              <button
                type="button"
                onClick={() => setShowAdjForm(true)}
                className="btn btn-secondary btn-sm"
              >
                <PlusIcon size={14} /> Adjustment
              </button>
            )}
            <button type="button" onClick={handlePrint} className="btn btn-primary btn-sm">
              <PrintIcon size={16} /> Print / Save PDF
            </button>
            <button type="button" onClick={onClose} className="modal-close-btn" title="Close modal">
              <XIcon size={16} />
            </button>
          </div>
        </div>

        {loading ? (
          <div className="dashboard-loading py-8">
            <div className="spinner-large" />
            <p>Rendering invoice folio...</p>
          </div>
        ) : errorMessage ? (
          <div className="alert-box error m-4">{errorMessage}</div>
        ) : !invoice ? null : (
          <div className="printable-invoice-content" id="printableInvoice">
            {/* Invoice Header */}
            <div className="invoice-header-block">
              <div className="hotel-info-side">
                <div className="hotel-brand-row">
                  {settings.hotel_logo_url ? (
                    <img
                      src={settings.hotel_logo_url}
                      alt={hotelName}
                      style={{ maxHeight: '36px', maxWidth: '36px', objectFit: 'contain' }}
                    />
                  ) : (
                    <LotusIcon size={36} />
                  )}
                  <div>
                    <h1 className="hotel-name-title">{hotelName}</h1>
                    {hotelTagline && <p className="hotel-tagline-text">{hotelTagline}</p>}
                  </div>
                </div>
                <div className="hotel-contacts-text">
                  {hotelAddress && <p>{hotelAddress}</p>}
                  {(hotelPhone || hotelEmail) && (
                    <p>
                      {hotelPhone ? `Phone: ${hotelPhone}` : ''}
                      {hotelPhone && hotelEmail ? ' | ' : ''}
                      {hotelEmail ? `Email: ${hotelEmail}` : ''}
                    </p>
                  )}
                  {hotelGstin && (
                    <p className="gstin-badge">{settings.tax_name || 'GST'}: {hotelGstin}</p>
                  )}
                </div>
              </div>

              <div className="invoice-meta-side">
                <div className="invoice-title-pill">TAX INVOICE</div>
                <div className="meta-line">
                  <span className="lbl">Invoice Number:</span>
                  <span className="val bold code">{invoice.invoice_number}</span>
                </div>
                <div className="meta-line">
                  <span className="lbl">Invoice Date:</span>
                  <span className="val">{formatDate(invoice.issue_date || invoice.created_at)}</span>
                </div>
                <div className="meta-line">
                  <span className="lbl">Reservation No:</span>
                  <span className="val bold code">{invoice.booking?.booking_number}</span>
                </div>
                <div className="meta-line">
                  <span className="lbl">Payment Status:</span>
                  <span
                    className={`status-pill ${
                      invoice.status === 'PAID'
                        ? 'confirmed'
                        : invoice.status === 'PARTIALLY_PAID'
                        ? 'pending'
                        : invoice.status === 'VOID'
                        ? 'cancelled'
                        : 'unpaid'
                    }`}
                  >
                    {invoice.status}
                  </span>
                </div>
              </div>
            </div>

            <hr className="invoice-divider" />

            {/* Bill To & Stay Summary */}
            <div className="invoice-parties-grid">
              <div className="party-card">
                <h4 className="party-title">BILLED TO</h4>
                <p className="guest-name-bold">{guestName}</p>
                <p>Phone: {invoice.guest?.phone || 'N/A'}</p>
                <p>Email: {invoice.guest?.email || 'N/A'}</p>
                {invoice.guest?.id_type && (
                  <p className="guest-id-text">
                    ID: {invoice.guest?.id_type} - {invoice.guest?.id_number || 'Confidential'}
                  </p>
                )}
              </div>

              <div className="party-card stay-summary">
                <h4 className="party-title">STAY DETAILS</h4>
                <div className="stay-meta-row">
                  <span>Check-In:</span>
                  <span className="bold">{formatDate(invoice.booking?.check_in_date)}</span>
                </div>
                <div className="stay-meta-row">
                  <span>Check-Out:</span>
                  <span className="bold">{formatDate(invoice.booking?.check_out_date)}</span>
                </div>
                <div className="stay-meta-row">
                  <span>Guests:</span>
                  <span>
                    {invoice.booking?.adults_count ?? 1} Adult(s), {invoice.booking?.children_count ?? 0} Children
                  </span>
                </div>
                <div className="stay-meta-row">
                  <span>Booking Source:</span>
                  <span>{invoice.booking?.booking_source}</span>
                </div>
              </div>
            </div>

            {/* Adjustment Form (Print Hidden) */}
            {showAdjForm && (
              <div className="invoice-adj-box print-hide mb-4 mt-3 p-3 rounded" style={{ background: 'var(--color-background-secondary)', border: '1px solid var(--color-border)' }}>
                <h4 className="font-semibold text-sm mb-2" style={{ color: 'var(--color-card-heading)' }}>
                  Add Line-Item Adjustment / Concession
                </h4>
                <form onSubmit={handleAddAdjustment}>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div className="form-group" style={{ margin: 0 }}>
                      <label className="text-xs">Type</label>
                      <select value={adjType} onChange={(e) => setAdjType(e.target.value)}>
                        <option value="DISCOUNT">Discount (-)</option>
                        <option value="SURCHARGE">Surcharge / Fee (+)</option>
                        <option value="WAIVER">Fee Waiver (-)</option>
                        <option value="CORRECTION">Correction (+/-)</option>
                      </select>
                    </div>
                    <div className="form-group" style={{ margin: 0 }}>
                      <label className="text-xs">Amount ({currencySymbol})</label>
                      <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0.00"
                        value={adjAmount}
                        onChange={(e) => setAdjAmount(e.target.value)}
                        required
                      />
                    </div>
                    <div className="form-group" style={{ margin: 0 }}>
                      <label className="text-xs">Audit Reason</label>
                      <input
                        type="text"
                        placeholder="e.g. VIP Manager discount..."
                        value={adjReason}
                        onChange={(e) => setAdjReason(e.target.value)}
                        required
                      />
                    </div>
                  </div>
                  <div style={{ display: 'flex', gap: '0.5rem' }}>
                    <button type="submit" disabled={submittingAdj} className="btn btn-primary btn-sm">
                      {submittingAdj ? 'Applying...' : 'Apply Adjustment'}
                    </button>
                    <button type="button" onClick={() => setShowAdjForm(false)} className="btn btn-secondary btn-sm">
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
            )}

            {/* Itemized Line Items Table */}
            <div className="invoice-table-section mt-4">
              <h4 className="section-label">ROOM CHARGES & SERVICES</h4>
              <table className="invoice-items-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th className="text-right">Qty / Nights</th>
                    <th className="text-right">Unit Rate</th>
                    <th className="text-right">Tax Rate</th>
                    <th className="text-right">Tax Amount</th>
                    <th className="text-right">Subtotal</th>
                    <th className="text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {(invoice.items || []).map((item, index) => (
                    <tr key={item.id || index}>
                      <td>{index + 1}</td>
                      <td className="bold">{item.description}</td>
                      <td>
                        <span className="item-type-badge">{item.item_type || item.type}</span>
                      </td>
                      <td className="text-right">{item.quantity}</td>
                      <td className="text-right">{formatCurrency(item.unit_price, currencySymbol)}</td>
                      <td className="text-right">{item.tax_rate_percent}%</td>
                      <td className="text-right">{formatCurrency(item.tax_amount, currencySymbol)}</td>
                      <td className="text-right">{formatCurrency(item.subtotal, currencySymbol)}</td>
                      <td className="text-right bold">{formatCurrency(item.total_amount, currencySymbol)}</td>
                    </tr>
                  ))}

                  {/* Adjustments */}
                  {(invoice.adjustments || []).map((adj, index) => (
                    <tr key={`adj-${adj.id || index}`} className="adj-row">
                      <td>•</td>
                      <td className="bold">{adj.description || adj.reason}</td>
                      <td>
                        <span className="item-type-badge adjustment">{adj.adjustment_type || 'ADJUSTMENT'}</span>
                      </td>
                      <td className="text-right">1</td>
                      <td className="text-right">{formatCurrency(adj.amount, currencySymbol)}</td>
                      <td className="text-right">0%</td>
                      <td className="text-right">{formatCurrency(0, currencySymbol)}</td>
                      <td className="text-right">{formatCurrency(adj.amount, currencySymbol)}</td>
                      <td className="text-right bold">
                        {adj.adjustment_type === 'DISCOUNT' ? '-' : '+'}
                        {formatCurrency(adj.amount, currencySymbol)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Invoice Totals Summary */}
            <div className="invoice-totals-wrapper mt-4">
              <div className="invoice-notes-block">
                <h5>Terms & Conditions</h5>
                <p>
                  {settings.invoice_footer_note ||
                    `Thank you for staying with us at ${hotelName}. All disputes subject to local jurisdiction.`}
                </p>
                <p className="mt-2 text-muted">
                  Check-out time is {settings.check_out_time || '11:00'}. Late check-outs must be authorized by front desk management.
                </p>
              </div>

              <div className="invoice-totals-table">
                <div className="total-line">
                  <span className="lbl">Base Subtotal:</span>
                  <span className="bold">{formatCurrency(invoice.subtotal, currencySymbol)}</span>
                </div>
                <div className="total-line">
                  <span className="lbl">Taxes & Levies ({settings.tax_name || 'GST'}):</span>
                  <span>{formatCurrency(invoice.tax_amount ?? invoice.tax_total, currencySymbol)}</span>
                </div>
                {Number(invoice.discount_amount ?? invoice.discount_total) > 0 && (
                  <div className="total-line discount">
                    <span className="lbl">Discounts Applied:</span>
                    <span>-{formatCurrency(invoice.discount_amount ?? invoice.discount_total, currencySymbol)}</span>
                  </div>
                )}
                {Number(invoice.surcharge_total) > 0 && (
                  <div className="total-line">
                    <span className="lbl">Surcharges / Fees:</span>
                    <span>+{formatCurrency(invoice.surcharge_total, currencySymbol)}</span>
                  </div>
                )}
                <div className="total-line grand-total">
                  <span className="lbl">Total Invoice Amount:</span>
                  <span className="grand-total-amount">{formatCurrency(invoice.total_amount, currencySymbol)}</span>
                </div>
                <div className="total-line paid">
                  <span className="lbl">Total Payments Received:</span>
                  <span className="bold text-forest-green">{formatCurrency(invoice.paid_amount, currencySymbol)}</span>
                </div>
                <div className="total-line balance-due">
                  <span className="lbl">Balance Due:</span>
                  <span className={`bold ${Number(invoice.balance_due) > 0 ? 'text-danger' : 'text-forest-green'}`}>
                    {formatCurrency(invoice.balance_due, currencySymbol)}
                  </span>
                </div>
              </div>
            </div>

            {/* Payment History Table */}
            {((invoice.payments && invoice.payments.length > 0) || (invoice.booking?.payments && invoice.booking.payments.length > 0)) && (
              <div className="payment-history-receipt-section mt-4">
                <h4 className="section-label">PAYMENTS APPLIED TO THIS FOLIO</h4>
                <table className="invoice-items-table mini">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Transaction Ref</th>
                      <th>Method</th>
                      <th>Type</th>
                      <th>Status</th>
                      <th className="text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(invoice.payments || invoice.booking.payments).map((pmt, idx) => (
                      <tr key={pmt.id || idx}>
                        <td>{formatDate(pmt.paid_at || pmt.created_at, true)}</td>
                        <td className="code">{pmt.transaction_reference || pmt.reference}</td>
                        <td>{pmt.payment_method || pmt.method}</td>
                        <td>{pmt.payment_type || pmt.type || 'ADVANCE'}</td>
                        <td>
                          <span className={`status-pill ${pmt.status === 'SUCCESS' || !pmt.status ? 'confirmed' : 'pending'}`}>
                            {pmt.status || 'SUCCESS'}
                          </span>
                        </td>
                        <td className="text-right bold text-forest-green">{formatCurrency(pmt.amount, currencySymbol)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {/* Signatures Footer */}
            <div className="invoice-signatures-footer mt-5">
              <div className="sig-block">
                <div className="sig-line" />
                <p className="sig-label">Guest Signature</p>
              </div>
              <div className="sig-block right">
                <div className="sig-line" />
                <p className="sig-label">Authorized Signatory — {hotelName}</p>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
