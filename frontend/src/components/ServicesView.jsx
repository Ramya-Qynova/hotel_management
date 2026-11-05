import React, { useState, useEffect, useCallback } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import {
  ServicesIcon,
  PlusIcon,
  SearchIcon,
  RefreshIcon,
  EditIcon,
  XIcon,
  UtensilsIcon,
  SparklesIcon,
  BedIcon,
  TagIcon,
  CheckCircleIcon,
  AlertCircleIcon,
  ArrowRightIcon,
} from './Icons';
import AddServiceModal from './AddServiceModal';

const CATEGORIES = [
  { id: 'ALL', label: 'All Services', icon: ServicesIcon },
  { id: 'FOOD', label: 'Food & Dining', icon: UtensilsIcon },
  { id: 'LAUNDRY', label: 'Laundry & Valet', icon: SparklesIcon },
  { id: 'EXTRA_BED', label: 'Extra Bed / Rollaway', icon: BedIcon },
  { id: 'OTHER', label: 'Other & Spa', icon: TagIcon },
];

export default function ServicesView({ user }) {
  const { currencySymbol } = useHotelSettings();
  const isOwnerOrAdmin = user?.role?.name === 'OWNER' || user?.role?.name === 'ADMIN';
  const [services, setServices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeCategory, setActiveCategory] = useState('ALL');
  const [searchQuery, setSearchQuery] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Modals
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [orderModalBooking, setOrderModalBooking] = useState(null);
  const [activeBookings, setActiveBookings] = useState([]);
  const [showOrderSelector, setShowOrderSelector] = useState(false);
  const [selectedBookingForOrder, setSelectedBookingForOrder] = useState('');

  // Form State
  const [formData, setFormData] = useState({
    name: '',
    category: 'FOOD',
    price: '',
    tax_rate_percent: 12.0,
    is_active: true,
    description: '',
  });

  // ESC key and body scroll lock for modals
  const isAnyModalOpen = isCreateOpen || Boolean(editingService) || showOrderSelector;
  useEffect(() => {
    if (!isAnyModalOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsCreateOpen(false);
        setEditingService(null);
        setShowOrderSelector(false);
      }
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isAnyModalOpen]);

  const fetchServices = useCallback(async () => {
    setLoading(true);
    setErrorMessage('');
    try {
      const params = {};
      if (activeCategory !== 'ALL') {
        params.category = activeCategory;
      }
      const data = await hotelApi.getServices(params);
      setServices(data || []);
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to load hotel services catalog.');
    } finally {
      setLoading(false);
    }
  }, [activeCategory]);

  useEffect(() => {
    fetchServices();
  }, [fetchServices]);

  // Load active checked-in or confirmed bookings for ordering
  const loadBookingsForOrder = async () => {
    try {
      const data = await hotelApi.getBookings({ per_page: 50 });
      const active = (data?.data || []).filter((b) => ['CONFIRMED', 'CHECKED_IN'].includes(b.status));
      setActiveBookings(active);
      if (active.length > 0) {
        setSelectedBookingForOrder(active[0].id);
      }
      setShowOrderSelector(true);
    } catch {
      alert('Could not fetch active reservations.');
    }
  };

  const handleCreateSubmit = async (e) => {
    e.preventDefault();
    try {
      const parsedPrice = parseFloat(formData.price) || 0;
      await hotelApi.createService({
        name: formData.name,
        category: formData.category,
        unit_price: parsedPrice,
        price: parsedPrice,
        tax_rate_percent: parseFloat(formData.tax_rate_percent),
        is_active: formData.is_active,
        description: formData.description,
      });
      setSuccessMessage('Service added to catalog successfully.');
      setIsCreateOpen(false);
      resetForm();
      fetchServices();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to create service.');
    }
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    if (!editingService) return;
    try {
      const parsedPrice = parseFloat(formData.price) || 0;
      await hotelApi.updateService(editingService.id, {
        name: formData.name,
        category: formData.category,
        unit_price: parsedPrice,
        price: parsedPrice,
        tax_rate_percent: parseFloat(formData.tax_rate_percent),
        is_active: formData.is_active,
        description: formData.description,
      });
      setSuccessMessage('Service updated successfully.');
      setEditingService(null);
      resetForm();
      fetchServices();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to update service.');
    }
  };

  const handleToggleActive = async (service) => {
    try {
      await hotelApi.toggleServiceActive(service.id);
      fetchServices();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to toggle service status.');
    }
  };

  const openEditModal = (service) => {
    setEditingService(service);
    setFormData({
      name: service.name,
      category: service.category,
      price: service.price,
      tax_rate_percent: service.tax_rate_percent,
      is_active: Boolean(service.is_active),
      description: service.description || '',
    });
  };

  const resetForm = () => {
    setFormData({
      name: '',
      category: 'FOOD',
      price: '',
      tax_rate_percent: 12.0,
      is_active: true,
      description: '',
    });
  };

  // Filtered by local search query
  const filteredServices = services.filter((s) => {
    if (!searchQuery.trim()) return true;
    const query = searchQuery.toLowerCase();
    return (
      s.name.toLowerCase().includes(query) ||
      s.category.toLowerCase().includes(query) ||
      (s.description && s.description.toLowerCase().includes(query))
    );
  });

  const getCategoryIcon = (category) => {
    switch (category) {
      case 'FOOD':
        return <UtensilsIcon size={14} />;
      case 'LAUNDRY':
        return <SparklesIcon size={14} />;
      case 'EXTRA_BED':
        return <BedIcon size={14} />;
      default:
        return <TagIcon size={14} />;
    }
  };

  return (
    <div className="view-container">
      {/* Page Title & Controls */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Hotel Services & Amenities Catalog</h1>
          <p className="view-subtitle">
            Configure chargeable guest services including in-room dining, laundry valet, extra bed rollaway, and airport transfers.
          </p>
        </div>

        <div className="view-header-actions">
          <button
            type="button"
            onClick={loadBookingsForOrder}
            className="btn btn-secondary"
          >
            <ServicesIcon size={16} />
            <span>Order for Reservation</span>
          </button>
          {isOwnerOrAdmin && (
            <button
              type="button"
              onClick={() => {
                resetForm();
                setIsCreateOpen(true);
              }}
              className="btn btn-primary"
            >
              <PlusIcon size={16} />
              <span>Add Chargeable Service</span>
            </button>
          )}
        </div>
      </div>

      {successMessage && (
        <div className="alert-box success mb-4">
          <CheckCircleIcon size={18} />
          <span style={{ flex: 1 }}>{successMessage}</span>
          <button type="button" className="close-alert-btn" onClick={() => setSuccessMessage('')}>
            <XIcon size={14} />
          </button>
        </div>
      )}

      {errorMessage && (
        <div className="alert-box error mb-4">
          <AlertCircleIcon size={18} />
          <span style={{ flex: 1 }}>{errorMessage}</span>
          <button type="button" className="close-alert-btn" onClick={() => setErrorMessage('')}>
            <XIcon size={14} />
          </button>
        </div>
      )}

      {/* Filter Tabs and Search Bar */}
      <div className="filter-bar">
        <div className="filter-pill-group">
          {CATEGORIES.map((cat) => {
            const Icon = cat.icon;
            const isActive = activeCategory === cat.id;
            return (
              <button
                key={cat.id}
                type="button"
                className={`filter-pill ${isActive ? 'active' : ''}`}
                onClick={() => setActiveCategory(cat.id)}
              >
                <Icon size={14} />
                <span>{cat.label}</span>
              </button>
            );
          })}
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
          <div className="search-input-wrapper">
            <SearchIcon size={16} className="search-icon-inside" />
            <input
              type="text"
              placeholder="Search service name, category, terms..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
            {searchQuery && (
              <button
                type="button"
                onClick={() => setSearchQuery('')}
                style={{ color: 'var(--color-muted)', padding: 0 }}
                title="Clear search"
              >
                <XIcon size={14} />
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={fetchServices}
            className="btn-icon-secondary"
            title="Refresh services list"
          >
            <RefreshIcon size={16} />
          </button>
        </div>
      </div>

      {/* Services Grid */}
      {loading ? (
        <div className="dashboard-loading" style={{ minHeight: '260px' }}>
          <div className="spinner-large" />
          <p>Loading hotel services catalog...</p>
        </div>
      ) : filteredServices.length === 0 ? (
        <div className="empty-state-card">
          <div className="empty-state-icon-wrapper">
            <ServicesIcon size={32} />
          </div>
          <h3 className="font-bold text-lg text-emerald-950">No Services Found</h3>
          <p className="text-muted" style={{ maxWidth: '420px', margin: '0 auto' }}>
            {searchQuery
              ? `No services matched "${searchQuery}". Try adjusting your search query or category filter.`
              : 'Add chargeable hospitality services to bill dining, airport pickups, and laundry to guest reservations.'}
          </p>
          {isOwnerOrAdmin && (
            <button
              type="button"
              onClick={() => {
                resetForm();
                setIsCreateOpen(true);
              }}
              className="btn btn-primary mt-3"
            >
              <PlusIcon size={16} />
              <span>Add First Service</span>
            </button>
          )}
        </div>
      ) : (
        <div className="services-cards-grid">
          {filteredServices.map((service) => (
            <div key={service.id} className={`service-card ${!service.is_active ? 'inactive' : ''}`}>
              <div className="service-card-header">
                <span className="service-category-tag">
                  {getCategoryIcon(service.category)}
                  <span>{service.category.replace('_', ' ')}</span>
                </span>
                <span className={`status-pill ${service.is_active ? 'confirmed' : 'cancelled'}`}>
                  <span className={`status-dot ${service.is_active ? 'active' : ''}`} />
                  {service.is_active ? 'Active' : 'Inactive'}
                </span>
              </div>

              <h3 className="service-title">{service.name}</h3>
              <p className="service-description">{service.description || 'No detailed policy specified for this offering.'}</p>

              <div className="service-pricing-row">
                <div>
                  <span className="price-lbl">Base Rate</span>
                  <span className="service-rate bold">
                    {formatCurrency(service.price, currencySymbol)}
                  </span>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <span className="price-lbl">Tax ({service.tax_rate_percent}%)</span>
                  <span className="service-tax">
                    +{formatCurrency((Number(service.price) * Number(service.tax_rate_percent)) / 100, currencySymbol)}
                  </span>
                </div>
              </div>

              {isOwnerOrAdmin && (
                <div className="service-card-actions">
                  <button
                    type="button"
                    onClick={() => openEditModal(service)}
                    className="btn btn-secondary btn-sm"
                  >
                    <EditIcon size={14} />
                    <span>Edit</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => handleToggleActive(service)}
                    className={`btn btn-sm ${service.is_active ? 'btn-danger-outline' : 'btn-success-outline'}`}
                  >
                    {service.is_active ? 'Deactivate' : 'Activate'}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Create / Edit Service Modal */}
      {(isCreateOpen || editingService) && (
        <div className="modal-overlay" onClick={() => { setIsCreateOpen(false); setEditingService(null); }} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '520px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                <ServicesIcon size={22} className="text-forest-green" />
                <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                  {editingService ? 'Edit Service Details' : 'Add Chargeable Hotel Service'}
                </h2>
              </div>
              <button
                type="button"
                onClick={() => { setIsCreateOpen(false); setEditingService(null); }}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>

            <form
              onSubmit={editingService ? handleEditSubmit : handleCreateSubmit}
              style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}
            >
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <div className="form-group">
                  <label>Service Name *</label>
                  <input
                    type="text"
                    placeholder="e.g. Continental Breakfast, Airport Pick-up, Laundry Service..."
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    required
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="form-group">
                    <label>Category *</label>
                    <select
                      value={formData.category}
                      onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                    >
                      <option value="FOOD">Food & Dining</option>
                      <option value="LAUNDRY">Laundry & Valet</option>
                      <option value="EXTRA_BED">Extra Bed / Rollaway</option>
                      <option value="OTHER">Other / Transportation / Spa</option>
                    </select>
                  </div>

                  <div className="form-group">
                    <label>Price ({currencySymbol}) *</label>
                    <div className="input-prefix-wrapper">
                      <span className="input-prefix">{currencySymbol}</span>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        value={formData.price}
                        onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="form-group">
                    <label>Tax Rate (%) *</label>
                    <div className="input-prefix-wrapper">
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={formData.tax_rate_percent}
                        onChange={(e) => setFormData({ ...formData, tax_rate_percent: e.target.value })}
                        required
                      />
                      <span className="input-prefix" style={{ borderLeft: '1px solid var(--card-border)', borderRight: 'none' }}>%</span>
                    </div>
                  </div>

                  <div className="form-group" style={{ justifyContent: 'center', paddingTop: '1.4rem' }}>
                    <label style={{ display: 'inline-flex', alignItems: 'center', cursor: 'pointer', gap: '0.65rem', margin: 0 }}>
                      <input
                        type="checkbox"
                        checked={formData.is_active}
                        onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                        style={{ width: '16px', height: '16px', cursor: 'pointer' }}
                      />
                      <span style={{ fontSize: '0.85rem', fontWeight: 600 }}>Active in Catalog</span>
                    </label>
                  </div>
                </div>

                <div className="form-group">
                  <label>Description & Service Terms</label>
                  <textarea
                    rows="3"
                    placeholder="Describe included items, operational hours, order terms..."
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  />
                </div>
              </div>

              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button
                  type="button"
                  onClick={() => { setIsCreateOpen(false); setEditingService(null); }}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  {editingService ? 'Save Service' : 'Add to Catalog'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Select Booking for Ordering Modal */}
      {showOrderSelector && (
        <div className="modal-overlay" onClick={() => setShowOrderSelector(false)} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '480px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <ServicesIcon size={20} className="text-forest-green" />
                <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                  Select Active Reservation
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setShowOrderSelector(false)}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}>
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <p className="text-muted text-sm mb-4">
                  Choose an active confirmed or checked-in reservation to assign a chargeable service to:
                </p>
                {activeBookings.length === 0 ? (
                  <div className="alert-box error mt-2">
                    <AlertCircleIcon size={16} />
                    <span>No active confirmed or checked-in reservations found.</span>
                  </div>
                ) : (
                  <div className="form-group">
                    <label>Active Reservation *</label>
                    <select
                      value={selectedBookingForOrder}
                      onChange={(e) => setSelectedBookingForOrder(e.target.value)}
                    >
                      {activeBookings.map((b) => (
                        <option key={b.id} value={b.id}>
                          {b.booking_number} — {b.guest?.first_name} {b.guest?.last_name} ({b.status})
                        </option>
                      ))}
                    </select>
                  </div>
                )}
              </div>

              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button type="button" onClick={() => setShowOrderSelector(false)} className="btn btn-secondary">
                  Cancel
                </button>
                <button
                  type="button"
                  disabled={!selectedBookingForOrder}
                  onClick={() => {
                    const b = activeBookings.find((x) => x.id === parseInt(selectedBookingForOrder, 10));
                    setShowOrderSelector(false);
                    setOrderModalBooking(b);
                  }}
                  className="btn btn-primary"
                >
                  <span>Proceed to Order</span>
                  <ArrowRightIcon size={16} />
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Order Service Modal */}
      {orderModalBooking && (
        <AddServiceModal
          isOpen={Boolean(orderModalBooking)}
          booking={orderModalBooking}
          onClose={() => setOrderModalBooking(null)}
          onServiceAdded={(_item) => {
            setSuccessMessage(`Service added to reservation ${orderModalBooking.booking_number}!`);
          }}
        />
      )}
    </div>
  );
}
