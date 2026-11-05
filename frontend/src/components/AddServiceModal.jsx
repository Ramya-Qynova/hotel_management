import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import { ServicesIcon, XIcon, PlusIcon } from './Icons';

export default function AddServiceModal({ isOpen, onClose, booking, onServiceAdded }) {
  const { currencySymbol } = useHotelSettings();
  const [services, setServices] = useState([]);
  const [selectedServiceId, setSelectedServiceId] = useState('');
  const [selectedRoomId, setSelectedRoomId] = useState('');
  const [quantity, setQuantity] = useState(1);
  const [serviceDate, setServiceDate] = useState(new Date().toISOString().slice(0, 10));
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
    if (!isOpen) return;

    // Fetch active services
    hotelApi
      .getServices({ is_active: 1 })
      .then((data) => {
        setServices(data || []);
        if (data && data.length > 0) {
          setSelectedServiceId(data[0].id);
        }
      })
      .catch((err) => console.error('Failed to load services:', err));

    if (booking?.booking_rooms && booking.booking_rooms.length > 0) {
      setSelectedRoomId(booking.booking_rooms[0].room_id);
    }
    setQuantity(1);
    setServiceDate(new Date().toISOString().slice(0, 10));
    setNotes('');
    setErrorMessage('');
  }, [isOpen, booking]);

  if (!isOpen || !booking) return null;

  const currentService = services.find((s) => s.id === parseInt(selectedServiceId, 10));
  const unitPrice = currentService ? Number(currentService.price) : 0;
  const taxRate = currentService ? Number(currentService.tax_rate_percent) : 0;
  const subtotal = Math.round(unitPrice * (parseInt(quantity, 10) || 1) * 100) / 100;
  const taxAmount = Math.round(((subtotal * taxRate) / 100) * 100) / 100;
  const totalAmount = Math.round((subtotal + taxAmount) * 100) / 100;

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedServiceId) {
      setErrorMessage('Please select a hotel service.');
      return;
    }

    setLoading(true);
    setErrorMessage('');
    try {
      const added = await hotelApi.addServiceToBooking(booking.id, {
        service_id: parseInt(selectedServiceId, 10),
        room_id: selectedRoomId ? parseInt(selectedRoomId, 10) : undefined,
        quantity: parseInt(quantity, 10),
        service_date: serviceDate,
        notes: notes.trim() || undefined,
      });

      if (onServiceAdded) {
        onServiceAdded(added);
      }
      onClose();
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to add service to reservation.');
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
            <ServicesIcon size={22} className="text-forest-green" />
            <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
              Order Chargeable Service
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
                <span className="text-muted">Reservation Folio:</span>
                <span className="bold">{booking.booking_number}</span>
              </div>
              <div className="folio-row">
                <span className="text-muted">Guest Name:</span>
                <span className="font-semibold">
                  {booking.guest?.first_name} {booking.guest?.last_name}
                </span>
              </div>
            </div>

            <div className="form-group">
              <label>Select Service *</label>
              <select
                value={selectedServiceId}
                onChange={(e) => setSelectedServiceId(e.target.value)}
                required
                disabled={services.length === 0}
              >
                {services.length === 0 ? (
                  <option value="">No active services in catalog (configure in Services)</option>
                ) : (
                  services.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name} ({s.category.replace('_', ' ')}) — {formatCurrency(s.price, currencySymbol)} + {s.tax_rate_percent}% GST
                    </option>
                  ))
                )}
              </select>
            </div>

            {booking.booking_rooms && booking.booking_rooms.length > 1 && (
              <div className="form-group">
                <label>Deliver to Room</label>
                <select value={selectedRoomId} onChange={(e) => setSelectedRoomId(e.target.value)}>
                  {booking.booking_rooms.map((br) => (
                    <option key={br.room_id} value={br.room_id}>
                      Room {br.room?.room_number} ({br.room?.room_type?.name})
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="form-group">
                <label>Quantity *</label>
                <input
                  type="number"
                  min="1"
                  max="50"
                  value={quantity}
                  onChange={(e) => setQuantity(e.target.value)}
                  required
                />
              </div>
              <div className="form-group">
                <label>Service Date *</label>
                <input
                  type="date"
                  value={serviceDate}
                  onChange={(e) => setServiceDate(e.target.value)}
                  required
                />
              </div>
            </div>

            {currentService && (
              <div className="rate-calculation-preview mt-2 mb-3">
                <div className="rate-row">
                  <span>Unit Price:</span>
                  <span>{formatCurrency(unitPrice, currencySymbol)}</span>
                </div>
                <div className="rate-row">
                  <span>Subtotal ({quantity}x):</span>
                  <span>{formatCurrency(subtotal, currencySymbol)}</span>
                </div>
                <div className="rate-row">
                  <span>GST Tax ({taxRate}%):</span>
                  <span>+{formatCurrency(taxAmount, currencySymbol)}</span>
                </div>
                <div className="rate-row total-highlight">
                  <span className="font-semibold">Total to Add to Folio:</span>
                  <span className="bold text-forest-green" style={{ fontSize: '1.05rem' }}>
                    {formatCurrency(totalAmount, currencySymbol)}
                  </span>
                </div>
              </div>
            )}

            <div className="form-group">
              <label>Special Instructions / Guest Notes</label>
              <input
                type="text"
                placeholder="e.g. Extra spicy, extra hangers, deliver at 8:00 PM..."
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
              <PlusIcon size={16} />
              <span>{loading ? 'Adding...' : 'Add to Reservation Folio'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
