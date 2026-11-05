import React, { useEffect, useRef } from 'react';

/**
 * Reusable PageTransition wrapper component.
 * Wraps page views and triggers GPU-accelerated entrance and staggered animations
 * whenever navigating between sidebar routes.
 *
 * @param {Object} props
 * @param {string} props.pageKey - Active module tab key (e.g., 'dashboard', 'bookings')
 * @param {React.ReactNode} props.children - View content to render
 * @param {string} [props.className] - Optional extra class names
 */
export default function PageTransition({ pageKey, children, className = '' }) {
  const containerRef = useRef(null);

  // Scroll content viewport back to top smoothly on route change
  useEffect(() => {
    if (typeof window !== 'undefined') {
      window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    }
    if (containerRef.current) {
      const scrollParent = containerRef.current.closest('.main-wrapper');
      if (scrollParent && typeof scrollParent.scrollTo === 'function') {
        scrollParent.scrollTo({ top: 0, left: 0, behavior: 'instant' });
      }
    }
  }, [pageKey]);

  return (
    <div
      key={pageKey}
      ref={containerRef}
      className={`page-transition-wrapper ${className}`.trim()}
      data-page-key={pageKey}
    >
      {children}
    </div>
  );
}

