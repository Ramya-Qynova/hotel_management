import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import { BillingIcon, XIcon } from './Icons';

export default function IssueRefundModal({ isOpen, onClose, payment, onRefundIssued }) {
  const { currencySymbol } = useHotelSettings();
  const [amount, setAmount] = useState('');
  const [reason, setReason] = useState('');
  const [refundMethod, setRefundMethod] = useState('CASH');
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
    if (isOpen && payment) {
      const alreadyRefunded = payment.refunds?.reduce((sum, r) => sum + Number(r.amount), 0) || 0;
      const refundableMax = Math.max(0, Math.round((Number(payment.amount) - alreadyRefunded) * 100) / 100);
      setAmount(refundableMax.toFixed(2));
      setReason('');
      setRefundMethod(payment.payment_method === 'ONLINE_GATEWAY' ? 'RAZORPAY_REFUND' : 'CASH');
      setErrorMessage('');
    }
  }, [isOpen, payment]);

  if (!isOpen || !payment) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount <= 0) {
      setErrorMessage('Please enter a valid refund amount.');
      return;
    }
    if (!reason.trim()) {
      setErrorMessage('Please provide a mandatory reason for this refund.');
      return;
    }

    setLoading(true);
    setErrorMessage('');
    try {
      const refund = await hotelApi.recordRefund(payment.id, {
        amount: Math.round(numAmount * 100) / 100,
        reason: reason.trim(),
        refund_method: refundMethod,
      });

      if (onRefundIssued) {
        onRefundIssued(refund);
      }
      onClose();
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to process refund.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="modal-card"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '480px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
      >
        <div className="modal-header" style={{ flexShrink: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
            <BillingIcon size={22} className="text-forest-green" />
            <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
              Issue Payment Refund
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
                <span className="text-muted">Original Payment:</span>
                <span className="code bold">{payment.transaction_reference}</span>
              </div>
              <div className="folio-row">
                <span className="text-muted">Payment Amount:</span>
                <span className="bold text-forest-green">{formatCurrency(payment.amount, currencySymbol)}</span>
              </div>
              <div className="folio-row">
                <span className="text-muted">Gateway / Method:</span>
                <span>{payment.payment_gateway || payment.payment_method}</span>
              </div>
            </div>

            <div className="form-group">
              <label>Refund Amount ({currencySymbol}) *</label>
              <div className="input-prefix-wrapper">
                <span className="input-prefix">{currencySymbol}</span>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  max={Number(payment.amount)}
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  required
                />
              </div>
            </div>

            <div className="form-group">
              <label>Refund Method *</label>
              <select value={refundMethod} onChange={(e) => setRefundMethod(e.target.value)}>
                <option value="CASH">Cash Drawer Out</option>
                <option value="ONLINE_GATEWAY">Original Gateway Reversal</option>
                <option value="BANK_TRANSFER">Bank Wire / Account Credit</option>
                <option value="RAZORPAY_REFUND">Razorpay Online Refund API</option>
              </select>
            </div>

            <div className="form-group">
              <label>Mandatory Audit Reason *</label>
              <textarea
                rows="3"
                placeholder="e.g. Guest checkout discrepancy, cancelled excursion, room rate adjustment..."
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                required
              />
            </div>
          </div>

          <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
            <button type="button" onClick={onClose} className="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" disabled={loading} className="btn btn-danger">
              {loading ? 'Processing...' : 'Authorize & Issue Refund'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
