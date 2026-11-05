/**
 * Reusable Form Validation Utilities for Hotel Management System
 * Complies with Requirement 7 (10-digit Indian phone validation)
 * and Requirement 9 (consistent mandatory & optional rule enforcement).
 */

export const PHONE_REGEX = /^[0-9]{10}$/;
export const PHONE_ERROR_MSG = 'Phone number must contain exactly 10 numeric digits (no country code or special characters).';

/**
 * Validate that phone number contains exactly 10 digits.
 * Rejects letters, symbols, fewer than 10, and more than 10.
 * Trims leading/trailing whitespace before testing.
 *
 * @param {string|number} phone
 * @returns {boolean}
 */
export function isValidPhone(phone) {
  if (phone === null || phone === undefined) return false;
  const cleaned = String(phone).trim();
  return PHONE_REGEX.test(cleaned);
}

/**
 * Clean phone input by removing whitespace only (does not truncate invalid characters).
 *
 * @param {string} phone
 * @returns {string}
 */
export function sanitizePhone(phone) {
  return String(phone || '').trim();
}

/**
 * Standard input props for phone inputs to enforce tel keyboard and length constraint.
 */
export const PHONE_INPUT_PROPS = {
  type: 'tel',
  pattern: '[0-9]{10}',
  maxLength: 10,
  placeholder: '10-digit mobile number',
  autoComplete: 'tel',
};

/**
 * Basic email format validator.
 *
 * @param {string} email
 * @returns {boolean}
 */
export function isValidEmail(email) {
  if (!email || !String(email).trim()) return true; // Optional if empty
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(String(email).trim());
}

