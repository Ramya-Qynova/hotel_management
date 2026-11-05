import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import { BillingIcon, XIcon, RupeeIcon } from './Icons';

export default function RecordPaymentModal({ isOpen, onClose, booking, onPaymentRecorded }) {
  const { currencySymbol } = useHotelSettings();
  const [amount, setAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('CASH');
  const [paymentType, setPaymentType] = useState('PARTIAL');
  const [transactionReference, setTransactionReference] = useState('');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(false);
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

  useEffect(() => {
    if (isOpen && booking) {
      const total = Number(booking.grand_total || 0);
      const paid = Number(booking.invoice?.paid_amount || 0);
      const balance = Math.max(0, Math.round((total - paid) * 100) / 100);
      setAmount(balance > 0 ? balance.toFixed(2) : total.toFixed(2));
      setPaymentMethod('CASH');
      setPaymentType(balance <= 0 ? 'FULL' : 'PARTIAL');
      setTransactionReference(`TXN-${Date.now().toString().slice(-6)}`);
      setNotes('');
      setErrorMessage('');
    }
  }, [isOpen, booking]);

  if (!isOpen || !booking) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount <= 0) {
      setErrorMessage('Please enter a valid amount greater than zero.');
      return;
    }

    setLoading(true);
    setErrorMessage('');
    try {
      const recorded = await hotelApi.recordPayment(booking.id, {
        amount: Math.round(numAmount * 100) / 100,
        payment_method: paymentMethod,
        payment_type: paymentType,
        transaction_reference: transactionReference.trim() || undefined,
        notes: notes.trim() || undefined,
        idempotency_key: `IDEMP-${booking.id}-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`,
      });

      if (onPaymentRecorded) {
        onPaymentRecorded(recorded);
      }
      onClose();
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to record payment.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="modal-card"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '520px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
      >
        <div className="modal-header" style={{ flexShrink: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
            <BillingIcon size={22} className="text-forest-green" />
            <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
              Record Folio Payment
            </h2>
          </div>
          <button type="button" onClick={onClose} className="modal-close-btn" title="Close modal" aria-label="Close">
            <XIcon size={16} />
          </button>
        </div>

        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}>
          <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
            {errorMessage && <div className="alert-box error mb-3">{errorMessage}</div>}

            <div className="folio-mini-card mb-4">
              <div className="folio-row">
                <span className="text-muted">Booking Reference:</span>
                <span className="bold">{booking.booking_number}</span>
              </div>
              <div className="folio-row">
                <span className="text-muted">Guest:</span>
                <span className="bold">
                  {booking.guest?.first_name} {booking.guest?.last_name}
                </span>
              </div>
              <div className="folio-row">
                <span className="text-muted">Total Reservation:</span>
                <span className="bold text-forest-green">{formatCurrency(booking.grand_total, currencySymbol)}</span>
              </div>
            </div>

            <div className="form-group">
              <label>Payment Amount ({currencySymbol}) *</label>
              <div className="input-prefix-wrapper">
                <span className="input-prefix">{currencySymbol}</span>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="0.00"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="form-group">
                <label>Payment Method *</label>
                <select value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)}>
                  <option value="CASH">Cash (Till In)</option>
                  <option value="CARD">Credit / Debit Card</option>
                  <option value="UPI">UPI / QR Code</option>
                  <option value="BANK_TRANSFER">Bank Wire / NEFT</option>
                </select>
              </div>
              <div className="form-group">
                <label>Payment Type *</label>
                <select value={paymentType} onChange={(e) => setPaymentType(e.target.value)}>
                  <option value="PARTIAL">Partial Settlement</option>
                  <option value="FULL">Full Settlement</option>
                  <option value="ADVANCE">Advance Deposit</option>
                </select>
              </div>
            </div>

            <div className="form-group">
              <label>Transaction / Voucher Reference</label>
              <input
                type="text"
                placeholder="e.g. TXN-102934 or UPI Ref..."
                value={transactionReference}
                onChange={(e) => setTransactionReference(e.target.value)}
              />
            </div>

            <div className="form-group">
              <label>Internal Cashier Notes</label>
              <input
                type="text"
                placeholder="Notes, room bill charge, cashier ID..."
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </div>
          </div>

          <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
            <button type="button" onClick={onClose} className="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" disabled={loading} className="btn btn-primary">
              <RupeeIcon size={16} />
              <span>{loading ? 'Recording...' : 'Confirm & Record Payment'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
