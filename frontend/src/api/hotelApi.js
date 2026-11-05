import api from './axios';

export const hotelApi = {
  // Dashboard
  getDashboard: async (days = 7) => {
    const res = await api.get('/dashboard', { params: { days } });
    return res.data?.data;
  },

  // Room Types
  getRoomTypes: async (activeOnly = false) => {
    const res = await api.get('/room-types', { params: { active_only: activeOnly } });
    return res.data?.data || [];
  },

  createRoomType: async (data) => {
    const res = await api.post('/room-types', data);
    return res.data?.data;
  },

  updateRoomType: async (id, data) => {
    const res = await api.put(`/room-types/${id}`, data);
    return res.data?.data;
  },

  deleteRoomType: async (id) => {
    const res = await api.delete(`/room-types/${id}`);
    return res.data?.data;
  },

  // Rooms
  getRooms: async (params = {}) => {
    const res = await api.get('/rooms', { params });
    return res.data?.data || [];
  },

  getAvailableRooms: async (checkInDate, checkOutDate, roomTypeId = null) => {
    const params = { check_in_date: checkInDate, check_out_date: checkOutDate };
    if (roomTypeId) params.room_type_id = roomTypeId;
    const res = await api.get('/rooms/available', { params });
    return res.data?.data || [];
  },

  createRoom: async (data) => {
    const res = await api.post('/rooms', data);
    return res.data?.data;
  },

  updateRoom: async (id, data) => {
    const res = await api.put(`/rooms/${id}`, data);
    return res.data?.data;
  },

  updateRoomStatus: async (id, operationalStatus, notes = null) => {
    const res = await api.patch(`/rooms/${id}/status`, { operational_status: operationalStatus, notes });
    return res.data?.data;
  },

  toggleRoomActive: async (id, isActive = null) => {
    const payload = isActive !== null ? { is_active: isActive } : {};
    const res = await api.patch(`/rooms/${id}/toggle-active`, payload);
    return res.data?.data;
  },

  deleteRoom: async (id) => {
    const res = await api.delete(`/rooms/${id}`);
    return res.data?.data;
  },

  // Room Maintenance
  getMaintenanceList: async (params = {}) => {
    const res = await api.get('/room-maintenance', { params });
    return res.data?.data;
  },

  createMaintenance: async (data) => {
    const res = await api.post('/room-maintenance', data);
    return res.data?.data;
  },

  resolveMaintenance: async (id, data) => {
    const res = await api.post(`/room-maintenance/${id}/resolve`, data);
    return res.data?.data;
  },

  // Guests
  getGuests: async (params = {}) => {
    const res = await api.get('/guests', { params });
    return res.data?.data;
  },

  getGuest: async (id) => {
    const res = await api.get(`/guests/${id}`);
    return res.data?.data;
  },

  createGuest: async (data) => {
    const res = await api.post('/guests', data);
    return res.data?.data;
  },

  updateGuest: async (id, data) => {
    const res = await api.put(`/guests/${id}`, data);
    return res.data?.data;
  },

  // Bookings
  getBookings: async (params = {}) => {
    const res = await api.get('/bookings', { params });
    return res.data?.data;
  },

  getBooking: async (id) => {
    const res = await api.get(`/bookings/${id}`);
    return res.data?.data;
  },

  calculateRates: async (checkInDate, checkOutDate, roomIds) => {
    const res = await api.get('/bookings/calculate-rates', {
      params: {
        check_in_date: checkInDate,
        check_out_date: checkOutDate,
        room_ids: roomIds,
      },
    });
    return res.data?.data;
  },

  createBooking: async (data) => {
    const res = await api.post('/bookings', data);
    return res.data?.data;
  },

  cancelBooking: async (id, reason) => {
    const res = await api.post(`/bookings/${id}/cancel`, { reason });
    return res.data?.data;
  },

  // Check-In / Check-Out
  getTodayArrivals: async () => {
    const res = await api.get('/check-in-out/arrivals');
    return res.data?.data || [];
  },

  getTodayDepartures: async () => {
    const res = await api.get('/check-in-out/departures');
    return res.data?.data || [];
  },

  checkIn: async (bookingId, expectedCheckInTime = null) => {
    const res = await api.post(`/check-in-out/${bookingId}/check-in`, { expected_check_in_time: expectedCheckInTime });
    return res.data?.data;
  },

  checkOut: async (bookingId, lateCheckOutUntil = null) => {
    const res = await api.post(`/check-in-out/${bookingId}/check-out`, { late_check_out_until: lateCheckOutUntil });
    return res.data?.data;
  },

  markRoomCleaned: async (roomId) => {
    const res = await api.post(`/check-in-out/rooms/${roomId}/cleaned`);
    return res.data?.data;
  },

  // Batch 3: Hotel Services
  getServices: async (params = {}) => {
    const res = await api.get('/services', { params });
    return res.data?.data || [];
  },

  createService: async (data) => {
    const res = await api.post('/services', data);
    return res.data?.data;
  },

  updateService: async (id, data) => {
    const res = await api.put(`/services/${id}`, data);
    return res.data?.data;
  },

  toggleServiceActive: async (id) => {
    const res = await api.patch(`/services/${id}/toggle-active`);
    return res.data?.data;
  },

  addServiceToBooking: async (bookingId, data) => {
    const res = await api.post(`/bookings/${bookingId}/services`, data);
    return res.data?.data;
  },

  removeServiceFromBooking: async (bookingServiceId) => {
    const res = await api.delete(`/booking-services/${bookingServiceId}`);
    return res.data?.data;
  },

  // Batch 3: Payments & Billing
  getPayments: async (params = {}) => {
    const res = await api.get('/payments', { params });
    return res.data?.data;
  },

  recordPayment: async (bookingId, data) => {
    const res = await api.post(`/bookings/${bookingId}/payments`, data);
    return res.data?.data;
  },

  getBillingSummary: async (bookingId) => {
    const res = await api.get(`/bookings/${bookingId}/billing`);
    return res.data?.data;
  },

  // Batch 3: Razorpay Integration
  createRazorpayOrder: async (bookingId, amount, notes = {}) => {
    const res = await api.post(`/bookings/${bookingId}/razorpay/create-order`, { amount, notes });
    return res.data?.data;
  },

  verifyRazorpayPayment: async (data) => {
    const res = await api.post('/razorpay/verify-payment', data);
    return res.data?.data;
  },

  // Batch 3: Refunds
  getRefunds: async (params = {}) => {
    const res = await api.get('/refunds', { params });
    return res.data?.data;
  },

  recordRefund: async (paymentId, data) => {
    const res = await api.post(`/payments/${paymentId}/refunds`, data);
    return res.data?.data;
  },

  // Batch 3: Invoices
  getInvoices: async (params = {}) => {
    const res = await api.get('/invoices', { params });
    return res.data?.data;
  },

  getInvoice: async (id) => {
    const res = await api.get(`/invoices/${id}`);
    return res.data?.data;
  },

  generateBookingInvoice: async (bookingId) => {
    const res = await api.post(`/bookings/${bookingId}/invoice`);
    return res.data?.data;
  },

  getPrintableInvoice: async (id) => {
    const res = await api.get(`/invoices/${id}/printable`);
    return res.data?.data;
  },

  addInvoiceAdjustment: async (invoiceId, data) => {
    const res = await api.post(`/invoices/${invoiceId}/adjustments`, data);
    return res.data?.data;
  },

  // Batch 3: Expenses & Categories
  getExpenseCategories: async () => {
    const res = await api.get('/expense-categories');
    return res.data?.data || [];
  },

  createExpenseCategory: async (data) => {
    const res = await api.post('/expense-categories', data);
    return res.data?.data;
  },

  getExpenses: async (params = {}) => {
    const res = await api.get('/expenses', { params });
    return res.data?.data;
  },

  recordExpense: async (data) => {
    const res = await api.post('/expenses', data);
    return res.data?.data;
  },

  // Batch 3: Cash Register
  getCashRegisterSummary: async (date = null) => {
    const params = date ? { date } : {};
    const res = await api.get('/cash-register/summary', { params });
    return res.data?.data;
  },

  openCashRegister: async (openingFloat, notes = null) => {
    const res = await api.post('/cash-register/open', { opening_float: openingFloat, notes });
    return res.data?.data;
  },

  reconcileCashRegister: async (actualClosingCash, notes = null) => {
    const res = await api.post('/cash-register/reconcile', {
      actual_closing_cash: actualClosingCash,
      notes,
    });
    return res.data?.data;
  },

  // Batch 4: Global Search
  search: async (q) => {
    const res = await api.get('/search', { params: { q } });
    return res.data?.data || { bookings: [], guests: [], rooms: [] };
  },

  // Batch 4: Notifications
  getNotifications: async (params = {}) => {
    const res = await api.get('/notifications', { params });
    return res.data?.data || { notifications: [], unread_count: 0 };
  },

  getUnreadNotificationCount: async () => {
    const res = await api.get('/notifications/unread-count');
    return res.data?.data?.unread_count || 0;
  },

  markNotificationAsRead: async (id) => {
    const res = await api.patch(`/notifications/${id}/read`);
    return res.data?.data;
  },

  markAllNotificationsAsRead: async () => {
    const res = await api.post('/notifications/mark-all-read');
    return res.data;
  },

  // Batch 4: Settings
  getSettings: async () => {
    const res = await api.get('/settings');
    return res.data?.data;
  },

  updateSettings: async (settings) => {
    const res = await api.put('/settings', { settings });
    return res.data?.data;
  },

  // Batch 4: Roles & Staff Management
  getRoles: async () => {
    const res = await api.get('/roles');
    return res.data?.data || [];
  },

  getStaff: async (params = {}) => {
    const res = await api.get('/staff', { params });
    return res.data?.data;
  },

  createStaff: async (data) => {
    const res = await api.post('/staff', data);
    return res.data?.data;
  },

  updateStaff: async (id, data) => {
    const res = await api.put(`/staff/${id}`, data);
    return res.data?.data;
  },

  toggleStaffStatus: async (id) => {
    const res = await api.patch(`/staff/${id}/toggle-status`);
    return res.data?.data;
  },

  getAuditLogs: async (params = {}) => {
    const res = await api.get('/staff/audit-logs', { params });
    return res.data?.data;
  },

  // Batch 4: Reports
  getReportSummary: async (params = {}) => {
    const res = await api.get('/reports/summary', { params });
    return res.data?.data;
  },

  getReportOccupancy: async (params = {}) => {
    const res = await api.get('/reports/occupancy', { params });
    return res.data?.data;
  },

  getReportBookings: async (params = {}) => {
    const res = await api.get('/reports/bookings', { params });
    return res.data?.data;
  },

  getReportCheckInOut: async (params = {}) => {
    const res = await api.get('/reports/check-in-out', { params });
    return res.data?.data;
  },

  getReportPayments: async (params = {}) => {
    const res = await api.get('/reports/payments', { params });
    return res.data?.data;
  },

  getReportServices: async (params = {}) => {
    const res = await api.get('/reports/services', { params });
    return res.data?.data;
  },

  getReportExpenses: async (params = {}) => {
    const res = await api.get('/reports/expenses', { params });
    return res.data?.data;
  },

  getReportCashRegister: async (params = {}) => {
    const res = await api.get('/reports/cash-register', { params });
    return res.data?.data;
  },

  downloadReportCsv: (type, params = {}) => {
    const searchParams = new URLSearchParams({ type, ...params });
    window.open(`/api/reports/export?${searchParams.toString()}`, '_blank');
  },
};

export default hotelApi;

