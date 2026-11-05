/**
 * Date and Currency Formatters for Grand Vista Hotel Management System
 * Guarantees human-readable dates (e.g., "31 Oct 2026") and consistent currency symbols.
 */

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/**
 * Formats a date string, timestamp, or Date object into "DD Mon YYYY" (e.g. "31 Oct 2026")
 * or "DD Mon YYYY, hh:mm AM/PM" if includeTime is true.
 */
export function formatDate(val, includeTime = false) {
  if (!val) return '—';
  try {
    // If it's a simple YYYY-MM-DD string, parse parts manually to avoid UTC timezone offset shifts
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val.trim())) {
      const [year, month, day] = val.trim().split('-');
      const monthIdx = parseInt(month, 10) - 1;
      const monthStr = MONTHS[monthIdx] || month;
      return `${parseInt(day, 10)} ${monthStr} ${year}`;
    }

    const d = new Date(val);
    if (isNaN(d.getTime())) return String(val);

    const day = d.getDate();
    const month = MONTHS[d.getMonth()];
    const year = d.getFullYear();

    if (!includeTime) {
      return `${day} ${month} ${year}`;
    }

    let hours = d.getHours();
    const minutes = d.getMinutes().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;

    return `${day} ${month} ${year}, ${hours}:${minutes} ${ampm}`;
  } catch {
    return String(val);
  }
}

/**
 * Formats a date range into e.g. "31 Oct 2026 – 03 Nov 2026"
 */
export function formatDateRange(startVal, endVal) {
  if (!startVal && !endVal) return '—';
  if (startVal && !endVal) return formatDate(startVal);
  if (!startVal && endVal) return formatDate(endVal);
  return `${formatDate(startVal)} → ${formatDate(endVal)}`;
}

/**
 * Formats a numeric amount using the hotel's configured currency symbol.
 */
export function formatCurrency(amount, symbol = '₹') {
  const num = Number(amount);
  if (isNaN(num)) return `${symbol}0`;
  const formatted = num.toLocaleString('en-IN', {
    minimumFractionDigits: num % 1 !== 0 ? 2 : 0,
    maximumFractionDigits: 2,
  });
  return `${symbol}${formatted}`;
}

