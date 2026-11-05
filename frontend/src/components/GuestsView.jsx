import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDateRange, formatCurrency } from '../utils/formatters';
import { isValidPhone, PHONE_ERROR_MSG, PHONE_INPUT_PROPS } from '../utils/validation';
import { GuestsIcon, SearchIcon, BookingsIcon, XIcon, EditIcon, PlusIcon } from './Icons';

export default function GuestsView({ onOpenBookingWithGuest }) {
  const { currencySymbol } = useHotelSettings();
  const [guests, setGuests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selectedGuest, setSelectedGuest] = useState(null);
  const [loadingGuestProfile, setLoadingGuestProfile] = useState(false);
  const [isAddGuestOpen, setIsAddGuestOpen] = useState(false);
  const [isEditGuestOpen, setIsEditGuestOpen] = useState(false);

  // Form fields
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');
  const [phoneError, setPhoneError] = useState('');
  const [email, setEmail] = useState('');
  const [idType, setIdType] = useState('AADHAAR');
  const [idNumber, setIdNumber] = useState('');
  const [city, setCity] = useState('');
  const [state, setState] = useState('');
  const [specialRequests, setSpecialRequests] = useState('');

  const [feedbackMsg, setFeedbackMsg] = useState({ type: '', text: '' });
  const [submitting, setSubmitting] = useState(false);

  // Body scroll lock and ESC key for guest modals
  useEffect(() => {
    const isModalOpen = isAddGuestOpen || isEditGuestOpen;
    if (isModalOpen) {
      document.body.style.overflow = 'hidden';
      const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
          setIsAddGuestOpen(false);
          setIsEditGuestOpen(false);
        }
      };
      window.addEventListener('keydown', handleKeyDown);
      return () => {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', handleKeyDown);
      };
    }
  }, [isAddGuestOpen, isEditGuestOpen]);

  const fetchGuests = () => {
    setLoading(true);
    hotelApi
      .getGuests({ search: search.trim(), per_page: 25 })
      .then((res) => {
        setGuests(res?.data || []);
      })
      .catch((err) => {
        setFeedbackMsg({
          type: 'error',
          text: err.response?.data?.message || 'Failed to fetch guests',
        });
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchGuests();
  }, [search]);

  const handleSelectGuest = async (guestId) => {
    setLoadingGuestProfile(true);
    try {
      const data = await hotelApi.getGuest(guestId);
      setSelectedGuest(data);
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to load guest details',
      });
    } finally {
      setLoadingGuestProfile(false);
    }
  };

  const handleCreateGuest = async (e) => {
    e.preventDefault();
    setFeedbackMsg({ type: '', text: '' });
    setPhoneError('');

    const cleanPhone = phone.trim();
    if (!firstName.trim() || !lastName.trim() || !cleanPhone) {
      setFeedbackMsg({ type: 'error', text: 'Please fill in all mandatory fields (marked with *).' });
      return;
    }

    if (!isValidPhone(cleanPhone)) {
      setPhoneError(PHONE_ERROR_MSG);
      setFeedbackMsg({ type: 'error', text: PHONE_ERROR_MSG });
      return;
    }

    setSubmitting(true);
    try {
      const created = await hotelApi.createGuest({
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        phone: cleanPhone,
        email: email.trim() || null,
        id_type: idType,
        id_number: idNumber.trim() || null,
        city: city.trim() || null,
        state: state.trim() || null,
        special_requests: specialRequests.trim() || null,
      });
      setFeedbackMsg({ type: 'success', text: `Guest "${firstName.trim()} ${lastName.trim()}" registered successfully!` });
      setIsAddGuestOpen(false);
      resetForm();
      fetchGuests();
      setSelectedGuest(created);
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to create guest',
      });
    } finally {
      setSubmitting(false);
    }
  };

  const openEditModal = () => {
    if (!selectedGuest) return;
    setFirstName(selectedGuest.first_name || '');
    setLastName(selectedGuest.last_name || '');
    setPhone(selectedGuest.phone || '');
    setPhoneError('');
    setEmail(selectedGuest.email || '');
    setIdType(selectedGuest.id_type || 'AADHAAR');
    setIdNumber(selectedGuest.id_number || '');
    setCity(selectedGuest.city || '');
    setState(selectedGuest.state || '');
    setSpecialRequests(selectedGuest.special_requests || '');
    setIsEditGuestOpen(true);
  };

  const handleUpdateGuest = async (e) => {
    e.preventDefault();
    if (!selectedGuest) return;
    setFeedbackMsg({ type: '', text: '' });
    setPhoneError('');

    const cleanPhone = phone.trim();
    if (!firstName.trim() || !lastName.trim() || !cleanPhone) {
      setFeedbackMsg({ type: 'error', text: 'Please fill in all mandatory fields (marked with *).' });
      return;
    }

    if (!isValidPhone(cleanPhone)) {
      setPhoneError(PHONE_ERROR_MSG);
      setFeedbackMsg({ type: 'error', text: PHONE_ERROR_MSG });
      return;
    }

    setSubmitting(true);
    try {
      const updated = await hotelApi.updateGuest(selectedGuest.id, {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        phone: cleanPhone,
        email: email.trim() || null,
        id_type: idType,
        id_number: idNumber.trim() || null,
        city: city.trim() || null,
        state: state.trim() || null,
        special_requests: specialRequests.trim() || null,
      });
      setFeedbackMsg({ type: 'success', text: 'Guest profile updated successfully.' });
      setIsEditGuestOpen(false);
      setSelectedGuest(updated);
      fetchGuests();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update guest',
      });
    } finally {
      setSubmitting(false);
    }
  };

  const resetForm = () => {
    setFirstName('');
    setLastName('');
    setPhone('');
    setPhoneError('');
    setEmail('');
    setIdType('AADHAAR');
    setIdNumber('');
    setCity('');
    setState('');
    setSpecialRequests('');
  };

  return (
    <div className="view-container">
      {/* Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Guest Registry & Profiles</h1>
          <p className="view-subtitle">
            Maintain guest records, stay histories, contact information and identity documents safely.
          </p>
        </div>

        <button
          type="button"
          onClick={() => {
            resetForm();
            setIsAddGuestOpen(true);
          }}
          className="btn btn-primary"
        >
          <GuestsIcon size={18} />
          <span>+ Register New Guest</span>
        </button>
      </div>

      {feedbackMsg.text && (
        <div className={`alert-box ${feedbackMsg.type}`}>{feedbackMsg.text}</div>
      )}

      {/* Main Layout: Left table, Right Profile Drawer */}
      <div className="guests-layout-grid">
        {/* Left Column: Guest Directory Table */}
        <div className="card table-card">
          <div className="card-header-row">
            <div className="search-input-wrapper" style={{ maxWidth: '320px' }}>
              <SearchIcon size={16} />
              <input
                type="text"
                placeholder="Search guest by name, phone or email..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <span className="total-badge">{guests.length} Registered Guests</span>
          </div>

          <div className="table-responsive guests-table-wrap">
            <table className="dashboard-table full guests-table">
              <thead>
                <tr>
                  <th style={{ width: '32%' }}>Guest Name</th>
                  <th style={{ width: '28%' }}>Contact Phone</th>
                  <th style={{ width: '22%' }} className="hide-on-compact">Email</th>
                  <th style={{ width: '18%' }}>Stays</th>
                  <th style={{ width: '80px', textAlign: 'center' }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan="5" className="text-center py-6">
                      <div className="spinner-large" />
                      <p>Loading guest directory...</p>
                    </td>
                  </tr>
                ) : guests.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="empty-row py-8">
                      No guests found matching search query.
                    </td>
                  </tr>
                ) : (
                  guests.map((g) => (
                    <tr
                      key={g.id}
                      className={selectedGuest?.id === g.id ? 'active-row' : ''}
                      onClick={() => handleSelectGuest(g.id)}
                      style={{ cursor: 'pointer' }}
                    >
                      <td className="name-cell bold text-truncate" title={`${g.first_name} ${g.last_name}`}>
                        {g.first_name} {g.last_name}
                      </td>
                      <td className="font-mono text-truncate" title={g.phone}>{g.phone}</td>
                      <td className="text-muted text-truncate hide-on-compact" title={g.email || '—'}>
                        {g.email || '—'}
                      </td>
                      <td>
                        <span className="badge-subtle">{g.bookings_count ?? 0} stays</span>
                      </td>
                      <td className="text-center">
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            handleSelectGuest(g.id);
                          }}
                          className="table-action-btn"
                        >
                          View
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* Mobile Compact Cards for Phone Viewports */}
          <div className="mobile-guest-cards-list">
            {loading ? (
              <div className="text-center py-6">
                <div className="spinner-large" />
                <p>Loading guests...</p>
              </div>
            ) : guests.length === 0 ? (
              <div className="empty-row py-6 text-center text-muted">
                No guests found.
              </div>
            ) : (
              guests.map((g) => (
                <div
                  key={g.id}
                  className={`mobile-guest-card ${selectedGuest?.id === g.id ? 'active-mobile-card' : ''}`}
                  onClick={() => handleSelectGuest(g.id)}
                >
                  <div className="mobile-guest-card-header">
                    <span className="font-bold text-sm text-heading">{g.first_name} {g.last_name}</span>
                    <span className="badge-subtle">{g.bookings_count ?? 0} stays</span>
                  </div>
                  <div className="mobile-guest-card-body">
                    <span className="font-mono text-xs">📞 {g.phone}</span>
                    {g.city && <span className="text-xs text-muted">📍 {g.city}</span>}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Right Column: Selected Guest Profile Drawer */}
        <div className="card guest-profile-drawer">
          {loadingGuestProfile ? (
            <div className="dashboard-loading py-12">
              <div className="spinner-large" />
              <p>Fetching guest history...</p>
            </div>
          ) : !selectedGuest ? (
            <div className="empty-state py-12 text-center">
              <GuestsIcon size={40} className="text-muted" />
              <h3 className="mt-2 font-semibold">Select a Guest</h3>
              <p className="text-muted text-sm">
                Click any guest row to view their contact details, identification documents and stay history.
              </p>
            </div>
          ) : (
            <div className="profile-details-wrap">
              <div className="profile-header-box">
                <div className="avatar-large">
                  {selectedGuest.first_name?.charAt(0)}
                  {selectedGuest.last_name?.charAt(0)}
                </div>
                <div>
                  <h2 className="profile-full-name">
                    {selectedGuest.first_name} {selectedGuest.last_name}
                  </h2>
                  <span className="profile-phone">{selectedGuest.phone}</span>
                </div>
              </div>

              <div className="profile-info-list">
                <div className="info-row">
                  <span className="lbl">Email:</span>
                  <span className="val">{selectedGuest.email || 'None registered'}</span>
                </div>
                <div className="info-row">
                  <span className="lbl">ID Document:</span>
                  <span className="val bold">
                    {selectedGuest.id_type || 'None'} —{' '}
                    <span className="font-mono text-forest-green">
                      {selectedGuest.id_number || 'Protected / Hidden'}
                    </span>
                  </span>
                </div>
                <div className="info-row">
                  <span className="lbl">City / State:</span>
                  <span className="val">
                    {selectedGuest.city || '—'}, {selectedGuest.state || '—'}
                  </span>
                </div>
                {selectedGuest.special_requests && (
                  <div className="info-row notes">
                    <span className="lbl">Special Preferences:</span>
                    <span className="val italic">{selectedGuest.special_requests}</span>
                  </div>
                )}
              </div>

              <div className="profile-actions-bar">
                <button
                  type="button"
                  onClick={openEditModal}
                  className="btn btn-secondary btn-sm"
                >
                  Edit Profile
                </button>
              </div>

              {/* Guest Stays History */}
              <div className="guest-history-section">
                <div className="section-title-sm">
                  <BookingsIcon size={16} />
                  <span>Stay History ({selectedGuest.bookings?.length || 0})</span>
                </div>

                {selectedGuest.bookings && selectedGuest.bookings.length > 0 ? (
                  <div className="history-cards-list">
                    {selectedGuest.bookings.map((b) => (
                      <div key={b.id} className="history-card-item">
                        <div className="item-header">
                          <span className="b-num bold font-mono">{b.booking_number}</span>
                          <span className={`status-pill ${b.status.toLowerCase()}`}>
                            {b.status}
                          </span>
                        </div>
                        <div className="item-dates">
                          <span>{formatDateRange(b.check_in_date, b.check_out_date)}</span>
                        </div>
                        <div className="item-total text-forest-green bold">
                          {formatCurrency(b.grand_total, currencySymbol)}
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted text-sm italic py-2">No past reservations recorded.</p>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* CREATE / EDIT GUEST MODAL */}
      {(isAddGuestOpen || isEditGuestOpen) && (
        <div
          className="modal-overlay"
          onClick={() => {
            setIsAddGuestOpen(false);
            setIsEditGuestOpen(false);
          }}
        >
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{isEditGuestOpen ? 'Edit Guest Profile' : 'Register New Guest'}</h3>
              <button
                type="button"
                onClick={() => {
                  setIsAddGuestOpen(false);
                  setIsEditGuestOpen(false);
                }}
                className="modal-close-btn"
                title="Close modal"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form
              onSubmit={isEditGuestOpen ? handleUpdateGuest : handleCreateGuest}
              className="modal-form"
            >
              <div className="modal-body-scrollable">
                <div className="form-grid-2">
                  <div className="form-field">
                    <label>First Name *</label>
                    <input
                      type="text"
                      placeholder="e.g. Rahul"
                      value={firstName}
                      onChange={(e) => setFirstName(e.target.value)}
                      required
                    />
                  </div>
                  <div className="form-field">
                    <label>Last Name *</label>
                    <input
                      type="text"
                      placeholder="e.g. Verma"
                      value={lastName}
                      onChange={(e) => setLastName(e.target.value)}
                      required
                    />
                  </div>
                </div>

                <div className="form-grid-2">
                  <div className="form-field">
                    <label>Phone Number * (10 Digits)</label>
                    <input
                      {...PHONE_INPUT_PROPS}
                      value={phone}
                      onChange={(e) => {
                        setPhone(e.target.value);
                        if (phoneError) setPhoneError('');
                      }}
                      required
                    />
                    {phoneError ? (
                      <span className="field-error-text">{phoneError}</span>
                    ) : (
                      <small className="form-field-hint">Indian 10-digit numeric mobile number.</small>
                    )}
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
                </div>

                <div className="form-grid-2">
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
                    <label>ID Number</label>
                    <input
                      type="text"
                      placeholder="Government ID number"
                      value={idNumber}
                      onChange={(e) => setIdNumber(e.target.value)}
                    />
                  </div>
                </div>

                <div className="form-grid-2">
                  <div className="form-field">
                    <label>City</label>
                    <input
                      type="text"
                      placeholder="City"
                      value={city}
                      onChange={(e) => setCity(e.target.value)}
                    />
                  </div>
                  <div className="form-field">
                    <label>State</label>
                    <input
                      type="text"
                      placeholder="State"
                      value={state}
                      onChange={(e) => setState(e.target.value)}
                    />
                  </div>
                </div>

                <div className="form-field">
                  <label>Special Requests / Stay Preferences</label>
                  <textarea
                    rows="2"
                    placeholder="e.g. Quiet room, late arrival, non-smoking..."
                    value={specialRequests}
                    onChange={(e) => setSpecialRequests(e.target.value)}
                  />
                </div>
              </div>

              <div className="modal-actions-row">
                <button
                  type="button"
                  onClick={() => {
                    setIsAddGuestOpen(false);
                    setIsEditGuestOpen(false);
                  }}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" disabled={submitting} className="btn btn-primary">
                  {submitting ? 'Saving...' : isEditGuestOpen ? 'Save Changes' : 'Register Guest'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

