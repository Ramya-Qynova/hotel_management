import React, { useState, useEffect, useCallback } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDate } from '../utils/formatters';
import { isValidPhone, PHONE_INPUT_PROPS, PHONE_ERROR_MSG } from '../utils/validation';
import {
  SettingsIcon,
  UsersIcon,
  PlusIcon,
  RefreshIcon,
  CheckCircleIcon,
  XIcon,
  ShieldCheckIcon,
  ClockIcon,
  FileTextIcon,
  EditIcon,
} from './Icons';

export default function SettingsView({ user }) {
  const { settings: contextSettings, updateLocalSettings } = useHotelSettings();
  const [activeTab, setActiveTab] = useState('general');
  const isOwnerOrAdmin = user?.role?.name === 'OWNER' || user?.role?.name === 'ADMIN';

  // Hotel Settings State - initialized from context
  const [settings, setSettings] = useState(contextSettings);

  const [loadingSettings, setLoadingSettings] = useState(false);
  const [savingSettings, setSavingSettings] = useState(false);
  const [saveSuccessMessage, setSaveSuccessMessage] = useState('');
  const [saveErrorMessage, setSaveErrorMessage] = useState('');

  // Staff Management State
  const [staffList, setStaffList] = useState([]);
  const [roles, setRoles] = useState([]);
  const [staffSearch, setStaffSearch] = useState('');
  const [staffRoleFilter, setStaffRoleFilter] = useState('');
  const [staffStatusFilter, setStaffStatusFilter] = useState('all');
  const [loadingStaff, setLoadingStaff] = useState(false);

  // Staff Modals
  const [isAddStaffOpen, setIsAddStaffOpen] = useState(false);
  const [editingStaff, setEditingStaff] = useState(null);
  const [staffFormData, setStaffFormData] = useState({
    name: '',
    email: '',
    password: '',
    role_id: '',
    is_active: true,
  });
  const [staffFormError, setStaffFormError] = useState('');
  const [submittingStaff, setSubmittingStaff] = useState(false);

  // Audit Logs State
  const [auditLogs, setAuditLogs] = useState([]);
  const [loadingLogs, setLoadingLogs] = useState(false);

  // Load Settings
  const loadSettings = useCallback(async () => {
    setLoadingSettings(true);
    try {
      const data = await hotelApi.getSettings();
      if (data?.settings) {
        setSettings((prev) => ({ ...prev, ...data.settings }));
        updateLocalSettings(data.settings);
      }
    } catch (e) {
      console.warn('Failed to load hotel settings:', e);
    } finally {
      setLoadingSettings(false);
    }
  }, [updateLocalSettings]);

  // Load Staff & Roles
  const loadStaffAndRoles = useCallback(async () => {
    if (!isOwnerOrAdmin) return;
    setLoadingStaff(true);
    try {
      const [staffData, rolesData] = await Promise.all([
        hotelApi.getStaff({
          search: staffSearch,
          role: staffRoleFilter,
          status: staffStatusFilter,
        }),
        hotelApi.getRoles(),
      ]);
      setStaffList(staffData?.data || []);
      setRoles(rolesData || []);
    } catch (e) {
      console.warn('Failed to load staff/roles:', e);
    } finally {
      setLoadingStaff(false);
    }
  }, [isOwnerOrAdmin, staffSearch, staffRoleFilter, staffStatusFilter]);

  // Load Audit Logs
  const loadAuditLogs = useCallback(async () => {
    if (!isOwnerOrAdmin) return;
    setLoadingLogs(true);
    try {
      const data = await hotelApi.getAuditLogs({ per_page: 30 });
      setAuditLogs(data?.data || []);
    } catch (e) {
      console.warn('Failed to load audit logs:', e);
    } finally {
      setLoadingLogs(false);
    }
  }, [isOwnerOrAdmin]);

  useEffect(() => {
    loadSettings();
  }, [loadSettings]);

  useEffect(() => {
    if (activeTab === 'staff') {
      loadStaffAndRoles();
    } else if (activeTab === 'audit') {
      loadAuditLogs();
    }
  }, [activeTab, loadStaffAndRoles, loadAuditLogs]);

  // ESC key and body scroll lock for staff modal
  const isStaffModalOpen = isAddStaffOpen || Boolean(editingStaff);
  useEffect(() => {
    if (!isStaffModalOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsAddStaffOpen(false);
        setEditingStaff(null);
      }
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isStaffModalOpen]);

  // Handle Setting Change
  const handleSettingChange = (field, val) => {
    setSettings((prev) => ({ ...prev, [field]: val }));
  };

  // Save Settings
  const handleSaveSettings = async (e) => {
    e.preventDefault();
    if (settings.hotel_phone && settings.hotel_phone.trim() && !isValidPhone(settings.hotel_phone.trim())) {
      setSaveErrorMessage('Contact Phone must be exactly 10 numeric digits.');
      return;
    }

    setSavingSettings(true);
    setSaveSuccessMessage('');
    setSaveErrorMessage('');

    try {
      await hotelApi.updateSettings({
        ...settings,
        hotel_phone: settings.hotel_phone ? settings.hotel_phone.trim() : '',
      });
      updateLocalSettings(settings);
      setSaveSuccessMessage('Hotel configuration saved and applied dynamically across the system.');
      setTimeout(() => setSaveSuccessMessage(''), 5000);
    } catch (err) {
      const msg = err.response?.data?.message || 'Failed to update hotel settings.';
      setSaveErrorMessage(msg);
    } finally {
      setSavingSettings(false);
    }
  };

  // Staff handlers
  const handleOpenAddStaff = () => {
    setStaffFormData({
      name: '',
      email: '',
      password: '',
      role_id: roles[0]?.id || '',
      is_active: true,
    });
    setStaffFormError('');
    setIsAddStaffOpen(true);
  };

  const handleOpenEditStaff = (member) => {
    setEditingStaff(member);
    setStaffFormData({
      name: member.name,
      email: member.email,
      password: '',
      role_id: member.role_id,
      is_active: Boolean(member.is_active),
    });
    setStaffFormError('');
  };

  const handleSubmitStaff = async (e) => {
    e.preventDefault();
    setSubmittingStaff(true);
    setStaffFormError('');

    try {
      if (editingStaff) {
        const payload = {
          name: staffFormData.name,
          email: staffFormData.email,
          role_id: parseInt(staffFormData.role_id, 10),
          is_active: staffFormData.is_active,
        };
        if (staffFormData.password.trim()) {
          payload.password = staffFormData.password;
        }
        await hotelApi.updateStaff(editingStaff.id, payload);
        setEditingStaff(null);
      } else {
        await hotelApi.createStaff(staffFormData);
        setIsAddStaffOpen(false);
      }
      loadStaffAndRoles();
    } catch (err) {
      const errData = err.response?.data;
      const errorMsg =
        errData?.message ||
        (errData?.errors ? Object.values(errData.errors).flat().join(', ') : 'Failed to save staff account.');
      setStaffFormError(errorMsg);
    } finally {
      setSubmittingStaff(false);
    }
  };

  // Toggle Staff Status (Deactivate / Activate)
  const handleToggleStaffStatus = async (member) => {
    const action = member.is_active ? 'deactivate' : 'activate';
    const confirmMsg = member.is_active
      ? `Are you sure you want to deactivate ${member.name}'s account? They will be immediately signed out and unable to access the system.`
      : `Activate ${member.name}'s account?`;

    if (!window.confirm(confirmMsg)) return;

    try {
      await hotelApi.toggleStaffStatus(member.id);
      loadStaffAndRoles();
    } catch (err) {
      alert(err.response?.data?.message || `Failed to ${action} staff member.`);
    }
  };

  return (
    <div className="settings-container view-container">
      {/* Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Hotel Settings & Staff Administration</h1>
          <p className="view-subtitle">
            Configure hotel branding, operational check-in timings, taxes, cancellation policies, and staff security access.
          </p>
        </div>
      </div>

      {/* Settings Navigation Tabs */}
      <div className="sub-tabs-bar">
        <button
          type="button"
          onClick={() => setActiveTab('general')}
          className={`sub-tab-btn ${activeTab === 'general' ? 'active' : ''}`}
        >
          <SettingsIcon size={16} />
          <span>General & Branding</span>
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('timings')}
          className={`sub-tab-btn ${activeTab === 'timings' ? 'active' : ''}`}
        >
          <ClockIcon size={16} />
          <span>Check-in / Check-out</span>
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('taxes')}
          className={`sub-tab-btn ${activeTab === 'taxes' ? 'active' : ''}`}
        >
          <FileTextIcon size={16} />
          <span>Tax & Invoicing</span>
        </button>
        <button
          type="button"
          onClick={() => setActiveTab('policies')}
          className={`sub-tab-btn ${activeTab === 'policies' ? 'active' : ''}`}
        >
          <ShieldCheckIcon size={16} />
          <span>Pricing & Policies</span>
        </button>
        {isOwnerOrAdmin && (
          <>
            <button
              type="button"
              onClick={() => setActiveTab('staff')}
              className={`sub-tab-btn ${activeTab === 'staff' ? 'active' : ''}`}
            >
              <UsersIcon size={16} />
              <span>Staff Accounts</span>
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('audit')}
              className={`sub-tab-btn ${activeTab === 'audit' ? 'active' : ''}`}
            >
              <span>Audit Trail</span>
            </button>
          </>
        )}
      </div>

      {/* Notifications / Alerts */}
      {saveSuccessMessage && (
        <div className="alert-box success mb-4">
          <CheckCircleIcon size={18} />
          <span style={{ flex: 1 }}>{saveSuccessMessage}</span>
          <button type="button" className="close-alert-btn" onClick={() => setSaveSuccessMessage('')}>
            <XIcon size={14} />
          </button>
        </div>
      )}

      {saveErrorMessage && (
        <div className="alert-box error mb-4">
          <XIcon size={18} />
          <span style={{ flex: 1 }}>{saveErrorMessage}</span>
          <button type="button" className="close-alert-btn" onClick={() => setSaveErrorMessage('')}>
            <XIcon size={14} />
          </button>
        </div>
      )}

      {/* TAB 1: GENERAL & BRANDING */}
      {activeTab === 'general' && (
        <form onSubmit={handleSaveSettings} className="settings-form">
          <div className="card mb-4">
            <h2 className="card-section-title">
              <SettingsIcon size={18} className="text-forest-green" />
              <span>Hotel Identity & Contact Information</span>
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="form-group">
                <label htmlFor="hotel_name">Hotel Name *</label>
                <input
                  id="hotel_name"
                  name="hotel_name"
                  type="text"
                  required
                  value={settings.hotel_name}
                  onChange={(e) => handleSettingChange('hotel_name', e.target.value)}
                  onInput={(e) => handleSettingChange('hotel_name', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
              </div>

              <div className="form-group">
                <label>Tagline / Motto</label>
                <input
                  type="text"
                  value={settings.hotel_tagline}
                  onChange={(e) => handleSettingChange('hotel_tagline', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
              </div>

              <div className="form-group">
                <label>Contact Phone (10 Digits)</label>
                <input
                  type="tel"
                  {...PHONE_INPUT_PROPS}
                  value={settings.hotel_phone || ''}
                  onChange={(e) => handleSettingChange('hotel_phone', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                  placeholder="9876543210"
                />
                {settings.hotel_phone && settings.hotel_phone.trim() && !isValidPhone(settings.hotel_phone.trim()) && (
                  <span className="field-error-text" style={{ fontSize: '0.75rem', color: 'var(--color-danger)', marginTop: '0.2rem', display: 'block' }}>
                    {PHONE_ERROR_MSG}
                  </span>
                )}
              </div>

              <div className="form-group">
                <label>Contact / Reservation Email</label>
                <input
                  type="email"
                  value={settings.hotel_email}
                  onChange={(e) => handleSettingChange('hotel_email', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
              </div>

              <div className="form-group" style={{ gridColumn: 'span 2' }}>
                <label>Physical Hotel Address</label>
                <textarea
                  rows="2"
                  value={settings.hotel_address}
                  onChange={(e) => handleSettingChange('hotel_address', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
              </div>

              <div className="form-group" style={{ gridColumn: 'span 2' }}>
                <label>Hotel Logo Image URL</label>
                <input
                  type="text"
                  placeholder="https://..."
                  value={settings.hotel_logo_url}
                  onChange={(e) => handleSettingChange('hotel_logo_url', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
                <span className="form-hint">
                  Displayed on top navigation, guest registration cards, and PDF invoices.
                </span>
              </div>
            </div>
          </div>

          {isOwnerOrAdmin && (
            <div className="form-footer-actions">
              <button type="submit" disabled={savingSettings} className="btn btn-primary">
                {savingSettings ? 'Saving...' : 'Save General Settings'}
              </button>
            </div>
          )}
        </form>
      )}

      {/* TAB 2: CHECK-IN / CHECK-OUT */}
      {activeTab === 'timings' && (
        <form onSubmit={handleSaveSettings} className="settings-form">
          <div className="card mb-4">
            <h2 className="card-section-title">
              <ClockIcon size={18} className="text-forest-green" />
              <span>Default Operational Timings & Reservation Holds</span>
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="form-group">
                <label>Standard Check-in Time (24h) *</label>
                <input
                  type="text"
                  pattern="^([01]\d|2[0-3]):[0-5]\d$"
                  required
                  placeholder="14:00"
                  value={settings.check_in_time}
                  onChange={(e) => handleSettingChange('check_in_time', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                  style={{ fontFamily: 'monospace' }}
                />
                <span className="form-hint">Format: HH:MM (e.g. 14:00 for 2:00 PM)</span>
              </div>

              <div className="form-group">
                <label>Standard Check-out Time (24h) *</label>
                <input
                  type="text"
                  pattern="^([01]\d|2[0-3]):[0-5]\d$"
                  required
                  placeholder="11:00"
                  value={settings.check_out_time}
                  onChange={(e) => handleSettingChange('check_out_time', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                  style={{ fontFamily: 'monospace' }}
                />
                <span className="form-hint">Format: HH:MM (e.g. 11:00 for 11:00 AM)</span>
              </div>

              <div className="form-group">
                <label>Online Hold Expiration (Minutes) *</label>
                <input
                  type="number"
                  min="5"
                  max="1440"
                  required
                  value={settings.hold_expiration_minutes}
                  onChange={(e) => handleSettingChange('hold_expiration_minutes', parseInt(e.target.value, 10) || 30)}
                  disabled={!isOwnerOrAdmin}
                />
                <span className="form-hint">
                  Temporary lock duration while guests complete checkout payment.
                </span>
              </div>
            </div>
          </div>

          {isOwnerOrAdmin && (
            <div className="form-footer-actions">
              <button type="submit" disabled={savingSettings} className="btn btn-primary">
                {savingSettings ? 'Saving...' : 'Save Timing Settings'}
              </button>
            </div>
          )}
        </form>
      )}

      {/* TAB 3: TAX & INVOICING */}
      {activeTab === 'taxes' && (
        <form onSubmit={handleSaveSettings} className="settings-form">
          <div className="card mb-4">
            <h2 className="card-section-title">
              <FileTextIcon size={18} className="text-forest-green" />
              <span>Tax Applicability & Invoicing Parameters</span>
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="form-group">
                <label>Tax Rate (%) *</label>
                <div className="input-prefix-wrapper">
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    required
                    value={settings.tax_rate_percent}
                    onChange={(e) => handleSettingChange('tax_rate_percent', parseFloat(e.target.value) || 0)}
                    disabled={!isOwnerOrAdmin}
                  />
                  <span className="input-prefix" style={{ borderLeft: '1px solid var(--card-border)', borderRight: 'none' }}>%</span>
                </div>
                <span className="form-hint">GST / VAT percentage applied to bookings.</span>
              </div>

              <div className="form-group">
                <label>Tax Label Name</label>
                <input
                  type="text"
                  value={settings.tax_name || ''}
                  onChange={(e) => handleSettingChange('tax_name', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
                <span className="form-hint">e.g. GST, VAT, Hospitality Tax</span>
              </div>

              <div className="form-group">
                <label>Tax Registration No / GSTIN</label>
                <input
                  type="text"
                  value={settings.hotel_gstin || ''}
                  onChange={(e) => handleSettingChange('hotel_gstin', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                  placeholder="e.g. 08AABCG1234F1Z8"
                />
                <span className="form-hint">Official GSTIN or Tax ID displayed on invoices.</span>
              </div>

              <div className="form-group">
                <label>Currency Symbol</label>
                <input
                  type="text"
                  value={settings.currency_symbol}
                  onChange={(e) => handleSettingChange('currency_symbol', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                  style={{ fontFamily: 'monospace' }}
                />
                <span className="form-hint">e.g. ₹, $, €</span>
              </div>

              <div className="form-group">
                <label>Invoice Prefix</label>
                <input
                  type="text"
                  value={settings.invoice_prefix}
                  onChange={(e) => handleSettingChange('invoice_prefix', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                  style={{ fontFamily: 'monospace' }}
                />
                <span className="form-hint">Prefix for invoice serial numbers (e.g. INV)</span>
              </div>

              <div className="form-group" style={{ gridColumn: 'span 2' }}>
                <label>Invoice Footer Note</label>
                <input
                  type="text"
                  value={settings.invoice_footer_note}
                  onChange={(e) => handleSettingChange('invoice_footer_note', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
                <span className="form-hint">Printed at the bottom of all guest tax invoices.</span>
              </div>
            </div>
          </div>

          {isOwnerOrAdmin && (
            <div className="form-footer-actions">
              <button type="submit" disabled={savingSettings} className="btn btn-primary">
                {savingSettings ? 'Saving...' : 'Save Tax & Invoicing Settings'}
              </button>
            </div>
          )}
        </form>
      )}

      {/* TAB 4: PRICING & POLICIES */}
      {activeTab === 'policies' && (
        <form onSubmit={handleSaveSettings} className="settings-form">
          <div className="card mb-4">
            <h2 className="card-section-title">
              <ShieldCheckIcon size={18} className="text-forest-green" />
              <span>Reservation Deposits & Cancellation Policies</span>
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="form-group">
                <label>Mandatory Advance Deposit (%) *</label>
                <div className="input-prefix-wrapper">
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    required
                    value={settings.advance_payment_percent}
                    onChange={(e) => handleSettingChange('advance_payment_percent', parseFloat(e.target.value) || 0)}
                    disabled={!isOwnerOrAdmin}
                  />
                  <span className="input-prefix" style={{ borderLeft: '1px solid var(--card-border)', borderRight: 'none' }}>%</span>
                </div>
                <span className="form-hint">
                  Required advance deposit percentage to transition booking to CONFIRMED.
                </span>
              </div>

              <div className="form-group">
                <label>Cancellation Grace Window (Hours)</label>
                <input
                  type="number"
                  min="0"
                  max="720"
                  value={settings.cancellation_grace_hours}
                  onChange={(e) => handleSettingChange('cancellation_grace_hours', parseInt(e.target.value, 10) || 0)}
                  disabled={!isOwnerOrAdmin}
                />
                <span className="form-hint">
                  Hours prior to check-in allowing penalty-free cancellation.
                </span>
              </div>

              <div className="form-group" style={{ gridColumn: 'span 2' }}>
                <label>Cancellation Terms & Refund Policy</label>
                <textarea
                  rows="3"
                  value={settings.cancellation_policy}
                  onChange={(e) => handleSettingChange('cancellation_policy', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
              </div>

              <div className="form-group" style={{ gridColumn: 'span 2' }}>
                <label>Guest Conduct & House Rules</label>
                <textarea
                  rows="3"
                  value={settings.house_rules}
                  onChange={(e) => handleSettingChange('house_rules', e.target.value)}
                  disabled={!isOwnerOrAdmin}
                />
              </div>
            </div>
          </div>

          {isOwnerOrAdmin && (
            <div className="form-footer-actions">
              <button type="submit" disabled={savingSettings} className="btn btn-primary">
                {savingSettings ? 'Saving...' : 'Save Policy Settings'}
              </button>
            </div>
          )}
        </form>
      )}

      {/* TAB 5: STAFF MANAGEMENT */}
      {activeTab === 'staff' && isOwnerOrAdmin && (
        <div className="staff-management-pane">
          {/* Staff Filter & Action Bar */}
          <div className="filter-bar">
            <div style={{ display: 'flex', gap: '0.65rem', flexWrap: 'wrap', alignItems: 'center' }}>
              <div className="search-input-wrapper">
                <input
                  type="text"
                  placeholder="Search staff name, email..."
                  value={staffSearch}
                  onChange={(e) => setStaffSearch(e.target.value)}
                />
              </div>

              <select
                value={staffRoleFilter}
                onChange={(e) => setStaffRoleFilter(e.target.value)}
                style={{ padding: '0.4rem 0.75rem', fontSize: '0.85rem' }}
              >
                <option value="">All Roles</option>
                {roles.map((r) => (
                  <option key={r.id} value={r.name}>
                    {r.display_name}
                  </option>
                ))}
              </select>

              <select
                value={staffStatusFilter}
                onChange={(e) => setStaffStatusFilter(e.target.value)}
                style={{ padding: '0.4rem 0.75rem', fontSize: '0.85rem' }}
              >
                <option value="all">All Status</option>
                <option value="active">Active Only</option>
                <option value="inactive">Deactivated</option>
              </select>
            </div>

            <button
              type="button"
              onClick={handleOpenAddStaff}
              className="btn btn-primary"
            >
              <PlusIcon size={16} />
              <span>Add Staff Account</span>
            </button>
          </div>

          {/* Staff Members Table */}
          <div className="card">
            {loadingStaff ? (
              <div className="py-8 text-center text-muted">
                <div className="spinner-large" style={{ margin: '0 auto 0.75rem auto' }} />
                <span>Loading staff directory...</span>
              </div>
            ) : (
              <div className="table-responsive">
                <table className="dashboard-table">
                  <thead>
                    <tr>
                      <th>Staff Member</th>
                      <th>Email Address</th>
                      <th>Assigned Role</th>
                      <th>Account Status</th>
                      <th>Created Date</th>
                      <th className="text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {staffList.length === 0 ? (
                      <tr>
                        <td colSpan="6" className="text-center py-6 text-muted">
                          No staff accounts match the filter criteria.
                        </td>
                      </tr>
                    ) : (
                      staffList.map((member) => (
                        <tr key={member.id}>
                          <td>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                              <div className="user-avatar" style={{ width: '30px', height: '30px', fontSize: '0.78rem' }}>
                                {member.name.charAt(0).toUpperCase()}
                              </div>
                              <span className="font-semibold text-emerald-950">{member.name}</span>
                            </div>
                          </td>
                          <td className="text-sm font-mono text-muted">{member.email}</td>
                          <td>
                            <span className={`role-badge role-${member.role?.name?.toLowerCase() || 'staff'}`}>
                              {member.role?.display_name || member.role?.name}
                            </span>
                          </td>
                          <td>
                            <span
                              className={`status-pill ${
                                member.is_active ? 'confirmed' : 'cancelled'
                              }`}
                            >
                              <span className="status-dot active" />
                              {member.is_active ? 'Active' : 'Deactivated'}
                            </span>
                          </td>
                          <td className="text-xs text-muted" style={{ whiteSpace: 'nowrap' }}>
                            {formatDate(member.created_at)}
                          </td>
                          <td className="text-right" style={{ whiteSpace: 'nowrap' }}>
                            <div style={{ display: 'inline-flex', gap: '0.4rem', justifyContent: 'flex-end' }}>
                              <button
                                type="button"
                                onClick={() => handleOpenEditStaff(member)}
                                className="btn btn-secondary btn-xs"
                              >
                                <EditIcon size={13} />
                                <span>Edit</span>
                              </button>
                              <button
                                type="button"
                                onClick={() => handleToggleStaffStatus(member)}
                                className={`btn btn-xs ${
                                  member.is_active ? 'btn-danger-outline' : 'btn-success-outline'
                                }`}
                              >
                                {member.is_active ? 'Deactivate' : 'Activate'}
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 6: AUDIT TRAIL */}
      {activeTab === 'audit' && isOwnerOrAdmin && (
        <div className="card">
          <div className="card-header-row mb-4" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <h3 className="card-section-title" style={{ margin: 0, border: 'none' }}>Administrative Audit Trail</h3>
              <p className="text-xs text-muted">Immutable ledger of administrative and critical operational system changes</p>
            </div>
            <button type="button" onClick={loadAuditLogs} className="btn-icon-secondary" title="Refresh audit log">
              <RefreshIcon size={16} />
            </button>
          </div>

          {loadingLogs ? (
            <div className="py-8 text-center text-muted">
              <div className="spinner-large" style={{ margin: '0 auto 0.5rem auto' }} />
              <span>Loading audit records...</span>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="dashboard-table text-sm">
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Staff User</th>
                    <th>Auditable Target</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  {auditLogs.length === 0 ? (
                    <tr>
                      <td colSpan="5" className="text-center py-6 text-muted">
                        No audit events recorded yet.
                      </td>
                    </tr>
                  ) : (
                    auditLogs.map((log) => (
                      <tr key={log.id}>
                        <td className="text-xs font-mono" style={{ whiteSpace: 'nowrap' }}>
                          {formatDate(log.created_at, true)}
                        </td>
                        <td>
                          <span className="status-pill pending" style={{ textTransform: 'uppercase', fontSize: '0.72rem' }}>
                            {log.action}
                          </span>
                        </td>
                        <td className="font-semibold">{log.user?.name || 'System / Batch'}</td>
                        <td className="text-xs text-muted font-mono">
                          {log.auditable_type?.split('\\').pop()} #{log.auditable_id}
                        </td>
                        <td className="text-xs max-w-xs truncate">
                          {log.new_values ? JSON.stringify(log.new_values) : '—'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* ADD / EDIT STAFF MODAL */}
      {(isAddStaffOpen || editingStaff) && (
        <div className="modal-overlay" onClick={() => { setIsAddStaffOpen(false); setEditingStaff(null); }} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '480px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <h3 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                {editingStaff ? `Edit Staff Member: ${editingStaff.name}` : 'Create New Staff Account'}
              </h3>
              <button
                type="button"
                onClick={() => {
                  setIsAddStaffOpen(false);
                  setEditingStaff(null);
                }}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>

            <form
              onSubmit={handleSubmitStaff}
              style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}
            >
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                {staffFormError && (
                  <div className="alert-box error mb-3">
                    <XIcon size={16} />
                    <span>{staffFormError}</span>
                  </div>
                )}

                <div className="form-group">
                  <label>Full Name *</label>
                  <input
                    type="text"
                    required
                    value={staffFormData.name}
                    onChange={(e) => setStaffFormData({ ...staffFormData, name: e.target.value })}
                    placeholder="e.g. Ramesh Patel"
                  />
                </div>

                <div className="form-group">
                  <label>Email Address *</label>
                  <input
                    type="email"
                    required
                    value={staffFormData.email}
                    onChange={(e) => setStaffFormData({ ...staffFormData, email: e.target.value })}
                    placeholder="e.g. ramesh@hotel.com"
                  />
                </div>

                <div className="form-group">
                  <label>Role Assignment *</label>
                  <select
                    required
                    value={staffFormData.role_id}
                    onChange={(e) => setStaffFormData({ ...staffFormData, role_id: e.target.value })}
                  >
                    <option value="">Select Role...</option>
                    {roles.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.display_name} ({r.name})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="form-group">
                  <label>
                    {editingStaff ? 'New Password (leave blank to keep current)' : 'Password (min 8 chars) *'}
                  </label>
                  <input
                    type="password"
                    required={!editingStaff}
                    minLength={8}
                    value={staffFormData.password}
                    onChange={(e) => setStaffFormData({ ...staffFormData, password: e.target.value })}
                    placeholder="••••••••"
                  />
                </div>

                <div className="form-group" style={{ flexDirection: 'row', alignItems: 'center', gap: '0.65rem', marginTop: '0.5rem' }}>
                  <input
                    type="checkbox"
                    id="is_active_toggle"
                    checked={staffFormData.is_active}
                    onChange={(e) => setStaffFormData({ ...staffFormData, is_active: e.target.checked })}
                    style={{ width: '16px', height: '16px', cursor: 'pointer' }}
                  />
                  <label htmlFor="is_active_toggle" style={{ margin: 0, cursor: 'pointer', fontSize: '0.88rem' }}>
                    Account is active and permitted to login
                  </label>
                </div>
              </div>

              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button
                  type="button"
                  onClick={() => {
                    setIsAddStaffOpen(false);
                    setEditingStaff(null);
                  }}
                  className="btn btn-secondary"
                >
                  Cancel
                </button>
                <button type="submit" disabled={submittingStaff} className="btn btn-primary">
                  {submittingStaff ? 'Saving...' : editingStaff ? 'Save Changes' : 'Create Staff Account'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
