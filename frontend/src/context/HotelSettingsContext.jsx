import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import hotelApi from '../api/hotelApi';

const DEFAULT_SETTINGS = {
  hotel_name: '',
  hotel_tagline: '',
  hotel_email: '',
  hotel_phone: '',
  hotel_address: '',
  hotel_logo_url: '',
  check_in_time: '14:00',
  check_out_time: '11:00',
  hold_expiration_minutes: 30,
  tax_rate_percent: 12.0,
  tax_name: 'GST',
  hotel_gstin: '',
  currency_symbol: '₹',
  currency_code: 'INR',
  invoice_prefix: 'INV',
  invoice_footer_note: '',
  advance_payment_percent: 50.0,
  cancellation_policy: '',
  cancellation_grace_hours: 24,
  house_rules: '',
};

const HotelSettingsContext = createContext({
  settings: DEFAULT_SETTINGS,
  currencySymbol: '₹',
  refreshSettings: async () => {},
  updateLocalSettings: () => {},
  loadingSettings: false,
});

export function HotelSettingsProvider({ children, user }) {
  const [settings, setSettings] = useState(DEFAULT_SETTINGS);
  const [loadingSettings, setLoadingSettings] = useState(false);

  const refreshSettings = useCallback(async () => {
    setLoadingSettings(true);
    try {
      const data = await hotelApi.getSettings();
      if (data?.settings) {
        setSettings((prev) => ({ ...prev, ...data.settings }));
      }
    } catch {
      // Use defaults if settings endpoint not accessible
    } finally {
      setLoadingSettings(false);
    }
  }, []);

  // Fetch settings on initial mount (public branding) and when user auth state changes
  useEffect(() => {
    refreshSettings();
  }, [user, refreshSettings]);

  // Sync document title with dynamic hotel name
  useEffect(() => {
    if (typeof document !== 'undefined') {
      const name = settings.hotel_name?.trim();
      document.title = name ? `${name} - Hotel Management System` : 'Hotel Management System';
    }
  }, [settings.hotel_name]);

  const updateLocalSettings = useCallback((newSettings) => {
    setSettings((prev) => ({ ...prev, ...newSettings }));
  }, []);

  const currencySymbol = settings.currency_symbol || '₹';

  return (
    <HotelSettingsContext.Provider
      value={{
        settings,
        currencySymbol,
        refreshSettings,
        updateLocalSettings,
        loadingSettings,
      }}
    >
      {children}
    </HotelSettingsContext.Provider>
  );
}

export function useHotelSettings() {
  return useContext(HotelSettingsContext);
}
