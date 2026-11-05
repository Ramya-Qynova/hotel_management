/**
 * Role-Based Access Control (RBAC) definitions and permissions matrix.
 */

export const ROLE_ALLOWED_MODULES = {
  OWNER: [
    'dashboard',
    'bookings',
    'rooms',
    'guests',
    'checkin-checkout',
    'services',
    'billing',
    'reports',
    'settings',
  ],
  ADMIN: [
    'dashboard',
    'bookings',
    'rooms',
    'guests',
    'checkin-checkout',
    'services',
    'billing',
    'reports',
    'settings',
  ],
  RECEPTIONIST: [
    'dashboard',
    'bookings',
    'rooms',
    'guests',
    'checkin-checkout',
    'services',
    'billing',
    'reports',
  ],
  HOUSEKEEPING: [
    'dashboard',
    'rooms',
  ],
  ACCOUNTANT: [
    'dashboard',
    'billing',
    'reports',
  ],
};

export function getRoleName(user) {
  if (!user || !user.role) return '';
  return (user.role.name || '').toUpperCase().trim();
}

export function hasModuleAccess(user, moduleId) {
  if (!user) return false;
  const roleName = getRoleName(user);
  const allowed = ROLE_ALLOWED_MODULES[roleName];
  if (!allowed) return false;
  return allowed.includes(moduleId);
}

export function isOwner(user) {
  return getRoleName(user) === 'OWNER';
}

export function isAdminOrOwner(user) {
  const role = getRoleName(user);
  return role === 'OWNER' || role === 'ADMIN';
}

export function isFinancialRole(user) {
  const role = getRoleName(user);
  return role === 'OWNER' || role === 'ADMIN' || role === 'ACCOUNTANT';
}

export function isHousekeeping(user) {
  return getRoleName(user) === 'HOUSEKEEPING';
}

export function isReceptionist(user) {
  return getRoleName(user) === 'RECEPTIONIST';
}
