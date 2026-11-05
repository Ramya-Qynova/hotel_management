import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import { isValidPhone, PHONE_ERROR_MSG, PHONE_INPUT_PROPS } from '../utils/validation';
import { RoomsIcon, BookingsIcon, XIcon, PlusIcon } from './Icons';

export default function NewBookingModal({ isOpen, onClose, onBookingCreated }) {
  const { currencySymbol } = useHotelSettings();

  const getTodayStr = () => new Date().toISOString().split('T')[0];
  const getTomorrowStr = () => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().split('T')[0];
  };

  const [checkInDate, setCheckInDate] = useState(getTodayStr());
  const [checkOutDate, setCheckOutDate] = useState(getTomorrowStr());
  const [bookingSource, setBookingSource] = useState('WALK_IN');
  const [adultsCount, setAdultsCount] = useState(1);
  const [childrenCount, setChildrenCount] = useState(0);
  const [specialRequests, setSpecialRequests] = useState('');

  // Available rooms
  const [availableRooms, setAvailableRooms] = useState([]);
  const [selectedRoomIds, setSelectedRoomIds] = useState([]);
  const [loadingRooms, setLoadingRooms] = useState(false);

  // Rate calculation
  const [rateQuote, setRateQuote] = useState(null);
  const [calculatingRates, setCalculatingRates] = useState(false);

  // Guest mode: 'select' or 'create'
  const [guestMode, setGuestMode] = useState('create');
  const [existingGuests, setExistingGuests] = useState([]);
  const [selectedGuestId, setSelectedGuestId] = useState('');
  const [guestSearch, setGuestSearch] = useState('');

  // New guest fields
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [idType, setIdType] = useState('AADHAAR');
  const [idNumber, setIdNumber] = useState('');

  const [submitting, setSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  // ESC key and body scroll lock for booking modal
  useEffect(() => {
    if (!isOpen) return;
    document.body.style.overflow = 'hidden';
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, onClose]);

  // Fetch available rooms whenever dates change
  useEffect(() => {
    if (!isOpen) return;
    if (!checkInDate || !checkOutDate || checkOutDate <= checkInDate) {
      setAvailableRooms([]);
      if (checkInDate && checkOutDate && checkOutDate <= checkInDate) {
        setErrorMsg('Check-out date must be strictly after check-in date.');
      }
      return;
    }

    let isMounted = true;
    setLoadingRooms(true);
    setErrorMsg('');

    hotelApi
      .getAvailableRooms(checkInDate, checkOutDate)
      .then((rooms) => {
        if (!isMounted) return;
        setAvailableRooms(rooms);
        // Clean up selected rooms if they are no longer available
        setSelectedRoomIds((prev) => prev.filter((id) => rooms.some((r) => r.id === id)));
      })
      .catch((err) => {
        if (!isMounted) return;
        setErrorMsg(err.response?.data?.message || 'Error fetching available rooms');
      })
      .finally(() => {
        if (isMounted) setLoadingRooms(false);
      });

    return () => {
      isMounted = false;
    };
  }, [checkInDate, checkOutDate, isOpen]);

  // Load guests for dropdown
  useEffect(() => {
    if (!isOpen || guestMode !== 'select') return;
    hotelApi
      .getGuests({ per_page: 50, search: guestSearch })
      .then((res) => {
        setExistingGuests(res?.data || []);
      })
      .catch(() => {});
  }, [isOpen, guestMode, guestSearch]);

  // Calculate rate quotation when selected rooms change
  useEffect(() => {
    if (selectedRoomIds.length === 0 || !checkInDate || !checkOutDate || checkOutDate <= checkInDate) {
      setRateQuote(null);
      return;
    }

    let isMounted = true;
    setCalculatingRates(true);

    hotelApi
      .calculateRates(checkInDate, checkOutDate, selectedRoomIds)
      .then((quote) => {
        if (isMounted) setRateQuote(quote);
      })
      .catch((err) => {
        if (isMounted) {
          setErrorMsg(err.response?.data?.message || 'Failed to calculate rates');
          setRateQuote(null);
        }
      })
      .finally(() => {
        if (isMounted) setCalculatingRates(false);
      });

    return () => {
      isMounted = false;
    };
  }, [selectedRoomIds, checkInDate, checkOutDate]);

  if (!isOpen) return null;

  const handleRoomToggle = (roomId) => {
    setSelectedRoomIds((prev) =>
      prev.includes(roomId) ? prev.filter((id) => id !== roomId) : [...prev, roomId]
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrorMsg('');

    if (selectedRoomIds.length === 0) {
      setErrorMsg('Please select at least one available room.');
      return;
    }

    const payload = {
      check_in_date: checkInDate,
      check_out_date: checkOutDate,
      booking_source: bookingSource,
      room_ids: selectedRoomIds,
      adults_count: parseInt(adultsCount, 10),
      children_count: parseInt(childrenCount, 10),
      special_requests: specialRequests,
    };

    if (guestMode === 'select') {
      if (!selectedGuestId) {
        setErrorMsg('Please select a registered guest.');
        return;
      }
      payload.guest_id = selectedGuestId;
    } else {
      const cleanPhone = phone.trim();
      if (!firstName.trim() || !lastName.trim() || !cleanPhone) {
        setErrorMsg('Guest first name, last name, and phone number are required.');
        return;
      }
      if (!isValidPhone(cleanPhone)) {
        setErrorMsg(PHONE_ERROR_MSG);
        return;
      }
      payload.guest = {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        phone: cleanPhone,
        email: email.trim() || null,
        id_type: idType,
        id_number: idNumber.trim() || null,
      };
    }

    setSubmitting(true);
    try {
      const created = await hotelApi.createBooking(payload);
      if (onBookingCreated) {
        onBookingCreated(created);
      }
      onClose();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to create booking.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-card wide" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <div className="modal-title-row">
            <BookingsIcon size={22} className="text-forest-green" />
            <h2>Create New Reservation</h2>
          </div>
          <button type="button" onClick={onClose} className="modal-close-btn" title="Close modal (Esc)">
            <XIcon size={16} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="modal-form">
          <div className="modal-body-scrollable">
            {errorMsg && <div className="alert-box error mb-3">{errorMsg}</div>}

            {/* Step 1: Dates & Booking Source */}
            <div className="form-section">
              <h3 className="section-title">1. Dates & Channel</h3>
              <div className="form-grid-3">
                <div className="form-field">
                  <label>Check-in Date *</label>
                  <input
                    type="date"
                    value={checkInDate}
                    onChange={(e) => setCheckInDate(e.target.value)}
                    min={new Date().toISOString().split('T')[0]}
                    required
                  />
                </div>

                <div className="form-field">
                  <label>Check-out Date *</label>
                  <input
                    type="date"
                    value={checkOutDate}
                    onChange={(e) => setCheckOutDate(e.target.value)}
                    min={checkInDate || new Date().toISOString().split('T')[0]}
                    required
                  />
                </div>

                <div className="form-field">
                  <label>Booking Source</label>
                  <select
                    value={bookingSource}
                    onChange={(e) => setBookingSource(e.target.value)}
                  >
                    <option value="WALK_IN">Walk-In (Front Desk)</option>
                    <option value="ONLINE">Online Portal</option>
                  </select>
                </div>
              </div>

              <div className="form-grid-2" style={{ marginTop: '0.75rem' }}>
                <div className="form-field">
                  <label>Adults</label>
                  <input
                    type="number"
                    min="1"
                    max="20"
                    value={adultsCount}
                    onChange={(e) => setAdultsCount(e.target.value)}
                  />
                </div>
                <div className="form-field">
                  <label>Children</label>
                  <input
                    type="number"
                    min="0"
                    max="10"
                    value={childrenCount}
                    onChange={(e) => setChildrenCount(e.target.value)}
                  />
                </div>
              </div>
            </div>

            {/* Step 2: Live Room Selection */}
            <div className="form-section">
              <div className="section-header-row">
                <h3 className="section-title">2. Select Room(s)</h3>
                <span className="badge-subtle">
                  {selectedRoomIds.length} room(s) selected
                </span>
              </div>

              {loadingRooms ? (
                <div className="loading-state">Checking live availability for {checkInDate} to {checkOutDate}...</div>
              ) : availableRooms.length === 0 ? (
                <div className="alert-box warning">
                  <strong>No rooms available.</strong> None of the physical rooms are eligible for the requested stay dates ({checkInDate} to {checkOutDate}). All rooms are occupied, reserved, inactive, or under maintenance. Please adjust check-in/check-out dates.
                </div>
              ) : (
                <div className="available-rooms-grid">
                  {availableRooms.map((rm) => {
                    const isSelected = selectedRoomIds.includes(rm.id);
                    return (
                      <div
                        key={rm.id}
                        onClick={() => handleRoomToggle(rm.id)}
                        className={`room-select-chip ${isSelected ? 'selected' : ''}`}
                      >
                        <div className="chip-left">
                          <RoomsIcon size={16} />
                          <span className="room-num">{rm.room_number}</span>
                        </div>
                        <div className="chip-right">
                          <span className="room-cat">{rm.room_type?.name}</span>
                          <span className="room-price">
                            {formatCurrency(rm.room_type?.base_price_per_night || 0, currencySymbol)}/nt
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Step 3: Guest Details */}
            <div className="form-section">
              <div className="section-header-row">
                <h3 className="section-title">3. Guest Information</h3>
                <div className="mode-toggle-group">
                  <button
                    type="button"
                    onClick={() => setGuestMode('create')}
                    className={`toggle-tab ${guestMode === 'create' ? 'active' : ''}`}
                  >
                    New Guest
                  </button>
                  <button
                    type="button"
                    onClick={() => setGuestMode('select')}
                    className={`toggle-tab ${guestMode === 'select' ? 'active' : ''}`}
                  >
                    Existing Guest
                  </button>
                </div>
              </div>

              {guestMode === 'select' ? (
                <div className="form-grid-2">
                  <div className="form-field">
                    <label>Search Existing Guest</label>
                    <input
                      type="text"
                      placeholder="Search by name or phone..."
                      value={guestSearch}
                      onChange={(e) => setGuestSearch(e.target.value)}
                    />
                  </div>
                  <div className="form-field">
                    <label>Choose Guest *</label>
                    <select
                      value={selectedGuestId}
                      onChange={(e) => setSelectedGuestId(e.target.value)}
                      required
                    >
                      <option value="">-- Select Guest --</option>
                      {existingGuests.map((g) => (
                        <option key={g.id} value={g.id}>
                          {g.first_name} {g.last_name} ({g.phone})
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              ) : (
                <div className="form-grid-3">
                  <div className="form-field">
                    <label>First Name *</label>
                    <input
                      type="text"
                      placeholder="e.g. Ananya"
                      value={firstName}
                      onChange={(e) => setFirstName(e.target.value)}
                      required
                    />
                  </div>
                  <div className="form-field">
                    <label>Last Name *</label>
                    <input
                      type="text"
                      placeholder="e.g. Iyer"
                      value={lastName}
                      onChange={(e) => setLastName(e.target.value)}
                      required
                    />
                  </div>
                  <div className="form-field">
                    <label>Phone Number * (10 Digits)</label>
                    <input
                      {...PHONE_INPUT_PROPS}
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      required
                    />
                    <small className="form-field-hint">Indian 10-digit numeric mobile.</small>
                  </div>
                  <div className="form-field">
                    <label>Email Address</label>
                    <input
                      type="email"
                      placeholder="guest@example.com"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                  </div>
                  <div className="form-field">
                    <label>ID Document Type</label>
                    <select value={idType} onChange={(e) => setIdType(e.target.value)}>
                      <option value="AADHAAR">Aadhaar Card</option>
                      <option value="PASSPORT">Passport</option>
                      <option value="DRIVING_LICENSE">Driving License</option>
                      <option value="VOTER_ID">Voter ID</option>
                      <option value="OTHER">Other</option>
                    </select>
                  </div>
                  <div className="form-field">
                    <label>ID Document Number</label>
                    <input
                      type="text"
                      placeholder="ID Number"
                      value={idNumber}
                      onChange={(e) => setIdNumber(e.target.value)}
                    />
                  </div>
                </div>
              )}

              <div className="form-field" style={{ marginTop: '0.75rem' }}>
                <label>Special Requests (Optional)</label>
                <textarea
                  rows="2"
                  placeholder="High floor, extra towels, early check-in preference..."
                  value={specialRequests}
                  onChange={(e) => setSpecialRequests(e.target.value)}
                />
              </div>
            </div>

            {/* Step 4: Rate Calculation & Snapshot Summary */}
            {rateQuote && (
              <div className="form-section quote-summary-card">
                <h3 className="section-title">4. Financial Snapshot & Totals</h3>
                <div className="quote-grid">
                  <div className="quote-item">
                    <span className="q-label">Nights</span>
                    <span className="q-val">{rateQuote.nights}</span>
                  </div>
                  <div className="quote-item">
                    <span className="q-label">Base Room Charges</span>
                    <span className="q-val">{formatCurrency(rateQuote.subtotal, currencySymbol)}</span>
                  </div>
                  <div className="quote-item">
                    <span className="q-label">Taxes ({rateQuote.tax_rate_percent}%)</span>
                    <span className="q-val">{formatCurrency(rateQuote.tax_amount, currencySymbol)}</span>
                  </div>
                  <div className="quote-item highlight">
                    <span className="q-label">Grand Total</span>
                    <span className="q-val total">{formatCurrency(rateQuote.grand_total, currencySymbol)}</span>
                  </div>
                  <div className="quote-item">
                    <span className="q-label">Required Advance (50%)</span>
                    <span className="q-val advance">{formatCurrency(rateQuote.required_advance_amount, currencySymbol)}</span>
                  </div>
                </div>
              </div>
            )}
          </div>

          <div className="modal-actions-row">
            <button type="button" onClick={onClose} className="btn btn-secondary">
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting || selectedRoomIds.length === 0}
              className="btn btn-primary"
            >
              {submitting ? 'Confirming Reservation...' : `Confirm & Create Booking (${selectedRoomIds.length} Room${selectedRoomIds.length === 1 ? '' : 's'})`}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

