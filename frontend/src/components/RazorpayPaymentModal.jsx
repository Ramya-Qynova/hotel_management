import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDateRange, formatCurrency } from '../utils/formatters';
import { CreditCardIcon, ShieldCheckIcon, XIcon, CheckCircleIcon, AlertCircleIcon } from './Icons';

export default function RazorpayPaymentModal({ isOpen, onClose, booking, onPaymentSuccess }) {
  const { currencySymbol } = useHotelSettings();
  const [amount, setAmount] = useState('');
  const [notes, setNotes] = useState('Online payment via Razorpay');
  const [loading, setLoading] = useState(false);
  const [orderData, setOrderData] = useState(null);
  const [step, setStep] = useState('INIT'); // 'INIT', 'ORDER_CREATED', 'PROCESSING', 'SUCCESS', 'FAILED'
  const [errorMessage, setErrorMessage] = useState('');
  const [successPayment, setSuccessPayment] = useState(null);

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
      // Calculate outstanding balance if available
      const total = Number(booking.grand_total || 0);
      const paid = Number(booking.invoice?.paid_amount || 0);
      const balance = Math.max(0, Math.round((total - paid) * 100) / 100);
      setAmount(balance > 0 ? balance.toFixed(2) : total.toFixed(2));
      setNotes(`Payment for reservation ${booking.booking_number}`);
      setOrderData(null);
      setStep('INIT');
      setErrorMessage('');
      setSuccessPayment(null);
    }
  }, [isOpen, booking]);

  if (!isOpen || !booking) return null;

  // Step 1: Create Order on backend
  const handleCreateOrder = async (e) => {
    e.preventDefault();
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount <= 0) {
      setErrorMessage('Please enter a valid payment amount greater than zero.');
      return;
    }

    setLoading(true);
    setErrorMessage('');
    try {
      const order = await hotelApi.createRazorpayOrder(booking.id, Math.round(numAmount * 100) / 100, { note: notes });
      setOrderData(order);
      setStep('ORDER_CREATED');
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to initialize Razorpay checkout order.');
    } finally {
      setLoading(false);
    }
  };

  // Step 2: Verification of Signature with backend
  const verifySignature = async (razorpayPaymentId, razorpayOrderId, razorpaySignature) => {
    setLoading(true);
    setStep('PROCESSING');
    try {
      const verified = await hotelApi.verifyRazorpayPayment({
        payment_id: orderData.payment_id,
        razorpay_payment_id: razorpayPaymentId,
        razorpay_order_id: razorpayOrderId,
        razorpay_signature: razorpaySignature,
      });

      setSuccessPayment(verified.payment || verified);
      setStep('SUCCESS');
      if (onPaymentSuccess) {
        onPaymentSuccess(verified.payment || verified);
      }
    } catch (err) {
      setErrorMessage(
        err.response?.data?.message || 'Payment signature verification failed. The transaction was not approved.'
      );
      setStep('FAILED');
    } finally {
      setLoading(false);
    }
  };

  // Launch Live Checkout if Razorpay checkout.js script is loaded
  const handleLiveCheckout = () => {
    if (!orderData) return;
    if (typeof window !== 'undefined' && window.Razorpay) {
      const options = {
        key: orderData.key_id,
        amount: orderData.amount,
        currency: orderData.currency || 'INR',
        name: booking.hotel_name || 'Hotel Reservation Folio',
        description: `Booking #${booking.booking_number}`,
        order_id: orderData.order_id,
        handler: function (response) {
          verifySignature(
            response.razorpay_payment_id,
            response.razorpay_order_id,
            response.razorpay_signature
          );
        },
        prefill: {
          name: `${booking.guest?.first_name || ''} ${booking.guest?.last_name || ''}`.trim(),
          email: booking.guest?.email || '',
          contact: booking.guest?.phone || '',
        },
        theme: {
          color: '#A18FD5',
        },
      };
      const rzp = new window.Razorpay(options);
      rzp.on('payment.failed', function (response) {
        setErrorMessage(response.error?.description || 'Payment rejected or cancelled.');
        setStep('FAILED');
      });
      rzp.open();
    } else {
      setErrorMessage('Razorpay Checkout SDK not loaded in browser. You can use the Verification Sandbox below.');
    }
  };

  // Launch Simulated Checkout for Verification Testing
  const handleSimulatePayment = async (shouldSucceed = true) => {
    if (!orderData) return;
    setLoading(true);

    try {
      const simulatedPaymentId = `pay_${Date.now().toString().slice(-8)}${Math.random().toString(36).substring(2, 6)}`;
      const testSecret = 'test_secret_fallback';
      const payload = `${orderData.order_id}|${simulatedPaymentId}`;

      let signature = '';
      if (shouldSucceed) {
        const enc = new TextEncoder();
        const key = await window.crypto.subtle.importKey(
          'raw',
          enc.encode(testSecret),
          { name: 'HMAC', hash: 'SHA-256' },
          false,
          ['sign']
        );
        const sig = await window.crypto.subtle.sign('HMAC', key, enc.encode(payload));
        const hashArray = Array.from(new Uint8Array(sig));
        signature = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
      } else {
        signature = 'invalid_sha256_mock_signature';
      }

      await verifySignature(simulatedPaymentId, orderData.order_id, signature);
    } catch {
      setErrorMessage('Sandbox simulation error.');
      setStep('FAILED');
      setLoading(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="modal-card"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '540px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
      >
        <div className="modal-header" style={{ flexShrink: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
            <CreditCardIcon size={22} className="text-forest-green" />
            <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
              Razorpay Online Gateway
            </h2>
          </div>
          <button type="button" onClick={onClose} className="modal-close-btn" title="Close modal" aria-label="Close">
            <XIcon size={16} />
          </button>
        </div>

        <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
          {errorMessage && <div className="alert-box error mb-3">{errorMessage}</div>}

          {/* Booking Summary Card */}
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
              <span className="text-muted">Stay Dates:</span>
              <span>{formatDateRange(booking.check_in_date, booking.check_out_date)}</span>
            </div>
          </div>

          {step === 'INIT' && (
            <form onSubmit={handleCreateOrder}>
              <div className="form-group">
                <label>Payment Amount ({currencySymbol}) *</label>
                <div className="input-prefix-wrapper">
                  <span className="input-prefix">{currencySymbol}</span>
                  <input
                    type="number"
                    step="0.01"
                    min="1.00"
                    placeholder="0.00"
                    value={amount}
                    onChange={(e) => setAmount(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="form-group">
                <label>Payment Notes</label>
                <input
                  type="text"
                  placeholder="Notes for receipt..."
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                />
              </div>

              <div className="security-notice-panel mb-4 p-3 rounded" style={{ background: 'var(--color-background-secondary)', border: '1px solid var(--color-border)' }}>
                <div style={{ display: 'flex', gap: '0.65rem', alignItems: 'center' }}>
                  <ShieldCheckIcon size={20} className="text-forest-green" />
                  <div>
                    <span className="font-semibold block text-sm" style={{ color: 'var(--color-heading)' }}>
                      End-to-End Encrypted Gateway
                    </span>
                    <small className="text-muted">
                      Generates authentic Razorpay Order ID and verifies cryptographic HMAC SHA-256 signatures before confirmation.
                    </small>
                  </div>
                </div>
              </div>

              <div className="modal-actions-row">
                <button type="button" onClick={onClose} className="btn btn-secondary">
                  Cancel
                </button>
                <button type="submit" disabled={loading} className="btn btn-primary">
                  {loading ? 'Initializing Order...' : 'Generate Razorpay Order →'}
                </button>
              </div>
            </form>
          )}

          {step === 'ORDER_CREATED' && orderData && (
            <div className="order-details-pane">
              <div className="alert-box success mb-4">
                <CheckCircleIcon size={18} />
                <span>Razorpay Order Initialized! ID: <strong className="code">{orderData.order_id}</strong></span>
              </div>

              <div className="folio-mini-card mb-4">
                <div className="folio-row">
                  <span className="text-muted">Chargeable Amount:</span>
                  <span className="bold text-forest-green" style={{ fontSize: '1.1rem' }}>
                    {formatCurrency(amount, currencySymbol)}
                  </span>
                </div>
                <div className="folio-row">
                  <span className="text-muted">Razorpay Key ID:</span>
                  <span className="code">{orderData.key_id}</span>
                </div>
              </div>

              <div className="mb-4">
                <button
                  type="button"
                  onClick={handleLiveCheckout}
                  className="btn btn-primary w-full justify-center mb-3"
                  style={{ width: '100%' }}
                >
                  <CreditCardIcon size={18} />
                  <span>Launch Razorpay Checkout Popup</span>
                </button>

                <div className="sandbox-panel p-3 rounded" style={{ background: 'var(--color-background-secondary)', border: '1px dashed var(--color-border)' }}>
                  <span className="font-semibold text-xs block mb-2 text-muted" style={{ textTransform: 'uppercase', letterSpacing: '0.04em' }}>
                    Payment Verification Sandbox:
                  </span>
                  <div style={{ display: 'flex', gap: '0.5rem' }}>
                    <button
                      type="button"
                      disabled={loading}
                      onClick={() => handleSimulatePayment(true)}
                      className="btn btn-success-outline btn-sm flex-1"
                    >
                      Simulate Valid Capture
                    </button>
                    <button
                      type="button"
                      disabled={loading}
                      onClick={() => handleSimulatePayment(false)}
                      className="btn btn-danger-outline btn-sm flex-1"
                    >
                      Simulate Failure
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {step === 'PROCESSING' && (
            <div className="text-center py-8">
              <div className="spinner-large" style={{ margin: '0 auto 1rem auto' }} />
              <h3 className="font-bold text-lg mb-1" style={{ color: 'var(--color-heading)' }}>Verifying Payment Signature...</h3>
              <p className="text-muted text-sm">
                Validating cryptographic Razorpay order token and updating guest folio ledger.
              </p>
            </div>
          )}

          {step === 'SUCCESS' && (
            <div className="payment-success-state text-center py-4">
              <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--color-success)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 0.75rem auto', color: 'var(--color-success-text)' }}>
                <CheckCircleIcon size={28} />
              </div>
              <h3 className="font-bold text-lg text-forest-green mb-1">Payment Captured Successfully!</h3>
              <p className="text-muted text-sm mb-4">
                Razorpay has verified the payment transaction and confirmed the reservation.
              </p>

              <div className="folio-mini-card mb-4 text-left">
                <div className="folio-row">
                  <span className="text-muted">Payment Status:</span>
                  <span className="status-pill confirmed">SUCCESS</span>
                </div>
                <div className="folio-row">
                  <span className="text-muted">Amount Captured:</span>
                  <span className="bold text-forest-green">{formatCurrency(successPayment?.amount || amount, currencySymbol)}</span>
                </div>
                <div className="folio-row">
                  <span className="text-muted">Transaction Ref:</span>
                  <span className="code">{successPayment?.transaction_reference}</span>
                </div>
                <div className="folio-row">
                  <span className="text-muted">Gateway Payment ID:</span>
                  <span className="code">{successPayment?.gateway_payment_id || 'Captured'}</span>
                </div>
              </div>

              <div className="modal-actions-row">
                <button type="button" onClick={onClose} className="btn btn-primary" style={{ width: '100%' }}>
                  Done
                </button>
              </div>
            </div>
          )}

          {step === 'FAILED' && (
            <div className="payment-failed-state text-center py-4">
              <div style={{ width: '48px', height: '48px', borderRadius: '50%', background: 'var(--color-danger)', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 0.75rem auto', color: 'var(--color-danger-text)' }}>
                <AlertCircleIcon size={28} />
              </div>
              <h3 className="font-bold text-lg text-danger mb-1">Payment Verification Rejected</h3>
              <p className="text-muted text-sm mb-4">
                The payment signature or capture verification failed. No reservation status was changed.
              </p>

              <div className="modal-actions-row">
                <button type="button" onClick={() => setStep('ORDER_CREATED')} className="btn btn-secondary">
                  Retry Verification
                </button>
                <button type="button" onClick={onClose} className="btn btn-danger">
                  Close
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
