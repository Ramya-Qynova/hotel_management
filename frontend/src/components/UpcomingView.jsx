import React from 'react';

export default function UpcomingView({ title, phase = 'Batch 3' }) {
  const descriptions = {
    'Services': 'Chargeable amenities, room service, laundry, spa orders, food & beverage billing and service catalog management.',
    'Billing & Payments': 'Payment gateway integration, cash register drawer reconciliation, GST invoice generation, refunds, and accounting ledgers.',
    'Reports': 'Financial audits, monthly occupancy analytics, revenue per available room (RevPAR), average daily rate (ADR) and tax compliance summaries.',
    'Settings': 'Hotel profile branding, custom tax rules, staff permissions, cancellation refund tiers and payment gateway API keys.',
  };

  return (
    <div className="view-container">
      <div className="card upcoming-page-card">
        <div className="upcoming-badge-pill">Scheduled for {phase}</div>
        <h1 className="upcoming-title">{title}</h1>
        <p className="upcoming-desc">
          {descriptions[title] || 'This module is scheduled for implementation in the upcoming development batch.'}
        </p>

        <div className="upcoming-features-box">
          <h3 className="features-title">Planned Capabilities:</h3>
          <ul className="planned-list">
            <li>Fully integrated with Batch 1 Sanctum RBAC and Batch 2 Operations.</li>
            <li>No hardcoded values; fully configurable from the database.</li>
            <li>Audited database transactions with logging.</li>
          </ul>
        </div>
      </div>
    </div>
  );
}

