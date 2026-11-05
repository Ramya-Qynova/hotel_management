import React, { useState, useEffect, useCallback } from 'react';
import hotelApi from '../api/hotelApi';
import { useHotelSettings } from '../context/HotelSettingsContext';
import { formatDate, formatDateRange, formatCurrency } from '../utils/formatters';
import {
  BillingIcon,
  ReceiptIcon,
  PlusIcon,
  SearchIcon,
  RefreshIcon,
  RupeeIcon,
  CreditCardIcon,
  PrintIcon,
  ServicesIcon,
  XIcon,
  CheckCircleIcon,
  AlertCircleIcon,
} from './Icons';
import RecordPaymentModal from './RecordPaymentModal';
import RazorpayPaymentModal from './RazorpayPaymentModal';
import InvoicePrintModal from './InvoicePrintModal';
import AddServiceModal from './AddServiceModal';
import IssueRefundModal from './IssueRefundModal';
import FolioInspectorModal from './FolioInspectorModal';

export default function BillingView({ user }) {
  const { currencySymbol } = useHotelSettings();
  const isFinancialManager = user?.role?.name === 'OWNER' || user?.role?.name === 'ADMIN' || user?.role?.name === 'ACCOUNTANT';
  const [activeTab, setActiveTab] = useState('folios'); // 'folios' | 'payments' | 'refunds' | 'register'
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Data states
  const [bookings, setBookings] = useState([]);
  const [payments, setPayments] = useState([]);
  const [refunds, setRefunds] = useState([]);
  const [cashRegister, setCashRegister] = useState(null);
  const [expenses, setExpenses] = useState([]);
  const [expenseCategories, setExpenseCategories] = useState([]);

  // Action Modals
  const [selectedBookingForInspect, setSelectedBookingForInspect] = useState(null);
  const [selectedBookingForPayment, setSelectedBookingForPayment] = useState(null);
  const [selectedBookingForRazorpay, setSelectedBookingForRazorpay] = useState(null);
  const [selectedBookingForInvoice, setSelectedBookingForInvoice] = useState(null);
  const [selectedBookingForService, setSelectedBookingForService] = useState(null);
  const [selectedPaymentForRefund, setSelectedPaymentForRefund] = useState(null);

  // Cash Register Modals
  const [isOpenRegisterModal, setIsOpenRegisterModal] = useState(false);
  const [openingFloat, setOpeningFloat] = useState('5000.00');
  const [registerNotes, setRegisterNotes] = useState('');

  const [isReconcileRegisterModal, setIsReconcileRegisterModal] = useState(false);
  const [closingCash, setClosingCash] = useState('');

  // Expense Modals
  const [isAddExpenseModal, setIsAddExpenseModal] = useState(false);
  const [expenseForm, setExpenseForm] = useState({
    expense_category_id: '',
    amount: '',
    description: '',
    payment_method: 'CASH',
    receipt_number: '',
    expense_date: new Date().toISOString().slice(0, 10),
  });

  const [isAddExpenseCatModal, setIsAddExpenseCatModal] = useState(false);
  const [categoryForm, setCategoryForm] = useState({ name: '', description: '' });

  // Body scroll lock and ESC key for cash register modals
  const isRegisterModalActive =
    isOpenRegisterModal || isReconcileRegisterModal || isAddExpenseModal || isAddExpenseCatModal;

  useEffect(() => {
    if (!isRegisterModalActive) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsOpenRegisterModal(false);
        setIsReconcileRegisterModal(false);
        setIsAddExpenseModal(false);
        setIsAddExpenseCatModal(false);
      }
    };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', handleKeyDown);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isRegisterModalActive]);

  // Load All Core Financial Data
  const refreshFinancialData = useCallback(async () => {
    setLoading(true);
    setErrorMessage('');
    try {
      const promises = [
        hotelApi.getBookings({ per_page: 50 }).catch(() => ({ data: [] })),
        hotelApi.getPayments({ per_page: 50 }).catch(() => ({ data: [] })),
      ];
      if (isFinancialManager) {
        promises.push(hotelApi.getRefunds({ per_page: 50 }).catch(() => ({ data: [] })));
      }
      const results = await Promise.all(promises);
      setBookings(results[0]?.data || []);
      setPayments(results[1]?.data || []);
      setRefunds(isFinancialManager ? (results[2]?.data || []) : []);
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to fetch financial data.');
    } finally {
      setLoading(false);
    }
  }, [isFinancialManager]);

  // Fetch Cash Register & Expenses
  const fetchRegisterAndExpenses = useCallback(async () => {
    if (!isFinancialManager) return;
    setLoading(true);
    setErrorMessage('');
    try {
      const [regSummary, expData, catData] = await Promise.all([
        hotelApi.getCashRegisterSummary(),
        hotelApi.getExpenses({ per_page: 50 }),
        hotelApi.getExpenseCategories(),
      ]);
      setCashRegister(regSummary);
      setExpenses(expData?.data || []);
      setExpenseCategories(catData || []);
      if (catData && catData.length > 0 && !expenseForm.expense_category_id) {
        setExpenseForm((prev) => ({ ...prev, expense_category_id: catData[0].id }));
      }
    } catch (err) {
      setErrorMessage(err.response?.data?.message || 'Failed to fetch cash register and expenses.');
    } finally {
      setLoading(false);
    }
  }, [expenseForm.expense_category_id, isFinancialManager]);

  useEffect(() => {
    refreshFinancialData();
  }, [refreshFinancialData]);

  useEffect(() => {
    if (activeTab === 'register' && isFinancialManager) {
      fetchRegisterAndExpenses();
    }
  }, [activeTab, fetchRegisterAndExpenses, isFinancialManager]);

  useEffect(() => {
    if (!isFinancialManager && (activeTab === 'refunds' || activeTab === 'register')) {
      setActiveTab('folios');
    }
  }, [isFinancialManager, activeTab]);

  // Financial Summary KPIs (Avoid floating-point arithmetic errors)
  const totalInvoiced = bookings.reduce((sum, b) => {
    return Math.round((sum + Number(b.grand_total || 0)) * 100) / 100;
  }, 0);

  const totalCollected = payments
    .filter((p) => p.status === 'SUCCESS')
    .reduce((sum, p) => {
      return Math.round((sum + Number(p.amount || 0)) * 100) / 100;
    }, 0);

  const totalRefunds = refunds
    .filter((r) => r.status === 'PROCESSED' || !r.status)
    .reduce((sum, r) => {
      return Math.round((sum + Number(r.amount || 0)) * 100) / 100;
    }, 0);

  const outstandingBalance = bookings.reduce((sum, b) => {
    const total = Number(b.grand_total || 0);
    const paid = Number(b.invoice?.paid_amount || 0);
    const due = Number(b.invoice?.balance_due ?? Math.max(0, total - paid));
    return Math.round((sum + due) * 100) / 100;
  }, 0);

  // Handle Open Register
  const handleOpenRegisterSubmit = async (e) => {
    e.preventDefault();
    try {
      await hotelApi.openCashRegister(parseFloat(openingFloat), registerNotes || undefined);
      setSuccessMessage('Cash register opened successfully with starting float.');
      setIsOpenRegisterModal(false);
      fetchRegisterAndExpenses();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to open register.');
    }
  };

  // Handle Reconcile Register
  const handleReconcileRegisterSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await hotelApi.reconcileCashRegister(parseFloat(closingCash), registerNotes || undefined);
      setSuccessMessage(`Cash drawer reconciled! Variance: ${formatCurrency(res.variance, currencySymbol)}`);
      setIsReconcileRegisterModal(false);
      fetchRegisterAndExpenses();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to reconcile register.');
    }
  };

  // Handle Record Expense
  const handleRecordExpenseSubmit = async (e) => {
    e.preventDefault();
    try {
      await hotelApi.createExpense({
        ...expenseForm,
        expense_category_id: parseInt(expenseForm.expense_category_id, 10),
        amount: parseFloat(expenseForm.amount),
      });
      setSuccessMessage('Expense recorded against cash drawer successfully.');
      setIsAddExpenseModal(false);
      setExpenseForm({
        expense_category_id: expenseCategories[0]?.id || '',
        amount: '',
        description: '',
        payment_method: 'CASH',
        receipt_number: '',
        expense_date: new Date().toISOString().slice(0, 10),
      });
      fetchRegisterAndExpenses();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to record expense.');
    }
  };

  // Handle Add Category
  const handleAddCategorySubmit = async (e) => {
    e.preventDefault();
    try {
      const created = await hotelApi.createExpenseCategory(categoryForm);
      setSuccessMessage(`Expense category "${created.name}" created!`);
      setIsAddExpenseCatModal(false);
      setCategoryForm({ name: '', description: '' });
      fetchRegisterAndExpenses();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to create category.');
    }
  };

  // Filter Bookings by query
  const filteredBookings = bookings.filter((b) => {
    if (!searchQuery.trim()) return true;
    const q = searchQuery.toLowerCase();
    const guestName = `${b.guest?.first_name || ''} ${b.guest?.last_name || ''}`.toLowerCase();
    return (
      b.booking_number.toLowerCase().includes(q) ||
      guestName.includes(q) ||
      (b.guest?.phone && b.guest.phone.includes(q))
    );
  });

  return (
    <div className="view-container">
      {/* View Header */}
      <div className="view-header">
        <div>
          <h1 className="view-title">Billing, Invoicing & Cash Operations</h1>
          <p className="view-subtitle">
            Manage reservation folios, split payments, Razorpay online gateway links, tax invoices, and cash drawer reconciliations.
          </p>
        </div>

        <div className="view-header-actions">
          {activeTab === 'register' && (
            <>
              <button
                type="button"
                onClick={() => setIsAddExpenseModal(true)}
                className="btn btn-primary"
              >
                <PlusIcon size={16} />
                <span>Record Expense</span>
              </button>
              <button
                type="button"
                onClick={() => setIsOpenRegisterModal(true)}
                className="btn btn-secondary"
              >
                <span>Open Register Float</span>
              </button>
              <button
                type="button"
                onClick={() => setIsReconcileRegisterModal(true)}
                className="btn btn-success-outline"
              >
                <span>Reconcile Drawer</span>
              </button>
            </>
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

      {/* Requirement 12: Top Summary KPI Cards */}
      <div className="register-summary-grid mb-6">
        <div className="metric-card">
          <span className="metric-label">Total Invoiced</span>
          <span className="metric-value">{formatCurrency(totalInvoiced, currencySymbol)}</span>
          <span className="metric-hint">Cumulative folios generated</span>
        </div>
        <div className="metric-card">
          <span className="metric-label">Total Collected</span>
          <span className="metric-value text-forest-green">{formatCurrency(totalCollected, currencySymbol)}</span>
          <span className="metric-hint">Successful receipts received</span>
        </div>
        <div className="metric-card">
          <span className="metric-label">Total Refunds</span>
          <span className="metric-value text-danger">{formatCurrency(totalRefunds, currencySymbol)}</span>
          <span className="metric-hint">Authorized refund disbursements</span>
        </div>
        <div className="metric-card highlight">
          <span className="metric-label">Outstanding Balance</span>
          <span className="metric-value">
            {outstandingBalance === 0 ? (
              <span className="text-forest-green font-bold">All Settled</span>
            ) : (
              formatCurrency(outstandingBalance, currencySymbol)
            )}
          </span>
          <span className="metric-hint font-semibold">
            {outstandingBalance === 0 ? 'No outstanding receivables' : 'Pending payment settlement'}
          </span>
        </div>
      </div>

      {/* Navigation Sub-Tabs & Search */}
      <div className="filter-bar">
        <div className="filter-pill-group">
          <button
            type="button"
            className={`filter-pill ${activeTab === 'folios' ? 'active' : ''}`}
            onClick={() => setActiveTab('folios')}
          >
            <BillingIcon size={14} />
            <span>Reservation Folios ({bookings.length})</span>
          </button>
          <button
            type="button"
            className={`filter-pill ${activeTab === 'payments' ? 'active' : ''}`}
            onClick={() => setActiveTab('payments')}
          >
            <RupeeIcon size={14} />
            <span>Payments History ({payments.length})</span>
          </button>
          {isFinancialManager && (
            <>
              <button
                type="button"
                className={`filter-pill ${activeTab === 'refunds' ? 'active' : ''}`}
                onClick={() => setActiveTab('refunds')}
              >
                <span>Refunds ({refunds.length})</span>
              </button>
              <button
                type="button"
                className={`filter-pill ${activeTab === 'register' ? 'active' : ''}`}
                onClick={() => setActiveTab('register')}
              >
                <span>Expenses & Cash Drawer</span>
              </button>
            </>
          )}
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
          {activeTab !== 'register' && (
            <div className="search-input-wrapper">
              <SearchIcon size={16} className="search-icon-inside" />
              <input
                type="text"
                placeholder="Search folio, guest name, phone..."
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
          )}

          <button
            type="button"
            onClick={() => {
              if (activeTab === 'register') {
                fetchRegisterAndExpenses();
              } else {
                refreshFinancialData();
              }
            }}
            className="btn-icon-secondary"
            title="Refresh current view"
          >
            <RefreshIcon size={16} />
          </button>
        </div>
      </div>

      {/* Tab 1: Reservation Folios */}
      {activeTab === 'folios' && (
        <div className="table-responsive">
          {loading ? (
            <div className="dashboard-loading" style={{ minHeight: '260px' }}>
              <div className="spinner-large" />
              <p>Loading reservation billing folios...</p>
            </div>
          ) : filteredBookings.length === 0 ? (
            <div className="empty-state-card">
              <div className="empty-state-icon-wrapper">
                <BillingIcon size={32} />
              </div>
              <h3 className="font-bold text-lg text-emerald-950">No Reservation Folios Found</h3>
              <p className="text-muted" style={{ maxWidth: '420px', margin: '0 auto' }}>
                {searchQuery
                  ? `No reservations matching "${searchQuery}". Try clearing search.`
                  : 'Confirmed reservations and their real-time folio balances will appear here.'}
              </p>
            </div>
          ) : (
            <table className="dashboard-table">
              <thead>
                <tr>
                  <th>Folio No</th>
                  <th>Guest Profile</th>
                  <th>Stay Dates</th>
                  <th>Status</th>
                  <th className="text-right">Grand Total</th>
                  <th className="text-right">Paid Amount</th>
                  <th className="text-right">Balance Due</th>
                  <th className="text-center">Billing Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredBookings.map((b) => {
                  const total = Number(b.grand_total || 0);
                  const paid = Number(b.invoice?.paid_amount || 0);
                  const balance = Number(b.invoice?.balance_due ?? Math.max(0, total - paid));
                  const isSettled = balance <= 0;

                  return (
                    <tr
                      key={b.id}
                      style={{ cursor: 'pointer' }}
                      onClick={() => setSelectedBookingForInspect(b)}
                    >
                      <td className="code bold" style={{ color: 'var(--color-sidebar-active-text)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem' }}>
                          <ReceiptIcon size={14} className="text-forest-green" />
                          <span>{b.booking_number}</span>
                        </div>
                      </td>
                      <td>
                        <span className="font-semibold block" style={{ color: 'var(--color-heading)' }}>
                          {b.guest?.first_name} {b.guest?.last_name}
                        </span>
                        <small className="block text-muted">{b.guest?.phone || 'No phone'}</small>
                      </td>
                      <td style={{ whiteSpace: 'nowrap' }}>
                        {formatDateRange(b.check_in_date, b.check_out_date)}
                      </td>
                      <td>
                        <span
                          className={`status-pill ${
                            b.status === 'CONFIRMED'
                              ? 'confirmed'
                              : b.status === 'CHECKED_IN'
                              ? 'checked-in'
                              : b.status === 'PENDING_PAYMENT'
                              ? 'pending'
                              : 'cancelled'
                          }`}
                        >
                          <span className="status-dot active" />
                          {b.status.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="text-right bold">
                        {formatCurrency(total, currencySymbol)}
                      </td>
                      <td className="text-right text-forest-green bold">
                        {formatCurrency(paid, currencySymbol)}
                      </td>
                      <td className="text-right">
                        <span className={`balance-badge ${isSettled ? 'settled' : 'due'}`}>
                          {isSettled ? 'Settled' : formatCurrency(balance, currencySymbol)}
                        </span>
                      </td>
                      <td
                        className="text-center"
                        style={{ whiteSpace: 'nowrap' }}
                        onClick={(e) => e.stopPropagation()}
                      >
                        <div className="action-button-row">
                          <button
                            type="button"
                            onClick={() => setSelectedBookingForInspect(b)}
                            className="btn btn-secondary btn-xs"
                            title="Open Folio Inspector"
                          >
                            <ReceiptIcon size={13} />
                            <span>Inspect</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => setSelectedBookingForService(b)}
                            className="btn btn-secondary btn-xs"
                            title="Add Chargeable Service to Folio"
                          >
                            <ServicesIcon size={13} />
                            <span>Service</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => setSelectedBookingForPayment(b)}
                            className="btn btn-primary btn-xs"
                            title="Record Cash/Card Payment"
                          >
                            <RupeeIcon size={13} />
                            <span>Pay</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => setSelectedBookingForRazorpay(b)}
                            className="btn btn-success-outline btn-xs"
                            title="Razorpay Online Payment Link"
                          >
                            <CreditCardIcon size={13} />
                            <span>Razorpay</span>
                          </button>
                          <button
                            type="button"
                            onClick={() => setSelectedBookingForInvoice(b)}
                            className="btn btn-secondary btn-xs"
                            title="Print Tax Invoice & Folio"
                          >
                            <PrintIcon size={13} />
                            <span>Invoice</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Tab 2: Payments History */}
      {activeTab === 'payments' && (
        <div className="table-responsive">
          {loading ? (
            <div className="dashboard-loading" style={{ minHeight: '260px' }}>
              <div className="spinner-large" />
              <p>Loading payment ledger...</p>
            </div>
          ) : payments.length === 0 ? (
            <div className="empty-state-card">
              <div className="empty-state-icon-wrapper">
                <RupeeIcon size={32} />
              </div>
              <h3 className="font-bold text-lg text-emerald-950">No Payments Recorded</h3>
              <p className="text-muted">Recorded guest receipts and online gateway payments will appear here.</p>
            </div>
          ) : (
            <table className="dashboard-table">
              <thead>
                <tr>
                  <th>Transaction Ref</th>
                  <th>Reservation</th>
                  <th>Guest</th>
                  <th>Method</th>
                  <th>Type</th>
                  <th>Gateway</th>
                  <th>Status</th>
                  <th className="text-right">Amount</th>
                  <th>Date & Time</th>
                  <th className="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {payments.map((pmt) => (
                  <tr key={pmt.id}>
                    <td className="code bold" style={{ color: 'var(--color-sidebar-active-text)' }}>
                      {pmt.transaction_reference}
                    </td>
                    <td className="code">{pmt.booking?.booking_number}</td>
                    <td>
                      {pmt.booking?.guest ? `${pmt.booking.guest.first_name} ${pmt.booking.guest.last_name}` : 'N/A'}
                    </td>
                    <td>
                      <span className="payment-method-pill">{pmt.payment_method}</span>
                    </td>
                    <td>{pmt.payment_type}</td>
                    <td>{pmt.payment_gateway || 'Manual'}</td>
                    <td>
                      <span
                        className={`status-pill ${
                          pmt.status === 'SUCCESS'
                            ? 'confirmed'
                            : pmt.status === 'FAILED'
                            ? 'cancelled'
                            : pmt.status === 'REFUNDED'
                            ? 'refunded'
                            : 'pending'
                        }`}
                      >
                        <span className="status-dot active" />
                        {pmt.status}
                      </span>
                    </td>
                    <td className="text-right bold text-forest-green" style={{ fontSize: '0.95rem' }}>
                      {formatCurrency(pmt.amount, currencySymbol)}
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {formatDate(pmt.created_at, true)}
                    </td>
                    <td className="text-center" style={{ whiteSpace: 'nowrap' }}>
                      {isFinancialManager && pmt.status === 'SUCCESS' && (
                        <button
                          type="button"
                          onClick={() => setSelectedPaymentForRefund(pmt)}
                          className="btn btn-danger-outline btn-xs"
                          title="Authorize refund against payment"
                        >
                          Refund
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Tab 3: Refunds */}
      {activeTab === 'refunds' && (
        <div className="table-responsive">
          {loading ? (
            <div className="dashboard-loading" style={{ minHeight: '260px' }}>
              <div className="spinner-large" />
              <p>Loading refund records...</p>
            </div>
          ) : refunds.length === 0 ? (
            <div className="empty-state-card">
              <div className="empty-state-icon-wrapper">
                <BillingIcon size={32} />
              </div>
              <h3 className="font-bold text-lg text-emerald-950">No Refunds Issued</h3>
              <p className="text-muted">Any authorized refunds against payments will appear here with audited reasons.</p>
            </div>
          ) : (
            <table className="dashboard-table">
              <thead>
                <tr>
                  <th>Refund Reference</th>
                  <th>Original Payment</th>
                  <th>Reservation</th>
                  <th>Method</th>
                  <th>Reason</th>
                  <th className="text-right">Refund Amount</th>
                  <th>Date & Time</th>
                  <th>Approved By</th>
                </tr>
              </thead>
              <tbody>
                {refunds.map((ref) => (
                  <tr key={ref.id}>
                    <td className="code bold text-danger">{ref.refund_reference}</td>
                    <td className="code">{ref.payment?.transaction_reference}</td>
                    <td className="code">{ref.booking?.booking_number}</td>
                    <td>
                      <span className="payment-method-pill">{ref.refund_method}</span>
                    </td>
                    <td className="italic" style={{ color: 'var(--color-text-secondary)' }}>{ref.reason}</td>
                    <td className="text-right bold text-danger" style={{ fontSize: '0.95rem' }}>
                      {formatCurrency(ref.amount, currencySymbol)}
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {formatDate(ref.created_at, true)}
                    </td>
                    <td>{ref.processed_by_user?.name || `Staff #${ref.processed_by}`}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Tab 4: Expenses & Cash Drawer */}
      {activeTab === 'register' && (
        <div className="cash-register-view">
          {/* Cash Register Summary Cards */}
          <div className="register-summary-grid">
            <div className="metric-card">
              <span className="metric-label">Opening Shift Float</span>
              <span className="metric-value">
                {formatCurrency(cashRegister?.opening_float || 0, currencySymbol)}
              </span>
              <span className="metric-hint">Starting cash verified in till</span>
            </div>
            <div className="metric-card">
              <span className="metric-label">Cash In (Collections)</span>
              <span className="metric-value text-forest-green">
                +{formatCurrency(cashRegister?.cash_in || 0, currencySymbol)}
              </span>
              <span className="metric-hint">Cash payments collected today</span>
            </div>
            <div className="metric-card">
              <span className="metric-label">Cash Out (Expenses & Refunds)</span>
              <span className="metric-value text-danger">
                -{formatCurrency(cashRegister?.cash_out || 0, currencySymbol)}
              </span>
              <span className="metric-hint">Petty cash spent & cash refunds</span>
            </div>
            <div className="metric-card highlight">
              <span className="metric-label">Expected Drawer Cash</span>
              <span className="metric-value">
                {formatCurrency(cashRegister?.expected_closing_cash || 0, currencySymbol)}
              </span>
              {cashRegister?.register?.status === 'CLOSED' ? (
                <span className="metric-hint text-forest-green font-semibold">
                  Reconciled! Variance: {formatCurrency(cashRegister.register.variance || 0, currencySymbol)}
                </span>
              ) : (
                <span className="metric-hint" style={{ color: 'var(--color-sidebar-active-text)', fontWeight: 600 }}>
                  ● Cash Drawer Active & Open
                </span>
              )}
            </div>
          </div>

          {/* Expenses Table */}
          <div className="card mt-4">
            <div className="card-header-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h2 className="card-section-title" style={{ margin: 0, border: 'none' }}>
                Operational Expenses & Petty Cash Ledger
              </h2>
              <div className="actions">
                <button
                  type="button"
                  onClick={() => setIsAddExpenseCatModal(true)}
                  className="btn btn-secondary btn-sm"
                >
                  <PlusIcon size={14} />
                  <span>New Expense Category</span>
                </button>
              </div>
            </div>

            <div className="table-responsive">
              {expenses.length === 0 ? (
                <p className="text-muted p-4 text-center">No operational expenses recorded for today.</p>
              ) : (
                <table className="dashboard-table">
                  <thead>
                    <tr>
                      <th>Expense Date</th>
                      <th>Category</th>
                      <th>Description</th>
                      <th>Receipt #</th>
                      <th>Payment Method</th>
                      <th className="text-right">Amount</th>
                      <th>Recorded By</th>
                    </tr>
                  </thead>
                  <tbody>
                    {expenses.map((exp) => (
                      <tr key={exp.id}>
                        <td style={{ whiteSpace: 'nowrap' }}>{formatDate(exp.expense_date)}</td>
                        <td className="bold">{exp.category?.name}</td>
                        <td>{exp.description}</td>
                        <td className="code">{exp.receipt_number || '—'}</td>
                        <td>
                          <span className="payment-method-pill">{exp.payment_method}</span>
                        </td>
                        <td className="text-right bold text-danger" style={{ fontSize: '0.95rem' }}>
                          {formatCurrency(exp.amount, currencySymbol)}
                        </td>
                        <td>{exp.recorded_by_user?.name || `User #${exp.recorded_by}`}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Folio Inspector Modal */}
      {selectedBookingForInspect && (
        <FolioInspectorModal
          isOpen={Boolean(selectedBookingForInspect)}
          booking={selectedBookingForInspect}
          onClose={() => setSelectedBookingForInspect(null)}
          onOpenRecordPayment={(b) => setSelectedBookingForPayment(b)}
          onOpenRazorpay={(b) => setSelectedBookingForRazorpay(b)}
          onOpenAddService={(b) => setSelectedBookingForService(b)}
          onOpenInvoice={(b) => setSelectedBookingForInvoice(b)}
          onOpenRefund={isFinancialManager ? (p) => setSelectedPaymentForRefund(p) : undefined}
        />
      )}

      {/* Record Payment Modal */}
      {selectedBookingForPayment && (
        <RecordPaymentModal
          isOpen={Boolean(selectedBookingForPayment)}
          booking={selectedBookingForPayment}
          onClose={() => setSelectedBookingForPayment(null)}
          onPaymentRecorded={() => {
            setSuccessMessage(`Payment recorded successfully for ${selectedBookingForPayment.booking_number}!`);
            refreshFinancialData();
          }}
        />
      )}

      {/* Razorpay Online Modal */}
      {selectedBookingForRazorpay && (
        <RazorpayPaymentModal
          isOpen={Boolean(selectedBookingForRazorpay)}
          booking={selectedBookingForRazorpay}
          onClose={() => setSelectedBookingForRazorpay(null)}
          onPaymentSuccess={() => {
            setSuccessMessage(`Online Razorpay payment verified for ${selectedBookingForRazorpay.booking_number}!`);
            refreshFinancialData();
          }}
        />
      )}

      {/* Invoice Print Modal */}
      {selectedBookingForInvoice && (
        <InvoicePrintModal
          isOpen={Boolean(selectedBookingForInvoice)}
          bookingId={selectedBookingForInvoice.id}
          onClose={() => setSelectedBookingForInvoice(null)}
          onInvoiceUpdated={() => {
            refreshFinancialData();
          }}
        />
      )}

      {/* Add Service Modal */}
      {selectedBookingForService && (
        <AddServiceModal
          isOpen={Boolean(selectedBookingForService)}
          booking={selectedBookingForService}
          onClose={() => setSelectedBookingForService(null)}
          onServiceAdded={() => {
            setSuccessMessage(`Service added to ${selectedBookingForService.booking_number}!`);
            refreshFinancialData();
          }}
        />
      )}

      {/* Issue Refund Modal */}
      {selectedPaymentForRefund && (
        <IssueRefundModal
          isOpen={Boolean(selectedPaymentForRefund)}
          payment={selectedPaymentForRefund}
          onClose={() => setSelectedPaymentForRefund(null)}
          onRefundIssued={() => {
            setSuccessMessage('Refund authorized and recorded successfully.');
            refreshFinancialData();
          }}
        />
      )}

      {/* Open Register Modal */}
      {isOpenRegisterModal && (
        <div className="modal-overlay" onClick={() => setIsOpenRegisterModal(false)} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '440px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                Open Cash Drawer
              </h2>
              <button
                type="button"
                onClick={() => setIsOpenRegisterModal(false)}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form
              onSubmit={handleOpenRegisterSubmit}
              style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}
            >
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <div className="form-group">
                  <label>Opening Float Balance ({currencySymbol}) *</label>
                  <div className="input-prefix-wrapper">
                    <span className="input-prefix">{currencySymbol}</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={openingFloat}
                      onChange={(e) => setOpeningFloat(e.target.value)}
                      required
                    />
                  </div>
                </div>
                <div className="form-group">
                  <label>Shift Notes</label>
                  <input
                    type="text"
                    placeholder="Morning shift opening cash verification..."
                    value={registerNotes}
                    onChange={(e) => setRegisterNotes(e.target.value)}
                  />
                </div>
              </div>
              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button type="button" onClick={() => setIsOpenRegisterModal(false)} className="btn btn-secondary">
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Open Register Float
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Reconcile Register Modal */}
      {isReconcileRegisterModal && (
        <div className="modal-overlay" onClick={() => setIsReconcileRegisterModal(false)} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '440px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                Reconcile & Close Cash Drawer
              </h2>
              <button
                type="button"
                onClick={() => setIsReconcileRegisterModal(false)}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form
              onSubmit={handleReconcileRegisterSubmit}
              style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}
            >
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <div className="folio-mini-card mb-3">
                  <div className="folio-row">
                    <span className="text-muted">Expected Till Cash:</span>
                    <span className="bold text-forest-green">
                      {formatCurrency(cashRegister?.expected_closing_cash || 0, currencySymbol)}
                    </span>
                  </div>
                </div>
                <div className="form-group">
                  <label>Actual Physical Cash Counted ({currencySymbol}) *</label>
                  <div className="input-prefix-wrapper">
                    <span className="input-prefix">{currencySymbol}</span>
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={closingCash}
                      onChange={(e) => setClosingCash(e.target.value)}
                      required
                      placeholder="Enter physical cash in drawer..."
                    />
                  </div>
                </div>
                <div className="form-group">
                  <label>Reconciliation Notes</label>
                  <input
                    type="text"
                    placeholder="Notes on any variance or discrepancies..."
                    value={registerNotes}
                    onChange={(e) => setRegisterNotes(e.target.value)}
                  />
                </div>
              </div>
              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button type="button" onClick={() => setIsReconcileRegisterModal(false)} className="btn btn-secondary">
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Reconcile & Submit
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Record Expense Modal */}
      {isAddExpenseModal && (
        <div className="modal-overlay" onClick={() => setIsAddExpenseModal(false)} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '480px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                Record Operational Expense
              </h2>
              <button
                type="button"
                onClick={() => setIsAddExpenseModal(false)}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form
              onSubmit={handleRecordExpenseSubmit}
              style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}
            >
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <div className="form-group">
                  <label>Expense Category *</label>
                  <select
                    value={expenseForm.expense_category_id}
                    onChange={(e) => setExpenseForm({ ...expenseForm, expense_category_id: e.target.value })}
                    required
                  >
                    {expenseCategories.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="form-group">
                    <label>Amount ({currencySymbol}) *</label>
                    <div className="input-prefix-wrapper">
                      <span className="input-prefix">{currencySymbol}</span>
                      <input
                        type="number"
                        step="0.01"
                        min="1.00"
                        value={expenseForm.amount}
                        onChange={(e) => setExpenseForm({ ...expenseForm, amount: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                  <div className="form-group">
                    <label>Payment Method *</label>
                    <select
                      value={expenseForm.payment_method}
                      onChange={(e) => setExpenseForm({ ...expenseForm, payment_method: e.target.value })}
                    >
                      <option value="CASH">Cash (Drawer Out)</option>
                      <option value="UPI">UPI / Net Banking</option>
                      <option value="CARD">Company Card</option>
                      <option value="BANK_TRANSFER">Bank Transfer</option>
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="form-group">
                    <label>Receipt / Voucher #</label>
                    <input
                      type="text"
                      placeholder="VCH-1029..."
                      value={expenseForm.receipt_number}
                      onChange={(e) => setExpenseForm({ ...expenseForm, receipt_number: e.target.value })}
                    />
                  </div>
                  <div className="form-group">
                    <label>Date *</label>
                    <input
                      type="date"
                      value={expenseForm.expense_date}
                      onChange={(e) => setExpenseForm({ ...expenseForm, expense_date: e.target.value })}
                      required
                    />
                  </div>
                </div>

                <div className="form-group">
                  <label>Description *</label>
                  <textarea
                    rows="2"
                    placeholder="Purchased vegetables, laundry detergent, printer paper..."
                    value={expenseForm.description}
                    onChange={(e) => setExpenseForm({ ...expenseForm, description: e.target.value })}
                    required
                  />
                </div>
              </div>

              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button type="button" onClick={() => setIsAddExpenseModal(false)} className="btn btn-secondary">
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Record Expense
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add Expense Category Modal */}
      {isAddExpenseCatModal && (
        <div className="modal-overlay" onClick={() => setIsAddExpenseCatModal(false)} role="dialog" aria-modal="true">
          <div
            className="modal-card"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '420px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}
          >
            <div className="modal-header" style={{ flexShrink: 0 }}>
              <h2 style={{ fontSize: '1.15rem', fontWeight: 700, margin: 0, color: 'var(--color-heading)' }}>
                New Expense Category
              </h2>
              <button
                type="button"
                onClick={() => setIsAddExpenseCatModal(false)}
                className="modal-close-btn"
                title="Close modal"
                aria-label="Close"
              >
                <XIcon size={16} />
              </button>
            </div>
            <form
              onSubmit={handleAddCategorySubmit}
              style={{ display: 'flex', flexDirection: 'column', flex: '1 1 auto', minHeight: 0, overflow: 'hidden' }}
            >
              <div className="modal-body-scrollable" style={{ flex: '1 1 auto', overflowY: 'auto', padding: '1.25rem 1.5rem' }}>
                <div className="form-group">
                  <label>Category Name *</label>
                  <input
                    type="text"
                    placeholder="e.g. Housekeeping Supplies, Kitchen Groceries, Utilities..."
                    value={categoryForm.name}
                    onChange={(e) => setCategoryForm({ ...categoryForm, name: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Description</label>
                  <input
                    type="text"
                    placeholder="Short description..."
                    value={categoryForm.description}
                    onChange={(e) => setCategoryForm({ ...categoryForm, description: e.target.value })}
                  />
                </div>
              </div>
              <div className="modal-actions-row" style={{ flexShrink: 0, padding: '0.85rem 1.5rem' }}>
                <button type="button" onClick={() => setIsAddExpenseCatModal(false)} className="btn btn-secondary">
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary">
                  Save Category
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
