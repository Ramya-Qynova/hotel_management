import React, { useState, useEffect } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatCurrency } from '../utils/formatters';
import { WrenchIcon, PlusIcon, XIcon, EditIcon, TrashIcon, CheckCircleIcon, AlertCircleIcon } from './Icons';

export default function RoomsView({ user, onRoomChanged }) {
  const { currencySymbol } = useHotelSettings();
  const [activeSubTab, setActiveSubTab] = useState('inventory'); // 'inventory' | 'categories'

  const isOwnerOrAdmin = user?.role?.name === 'OWNER' || user?.role?.name === 'ADMIN';

  // Physical Rooms State
  const [rooms, setRooms] = useState([]);
  const [loadingRooms, setLoadingRooms] = useState(true);
  const [selectedCategoryFilter, setSelectedCategoryFilter] = useState('');
  const [selectedStatusFilter, setSelectedStatusFilter] = useState('');
  const [selectedActiveFilter, setSelectedActiveFilter] = useState('all'); // 'all' | 'active' | 'inactive'

  // Room Categories State
  const [roomTypes, setRoomTypes] = useState([]);
  const [loadingTypes, setLoadingTypes] = useState(true);

  // Modals
  const [isAddRoomOpen, setIsAddRoomOpen] = useState(false);
  const [isEditRoomOpen, setIsEditRoomOpen] = useState(false);
  const [editingRoom, setEditingRoom] = useState(null);
  const [isAddCategoryOpen, setIsAddCategoryOpen] = useState(false);
  const [isEditCategoryOpen, setIsEditCategoryOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState(null);
  const [isMaintenanceOpen, setIsMaintenanceOpen] = useState(false);
  const [maintenanceRoom, setMaintenanceRoom] = useState(null);

  // Form states - New Room
  const [newRoomNumber, setNewRoomNumber] = useState('');
  const [newRoomTypeId, setNewRoomTypeId] = useState('');
  const [newRoomFloor, setNewRoomFloor] = useState(1);
  const [newRoomNotes, setNewRoomNotes] = useState('');

  // Form states - Edit Room
  const [editRoomNumber, setEditRoomNumber] = useState('');
  const [editRoomTypeId, setEditRoomTypeId] = useState('');
  const [editRoomFloor, setEditRoomFloor] = useState(1);
  const [editRoomStatus, setEditRoomStatus] = useState('READY');
  const [editRoomIsActive, setEditRoomIsActive] = useState(true);
  const [editRoomNotes, setEditRoomNotes] = useState('');

  // Form states - Category
  const [catName, setCatName] = useState('');
  const [catPrice, setCatPrice] = useState('');
  const [catOccupancy, setCatOccupancy] = useState(2);
  const [catExtraPerson, setCatExtraPerson] = useState(0);
  const [catExtraBed, setCatExtraBed] = useState(0);
  const [catAmenities, setCatAmenities] = useState('WiFi, Air Conditioning, Hot Water, TV');
  const [catDesc, setCatDesc] = useState('');

  // Form states - Maintenance
  const [mStartDate, setMStartDate] = useState(new Date().toISOString().split('T')[0]);
  const [mEndDate, setMEndDate] = useState(
    new Date(Date.now() + 86400000 * 2).toISOString().split('T')[0]
  );
  const [mReason, setMReason] = useState('');
  const [mCost, setMCost] = useState(0);

  const [feedbackMsg, setFeedbackMsg] = useState({ type: '', text: '' });
  const [isSubmitting, setIsSubmitting] = useState(false);

  // ESC key and body scroll lock for all modals
  useEffect(() => {
    const isAnyOpen = isAddRoomOpen || isEditRoomOpen || isAddCategoryOpen || isEditCategoryOpen || isMaintenanceOpen;
    if (isAnyOpen) {
      document.body.style.overflow = 'hidden';
      const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
          setIsAddRoomOpen(false);
          setIsEditRoomOpen(false);
          setIsAddCategoryOpen(false);
          setIsEditCategoryOpen(false);
          setIsMaintenanceOpen(false);
        }
      };
      window.addEventListener('keydown', handleKeyDown);
      return () => {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', handleKeyDown);
      };
    }
  }, [isAddRoomOpen, isEditRoomOpen, isAddCategoryOpen, isEditCategoryOpen, isMaintenanceOpen]);

  // Fetch Rooms
  const fetchRooms = () => {
    setLoadingRooms(true);
    const params = {};
    if (selectedCategoryFilter) params.room_type_id = selectedCategoryFilter;
    if (selectedStatusFilter) params.operational_status = selectedStatusFilter;
    if (selectedActiveFilter === 'active') params.is_active = 1;
    if (selectedActiveFilter === 'inactive') params.is_active = 0;

    hotelApi
      .getRooms(params)
      .then((data) => setRooms(data || []))
      .catch((err) => {
        setFeedbackMsg({
          type: 'error',
          text: err.response?.data?.message || 'Failed to load rooms inventory',
        });
      })
      .finally(() => setLoadingRooms(false));
  };

  // Fetch Categories
  const fetchRoomTypes = () => {
    setLoadingTypes(true);
    hotelApi
      .getRoomTypes()
      .then((data) => setRoomTypes(data || []))
      .catch((err) => {
        setFeedbackMsg({
          type: 'error',
          text: err.response?.data?.message || 'Failed to load room categories',
        });
      })
      .finally(() => setLoadingTypes(false));
  };

  useEffect(() => {
    fetchRooms();
  }, [selectedCategoryFilter, selectedStatusFilter, selectedActiveFilter]);

  useEffect(() => {
    fetchRoomTypes();
  }, []);

  // Quick Operational Status Change
  const handleStatusChange = async (roomId, newStatus) => {
    try {
      await hotelApi.updateRoomStatus(roomId, newStatus);
      setRooms((prev) =>
        prev.map((r) => (r.id === roomId ? { ...r, operational_status: newStatus } : r))
      );
      setFeedbackMsg({ type: 'success', text: `Room status updated to ${newStatus}.` });
      if (onRoomChanged) onRoomChanged();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update status.',
      });
    }
  };

  // Toggle Active/Inactive Status (OWNER/ADMIN only)
  const handleToggleActive = async (room) => {
    if (!isOwnerOrAdmin) {
      setFeedbackMsg({
        type: 'error',
        text: 'Only hotel owners or administrators can activate or deactivate rooms.',
      });
      return;
    }

    const targetActive = !room.is_active;
    try {
      await hotelApi.toggleRoomActive(room.id, targetActive);
      setRooms((prev) =>
        prev.map((r) => (r.id === room.id ? { ...r, is_active: targetActive } : r))
      );
      setFeedbackMsg({
        type: 'success',
        text: `Room ${room.room_number} ${targetActive ? 'activated' : 'deactivated'} successfully.`,
      });
      if (onRoomChanged) onRoomChanged();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update room active status.',
      });
    }
  };

  // Open Edit Room Modal
  const openEditRoom = (room) => {
    setEditingRoom(room);
    setEditRoomNumber(room.room_number || '');
    setEditRoomTypeId(room.room_type_id || (roomTypes[0]?.id ?? ''));
    setEditRoomFloor(room.floor ?? 1);
    setEditRoomStatus(room.operational_status || 'READY');
    setEditRoomIsActive(Boolean(room.is_active));
    setEditRoomNotes(room.notes || '');
    setIsEditRoomOpen(true);
  };

  // Handle Edit Room Submit
  const handleUpdateRoom = async (e) => {
    e.preventDefault();
    if (!editingRoom || !editRoomNumber || !editRoomTypeId) return;
    setIsSubmitting(true);
    try {
      const updated = await hotelApi.updateRoom(editingRoom.id, {
        room_number: editRoomNumber.trim(),
        room_type_id: parseInt(editRoomTypeId, 10),
        floor: parseInt(editRoomFloor, 10),
        operational_status: editRoomStatus,
        is_active: editRoomIsActive,
        notes: editRoomNotes.trim() || null,
      });

      setRooms((prev) =>
        prev.map((r) => (r.id === editingRoom.id ? { ...r, ...updated } : r))
      );
      setFeedbackMsg({
        type: 'success',
        text: `Room ${editRoomNumber} updated successfully!`,
      });
      setIsEditRoomOpen(false);
      setEditingRoom(null);
      fetchRooms();
      if (onRoomChanged) onRoomChanged();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update room.',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  // Permanently Delete Room (OWNER/ADMIN only, fails safe if dependencies exist)
  const handleDeleteRoom = async (room) => {
    if (!isOwnerOrAdmin) {
      setFeedbackMsg({
        type: 'error',
        text: 'Only hotel owners or administrators can delete rooms.',
      });
      return;
    }

    const hasHistory =
      (room.booking_rooms_count || 0) > 0 ||
      (room.maintenances_count || 0) > 0 ||
      (room.booking_services_count || 0) > 0;

    if (hasHistory) {
      setFeedbackMsg({
        type: 'error',
        text: `Cannot delete Room ${room.room_number}: it has historical booking, service or maintenance records. Please deactivate it instead.`,
      });
      return;
    }

    if (
      !window.confirm(
        `Are you sure you want to permanently delete Room ${room.room_number}? This action cannot be undone.`
      )
    ) {
      return;
    }

    try {
      await hotelApi.deleteRoom(room.id);
      setRooms((prev) => prev.filter((r) => r.id !== room.id));
      setFeedbackMsg({
        type: 'success',
        text: `Room ${room.room_number} permanently deleted.`,
      });
      if (onRoomChanged) onRoomChanged();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to delete room.',
      });
    }
  };

  // Handle Add Room Submit
  const handleAddRoom = async (e) => {
    e.preventDefault();
    if (!newRoomNumber || !newRoomTypeId) return;
    setIsSubmitting(true);
    try {
      await hotelApi.createRoom({
        room_number: newRoomNumber.trim(),
        room_type_id: parseInt(newRoomTypeId, 10),
        floor: parseInt(newRoomFloor, 10),
        notes: newRoomNotes.trim() || null,
        operational_status: 'READY',
      });
      setFeedbackMsg({ type: 'success', text: `Room ${newRoomNumber} added successfully!` });
      setIsAddRoomOpen(false);
      setNewRoomNumber('');
      setNewRoomNotes('');
      fetchRooms();
      fetchRoomTypes();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to create room.',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  // Handle Add Category Submit
  const handleAddCategory = async (e) => {
    e.preventDefault();
    if (!catName || !catPrice) return;
    setIsSubmitting(true);

    const amenitiesArray = catAmenities
      .split(',')
      .map((a) => a.trim())
      .filter(Boolean);

    try {
      await hotelApi.createRoomType({
        name: catName.trim(),
        base_price_per_night: parseFloat(catPrice),
        max_occupancy: parseInt(catOccupancy, 10),
        extra_person_charge: parseFloat(catExtraPerson) || 0,
        extra_bed_charge: parseFloat(catExtraBed) || 0,
        amenities: amenitiesArray,
        description: catDesc.trim() || null,
        is_active: true,
      });
      setFeedbackMsg({ type: 'success', text: `Room category "${catName}" created!` });
      setIsAddCategoryOpen(false);
      resetCategoryForm();
      fetchRoomTypes();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to create category.',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const openEditCategory = (cat) => {
    setEditingCategory(cat);
    setCatName(cat.name);
    setCatPrice(cat.base_price_per_night);
    setCatOccupancy(cat.max_occupancy);
    setCatExtraPerson(cat.extra_person_charge || 0);
    setCatExtraBed(cat.extra_bed_charge || 0);
    setCatAmenities(Array.isArray(cat.amenities) ? cat.amenities.join(', ') : '');
    setCatDesc(cat.description || '');
    setIsEditCategoryOpen(true);
  };

  const handleUpdateCategory = async (e) => {
    e.preventDefault();
    if (!editingCategory) return;
    setIsSubmitting(true);

    const amenitiesArray = catAmenities
      .split(',')
      .map((a) => a.trim())
      .filter(Boolean);

    try {
      await hotelApi.updateRoomType(editingCategory.id, {
        name: catName.trim(),
        base_price_per_night: parseFloat(catPrice),
        max_occupancy: parseInt(catOccupancy, 10),
        extra_person_charge: parseFloat(catExtraPerson) || 0,
        extra_bed_charge: parseFloat(catExtraBed) || 0,
        amenities: amenitiesArray,
        description: catDesc.trim() || null,
      });
      setFeedbackMsg({ type: 'success', text: `Category "${catName}" updated!` });
      setIsEditCategoryOpen(false);
      resetCategoryForm();
      fetchRoomTypes();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update category.',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const resetCategoryForm = () => {
    setCatName('');
    setCatPrice('');
    setCatOccupancy(2);
    setCatExtraPerson(0);
    setCatExtraBed(0);
    setCatAmenities('WiFi, Air Conditioning, Hot Water, TV');
    setCatDesc('');
    setEditingCategory(null);
  };

  // Schedule Maintenance Submit
  const handleScheduleMaintenance = async (e) => {
    e.preventDefault();
    if (!maintenanceRoom || !mReason) return;
    setIsSubmitting(true);
    try {
      await hotelApi.createMaintenance({
        room_id: maintenanceRoom.id,
        start_date: mStartDate,
        end_date: mEndDate,
        reason: mReason.trim(),
        status: 'IN_PROGRESS',
        maintenance_cost: parseFloat(mCost) || 0,
      });
      setFeedbackMsg({
        type: 'success',
        text: `Maintenance scheduled for Room ${maintenanceRoom.room_number}.`,
      });
      setIsMaintenanceOpen(false);
      setMaintenanceRoom(null);
      setMReason('');
      fetchRooms();
    } catch (err) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to schedule maintenance.',
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="view-container">
      {/* Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Rooms & Category Inventory</h1>
          <p className="view-subtitle">
            Configure dynamic room categories (AC, Normal, Premium Suite), rates, features, and manage physical inventory.
          </p>
        </div>

        {isOwnerOrAdmin && (
          <div className="action-buttons-group">
            <button
              type="button"
              onClick={() => {
                if (roomTypes.length > 0) {
                  setNewRoomTypeId(roomTypes[0].id);
                }
                setIsAddRoomOpen(true);
              }}
              className="btn btn-secondary"
            >
              <PlusIcon size={16} />
              <span>Add Physical Room</span>
            </button>
            <button
              type="button"
              onClick={() => {
                resetCategoryForm();
                setIsAddCategoryOpen(true);
              }}
              className="btn btn-primary"
            >
              <PlusIcon size={16} />
              <span>New Room Category</span>
            </button>
          </div>
        )}
      </div>

      {feedbackMsg.text && (
        <div className={`alert-box ${feedbackMsg.type}`}>{feedbackMsg.text}</div>
      )}

      {/* Tabs */}
      <div className="sub-tabs-bar">
        <button
          type="button"
          onClick={() => setActiveSubTab('inventory')}
          className={`sub-tab-btn ${activeSubTab === 'inventory' ? 'active' : ''}`}
        >
          Physical Rooms Inventory ({rooms.length})
        </button>
        <button
          type="button"
          onClick={() => setActiveSubTab('categories')}
          className={`sub-tab-btn ${activeSubTab === 'categories' ? 'active' : ''}`}
        >
          Dynamic Room Categories ({roomTypes.length})
        </button>
      </div>

      {/* SUBTAB 1: PHYSICAL ROOMS */}
      {activeSubTab === 'inventory' && (
        <>
          {/* Filter Bar */}
          <div className="card filter-bar-card">
            <div className="filter-select-group">
              <label>Category:</label>
              <select
                value={selectedCategoryFilter}
                onChange={(e) => setSelectedCategoryFilter(e.target.value)}
              >
                <option value="">All Categories</option>
                {roomTypes.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="filter-select-group">
              <label>Status:</label>
              <select
                value={selectedStatusFilter}
                onChange={(e) => setSelectedStatusFilter(e.target.value)}
              >
                <option value="">All Statuses</option>
                <option value="READY">READY</option>
                <option value="CLEANING">CLEANING</option>
                <option value="MAINTENANCE">MAINTENANCE</option>
                <option value="OUT_OF_SERVICE">OUT_OF_SERVICE</option>
              </select>
            </div>

            <div className="filter-select-group">
              <label>Inventory:</label>
              <select
                value={selectedActiveFilter}
                onChange={(e) => setSelectedActiveFilter(e.target.value)}
              >
                <option value="all">All Rooms</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
              </select>
            </div>
          </div>

          {/* Rooms Table */}
          <div className="card table-card">
            <div className="table-responsive">
              <table className="dashboard-table full">
                <thead>
                  <tr>
                    <th>Room #</th>
                    <th>Category</th>
                    <th>Floor</th>
                    <th>Nightly Rate</th>
                    <th>Operational Status</th>
                    <th>Inventory Status</th>
                    <th>Current Stay</th>
                    <th>Notes</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loadingRooms ? (
                    <tr>
                      <td colSpan="9" className="text-center py-6">
                        <div className="spinner-large" />
                        <p>Loading rooms inventory...</p>
                      </td>
                    </tr>
                  ) : rooms.length === 0 ? (
                    <tr>
                      <td colSpan="9" className="empty-row py-8">
                        No physical rooms found matching selected filters. Click "+ Add Physical Room" to create one.
                      </td>
                    </tr>
                  ) : (
                    rooms.map((rm) => {
                      const hasHistory =
                        (rm.booking_rooms_count || 0) > 0 ||
                        (rm.maintenances_count || 0) > 0 ||
                        (rm.booking_services_count || 0) > 0;
                      const activeBooking = rm.booking_rooms && rm.booking_rooms.length > 0 ? rm.booking_rooms[0] : null;

                      return (
                        <tr key={rm.id}>
                          <td className="code-cell font-mono bold">{rm.room_number}</td>
                          <td className="bold">{rm.room_type?.name}</td>
                          <td>Floor {rm.floor}</td>
                          <td className="bold text-forest-green">{formatCurrency(rm.room_type?.base_price_per_night || 0, currencySymbol)}</td>
                          <td>
                            <select
                              value={rm.operational_status}
                              onChange={(e) => handleStatusChange(rm.id, e.target.value)}
                              className={`status-select ${rm.operational_status.toLowerCase()}`}
                            >
                              <option value="READY">READY (Clean & Available)</option>
                              <option value="CLEANING">CLEANING (Housekeeping)</option>
                              <option value="MAINTENANCE">MAINTENANCE (Repair)</option>
                              <option value="OUT_OF_SERVICE">OUT_OF_SERVICE</option>
                            </select>
                          </td>
                          <td>
                            <span className={`status-pill ${rm.is_active ? 'confirmed' : 'cancelled'}`}>
                              <span className={`status-dot ${rm.is_active ? 'active' : ''}`} />
                              {rm.is_active ? 'Active' : 'Inactive'}
                            </span>
                          </td>
                          <td>
                            {activeBooking ? (
                              <span
                                className="stay-badge occupied"
                                title={`Stay: ${activeBooking.check_in_date} to ${activeBooking.check_out_date}`}
                              >
                                Occupied ({activeBooking.booking?.guest?.first_name || 'Guest'})
                              </span>
                            ) : (
                              <span className="text-muted text-xs">Vacant</span>
                            )}
                          </td>
                          <td className="text-muted text-xs" style={{ maxWidth: '140px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={rm.notes || ''}>
                            {rm.notes || '—'}
                          </td>
                          <td>
                            <div className="action-button-row">
                              {isOwnerOrAdmin && (
                                <button
                                  type="button"
                                  onClick={() => openEditRoom(rm)}
                                  className="btn btn-secondary btn-xs"
                                  title="Edit Room Details"
                                >
                                  <EditIcon size={13} />
                                  <span>Edit</span>
                                </button>
                              )}

                              {isOwnerOrAdmin && (
                                <button
                                  type="button"
                                  onClick={() => handleToggleActive(rm)}
                                  className={`btn ${rm.is_active ? 'btn-secondary' : 'btn-success-outline'} btn-xs`}
                                  title={rm.is_active ? 'Deactivate room (prevent new bookings)' : 'Activate room for bookings'}
                                >
                                  <span>{rm.is_active ? 'Deactivate' : 'Activate'}</span>
                                </button>
                              )}

                              {isOwnerOrAdmin && (
                                hasHistory ? (
                                  <button
                                    type="button"
                                    disabled
                                    className="btn btn-danger-outline btn-xs btn-disabled opacity-50 cursor-not-allowed"
                                    title="Cannot delete: room has historical booking, invoice or maintenance records. Please deactivate instead."
                                  >
                                    <TrashIcon size={13} />
                                    <span>Delete</span>
                                  </button>
                                ) : (
                                  <button
                                    type="button"
                                    onClick={() => handleDeleteRoom(rm)}
                                    className="btn btn-danger-outline btn-xs"
                                    title="Permanently delete unused room"
                                  >
                                    <TrashIcon size={13} />
                                    <span>Delete</span>
                                  </button>
                                )
                              )}

                              <button
                                type="button"
                                onClick={() => {
                                  setMaintenanceRoom(rm);
                                  setIsMaintenanceOpen(true);
                                }}
                                className="btn btn-secondary btn-xs"
                                title="Schedule Maintenance"
                              >
                                <WrenchIcon size={13} />
                                <span>Maint</span>
                              </button>
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}

      {/* SUBTAB 2: ROOM CATEGORIES */}
      {activeSubTab === 'categories' && (
        <div className="categories-grid">
          {loadingTypes ? (
            <div className="dashboard-loading col-span-full">
              <div className="spinner-large" />
              <p>Loading dynamic room categories...</p>
            </div>
          ) : roomTypes.length === 0 ? (
            <div className="card col-span-full py-8 text-center">
              <p>No room categories defined. Click "+ New Room Category" to start.</p>
            </div>
          ) : (
            roomTypes.map((cat) => (
              <div key={cat.id} className="card category-detail-card">
                <div className="cat-card-header">
                  <div>
                    <h3 className="cat-card-title">{cat.name}</h3>
                    <span className="cat-slug-sub">slug: {cat.slug}</span>
                  </div>
                  <span className="cat-price-pill">
                    {formatCurrency(cat.base_price_per_night, currencySymbol)}/night
                  </span>
                </div>

                <p className="cat-card-desc">{cat.description || 'No description provided.'}</p>

                <div className="cat-meta-row">
                  <div className="meta-item">
                    <span className="lbl">Max Occupancy:</span>
                    <span className="val">{cat.max_occupancy} Guests</span>
                  </div>
                  <div className="meta-item">
                    <span className="lbl">Physical Rooms:</span>
                    <span className="val bold text-forest-green">{cat.rooms_count ?? 0}</span>
                  </div>
                  <div className="meta-item">
                    <span className="lbl">Extra Person:</span>
                    <span className="val">
                      {formatCurrency(cat.extra_person_charge || 0, currencySymbol)}
                    </span>
                  </div>
                </div>

                <div className="cat-amenities-section">
                  <span className="amenities-title">Configured Amenities:</span>
                  <div className="amenities-tags-wrap">
                    {Array.isArray(cat.amenities) && cat.amenities.length > 0 ? (
                      cat.amenities.map((am, i) => (
                        <span key={i} className="amenity-tag">
                          ✓ {am}
                        </span>
                      ))
                    ) : (
                      <span className="text-muted italic">Standard essentials</span>
                    )}
                  </div>
                </div>

                {isOwnerOrAdmin && (
                  <div className="cat-card-footer">
                    <button
                      type="button"
                      onClick={() => openEditCategory(cat)}
                      className="btn btn-secondary btn-sm"
                    >
                      <EditIcon size={14} />
                      <span>Edit Pricing & Features</span>
                    </button>
                  </div>
                )}
              </div>
            ))
          )}
        </div>
      )}

      {/* MODAL 1: ADD PHYSICAL ROOM */}
      {isAddRoomOpen && (
        <div className="modal-overlay" onClick={() => setIsAddRoomOpen(false)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Add Physical Room to Inventory</h3>
              <button
                type="button"
                onClick={() => setIsAddRoomOpen(false)}
                className="modal-close-btn"
                title="Close modal"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form onSubmit={handleAddRoom} className="modal-form">
              <div className="form-field">
                <label>Room Number *</label>
                <input
                  type="text"
                  placeholder="e.g. AC-106, N-206, P-301..."
                  value={newRoomNumber}
                  onChange={(e) => setNewRoomNumber(e.target.value)}
                  required
                />
              </div>

              <div className="form-field">
                <label>Room Category *</label>
                <select
                  value={newRoomTypeId}
                  onChange={(e) => setNewRoomTypeId(e.target.value)}
                  required
                >
                  {roomTypes.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name} ({formatCurrency(t.base_price_per_night, currencySymbol)}/nt)
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-field">
                <label>Floor *</label>
                <input
                  type="number"
                  min="-1"
                  max="50"
                  value={newRoomFloor}
                  onChange={(e) => setNewRoomFloor(e.target.value)}
                  required
                />
              </div>

              <div className="form-field">
                <label>Notes / Orientation</label>
                <input
                  type="text"
                  placeholder="e.g. East Wing, Mountain View, Twin Beds..."
                  value={newRoomNotes}
                  onChange={(e) => setNewRoomNotes(e.target.value)}
                />
              </div>

              <div className="modal-actions-row">
                <button
                  type="button"
                  onClick={() => setIsAddRoomOpen(false)}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" disabled={isSubmitting} className="btn btn-primary">
                  {isSubmitting ? 'Saving...' : 'Add Room'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 1B: EDIT PHYSICAL ROOM */}
      {isEditRoomOpen && editingRoom && (
        <div className="modal-overlay" onClick={() => setIsEditRoomOpen(false)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div className="modal-title-row">
                <EditIcon size={20} className="text-forest-green" />
                <h3>Edit Physical Room {editingRoom.room_number}</h3>
              </div>
              <button
                type="button"
                onClick={() => setIsEditRoomOpen(false)}
                className="modal-close-btn"
                title="Close modal"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form onSubmit={handleUpdateRoom} className="modal-form">
              <div className="modal-body-scrollable">
                <div className="form-field">
                  <label>Room Number *</label>
                  <input
                    type="text"
                    value={editRoomNumber}
                    onChange={(e) => setEditRoomNumber(e.target.value)}
                    required
                  />
                </div>

                <div className="form-field">
                  <label>Room Category *</label>
                  <select
                    value={editRoomTypeId}
                    onChange={(e) => setEditRoomTypeId(e.target.value)}
                    required
                  >
                    {roomTypes.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.name} ({formatCurrency(t.base_price_per_night, currencySymbol)}/nt)
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-field">
                  <label>Floor *</label>
                  <input
                    type="number"
                    min="-2"
                    max="100"
                    value={editRoomFloor}
                    onChange={(e) => setEditRoomFloor(e.target.value)}
                    required
                  />
                </div>

                <div className="form-field">
                  <label>Operational Status *</label>
                  <select
                    value={editRoomStatus}
                    onChange={(e) => setEditRoomStatus(e.target.value)}
                    required
                  >
                    <option value="READY">READY (Clean & Available)</option>
                    <option value="CLEANING">CLEANING (Housekeeping)</option>
                    <option value="MAINTENANCE">MAINTENANCE (Repair)</option>
                    <option value="OUT_OF_SERVICE">OUT_OF_SERVICE</option>
                  </select>
                </div>

                <div className="form-field">
                  <label>Inventory Active Status *</label>
                  <select
                    value={editRoomIsActive ? '1' : '0'}
                    onChange={(e) => setEditRoomIsActive(e.target.value === '1')}
                    disabled={!isOwnerOrAdmin}
                    required
                  >
                    <option value="1">Active (Available for booking)</option>
                    <option value="0">Inactive (Hidden from booking)</option>
                  </select>
                  {!isOwnerOrAdmin && (
                    <small className="form-field-hint">Only OWNER or ADMIN can activate or deactivate rooms.</small>
                  )}
                </div>

                <div className="form-field">
                  <label>Notes / Orientation</label>
                  <input
                    type="text"
                    value={editRoomNotes}
                    onChange={(e) => setEditRoomNotes(e.target.value)}
                    placeholder="e.g. East Wing, Mountain View, Twin Beds..."
                  />
                </div>
              </div>

              <div className="modal-actions-row">
                <button
                  type="button"
                  onClick={() => setIsEditRoomOpen(false)}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" disabled={isSubmitting} className="btn btn-primary">
                  {isSubmitting ? 'Saving...' : 'Save Changes'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 2: ADD / EDIT ROOM CATEGORY */}
      {(isAddCategoryOpen || isEditCategoryOpen) && (
        <div
          className="modal-overlay"
          onClick={() => {
            setIsAddCategoryOpen(false);
            setIsEditCategoryOpen(false);
          }}
        >
          <div className="modal-card wide" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>
                {isEditCategoryOpen
                  ? `Edit Category: ${editingCategory?.name}`
                  : 'Create Dynamic Room Category'}
              </h3>
              <button
                type="button"
                onClick={() => {
                  setIsAddCategoryOpen(false);
                  setIsEditCategoryOpen(false);
                }}
                className="modal-close-btn"
                title="Close modal"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form
              onSubmit={isEditCategoryOpen ? handleUpdateCategory : handleAddCategory}
              className="modal-form"
            >
              <div className="form-grid-2">
                <div className="form-field">
                  <label>Category Name *</label>
                  <input
                    type="text"
                    placeholder="e.g. Premium Suite, Deluxe AC, Normal Room..."
                    value={catName}
                    onChange={(e) => setCatName(e.target.value)}
                    required
                  />
                </div>
                <div className="form-field">
                  <label>Base Price Per Night (₹) *</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="e.g. 3500.00"
                    value={catPrice}
                    onChange={(e) => setCatPrice(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="form-grid-3">
                <div className="form-field">
                  <label>Standard Occupancy *</label>
                  <input
                    type="number"
                    min="1"
                    max="10"
                    value={catOccupancy}
                    onChange={(e) => setCatOccupancy(e.target.value)}
                    required
                  />
                </div>
                <div className="form-field">
                  <label>Extra Person Charge (₹)</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={catExtraPerson}
                    onChange={(e) => setCatExtraPerson(e.target.value)}
                  />
                </div>
                <div className="form-field">
                  <label>Extra Bed Charge (₹)</label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={catExtraBed}
                    onChange={(e) => setCatExtraBed(e.target.value)}
                  />
                </div>
              </div>

              <div className="form-field">
                <label>Configured Amenities & Features (comma-separated)</label>
                <input
                  type="text"
                  placeholder="WiFi, Air Conditioning, Hot Water, King Bed, City View Balcony, Mini Bar..."
                  value={catAmenities}
                  onChange={(e) => setCatAmenities(e.target.value)}
                />
                <span className="field-hint">
                  Features are independent of hotel billing services.
                </span>
              </div>

              <div className="form-field">
                <label>Description</label>
                <textarea
                  rows="3"
                  placeholder="Detailed description of room furnishings, view, and layout..."
                  value={catDesc}
                  onChange={(e) => setCatDesc(e.target.value)}
                />
              </div>

              <div className="modal-actions-row">
                <button
                  type="button"
                  onClick={() => {
                    setIsAddCategoryOpen(false);
                    setIsEditCategoryOpen(false);
                  }}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" disabled={isSubmitting} className="btn btn-primary">
                  {isSubmitting
                    ? 'Saving...'
                    : isEditCategoryOpen
                    ? 'Update Category'
                    : 'Create Category'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 3: SCHEDULE MAINTENANCE */}
      {isMaintenanceOpen && maintenanceRoom && (
        <div className="modal-overlay" onClick={() => setIsMaintenanceOpen(false)}>
          <div className="modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <div className="modal-title-row">
                <WrenchIcon size={20} className="text-amber-700" />
                <h3>Schedule Maintenance: Room {maintenanceRoom.room_number}</h3>
              </div>
              <button
                type="button"
                onClick={() => setIsMaintenanceOpen(false)}
                className="modal-close-btn"
                title="Close modal"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form onSubmit={handleScheduleMaintenance} className="modal-form">
              <div className="form-grid-2">
                <div className="form-field">
                  <label>Start Date *</label>
                  <input
                    type="date"
                    value={mStartDate}
                    onChange={(e) => setMStartDate(e.target.value)}
                    required
                  />
                </div>
                <div className="form-field">
                  <label>End Date (Exclusive) *</label>
                  <input
                    type="date"
                    value={mEndDate}
                    min={mStartDate}
                    onChange={(e) => setMEndDate(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div className="form-field">
                <label>Reason / Maintenance Scope *</label>
                <input
                  type="text"
                  placeholder="AC filter cleaning, plumbing repair, repaint..."
                  value={mReason}
                  onChange={(e) => setMReason(e.target.value)}
                  required
                />
              </div>

              <div className="form-field">
                <label>Estimated Cost (₹)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={mCost}
                  onChange={(e) => setMCost(e.target.value)}
                />
              </div>

              <div className="modal-actions-row">
                <button
                  type="button"
                  onClick={() => setIsMaintenanceOpen(false)}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" disabled={isSubmitting} className="btn btn-primary">
                  {isSubmitting ? 'Scheduling...' : 'Set Under Maintenance'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
