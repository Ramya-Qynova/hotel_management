# HOTEL MANAGEMENT SYSTEM
## Software Requirements & Technical Documentation

Project Type Web-based Hotel Management System  
Frontend React.js  
Backend Laravel (PHP 8.3+)  
Database MySQL  
Local Development Environment XAMPP  
Development Assistant Google Antigravity  
Architecture React frontend + Laravel REST API backend

---

## 1. Project Overview

The Hotel Management System is designed to manage the daily operations of a hotel through a centralized web application.

The system will allow hotel owners and staff to manage rooms, reservations, guests, payments, check-incheck-out, additional services, invoices, cancellations, and business reports.

The application must support both walk-in bookings and online reservations.

The primary objective is to reduce manual work, prevent double bookings, improve billing accuracy, and provide the hotel owner with a clear overview of hotel operations and revenue.

## 2. Hotel Business Configuration

The initial system will be developed for a hotel with 20 rooms.

 Room category  Number of rooms  Base price per night 
---------
 AC Room  10  ₹3,000 
 Normal Room  10  ₹2,000 
 Total  20  

These prices are initial default rates. The hotel owner must be able to change room prices through the admin panel.

### Configurable hotel settings

The owner must be able to configure

- Hotel name, address, contact details and logo.
- Room prices.
- Check-in time.
- Check-out time.
- Advance payment percentage or fixed amount.
- Applicable taxes.
- Cancellation and refund policy.
- Booking hold expiration time.
- Extra guest charges.
- Extra bed charges.
- Early check-in and late check-out charges.
- Service prices.
- Invoice details and numbering.

The application must not hardcode business values that the owner should be able to configure.

## 3. User Roles and Permissions

The system should support role-based access control.

 Role  Responsibilities 
------
 Super Admin  Complete system access and configuration 
 Owner  Hotel settings, bookings, payments, reports and staff management 
 Manager  Manage daily hotel operations 
 Receptionist  Guest registration, bookings, payments and check-incheck-out 
 Housekeeping  Room cleaning and room readiness updates 
 Accountant  Payments, invoices, refunds and financial reports 

Permissions must be checked on the Laravel backend. Hiding a button in React is not sufficient security.

## 4. Main System Modules

### 4.1 Dashboard

The dashboard should display

- Total rooms.
- Occupied rooms.
- Available rooms.
- Rooms under cleaning.
- Rooms under maintenance.
- Today's check-ins.
- Today's check-outs.
- Pending advance payments.
- Today's payment collections.
- Monthly room revenue.
- Additional service revenue.
- Outstanding customer balances.
- Cancellation statistics.

The dashboard should provide date filters and useful summaries.

### 4.2 Room Management

The owner or authorized staff must be able to

- Add rooms.
- Edit room details.
- Assign room categories.
- Set room prices.
- Update room operational status.
- View room availability.
- Mark rooms for cleaning.
- Mark rooms under maintenance.
- View room booking history.

Initial room setup

- AC-101 to AC-110 AC rooms.
- N-201 to N-210 Normal rooms.

Room operational statuses

- READY
- CLEANING
- MAINTENANCE
- OUT_OF_SERVICE

Room operational status and booking status must be maintained separately.

### 4.3 Guest Management

The system must store

- Guest full name.
- Phone number.
- Email address.
- Address.
- Guest count.
- Relevant identification details, where legally required.
- Booking history.

Guest information should be reusable for future bookings.

Sensitive guest documents must be protected with restricted access and appropriate retention controls.

### 4.4 Booking Management

The system must support two booking sources

1. WALK_IN
2. ONLINE

Both booking methods must use the same backend booking and availability logic.

Booking workflow

1. Select check-in date.
2. Select check-out date.
3. Select room category.
4. Check availability.
5. Enter guest details.
6. Calculate room charges.
7. Calculate required advance.
8. Collect or verify advance payment.
9. Confirm the booking.
10. Generate booking confirmation.

Booking statuses

- PENDING_PAYMENT
- CONFIRMED
- CHECKED_IN
- CHECKED_OUT
- CANCELLED
- EXPIRED
- NO_SHOW

Only valid status transitions should be allowed.

### 4.5 Booking Availability Rules

The system must prevent double bookings.

A room is unavailable when an existing active reservation overlaps the requested dates.

Overlap condition

existing_check_in  new_check_out AND existing_check_out  new_check_in

Example

- Guest A books November 5 to November 8.
- Guest A occupies the room for three nights.
- Guest B can check in on November 8.

The checkout date is exclusive.

Availability must consider

- Confirmed reservations.
- Active temporary booking holds.
- Room maintenance periods.
- Out-of-service rooms.

Availability must be verified on the server immediately before a booking is committed.

The application must use database transactions and appropriate row locking to prevent simultaneous bookings from reserving the same room.

For online bookings, a temporary hold should expire automatically if payment is not completed within the configured time.

### 4.6 Advance Payment Management

An advance payment is required before a booking becomes confirmed.

Example

Room price ₹3,000 per night  
Number of nights 2  
Room total ₹6,000  
Advance percentage 30%  
Required advance ₹1,800  
Remaining balance ₹4,200

The advance percentage must be configurable by the owner.

Payment methods

- CASH
- UPI
- CARD
- BANK_TRANSFER
- ONLINE_GATEWAY

Payment statuses

- PENDING
- SUCCESS
- FAILED
- PARTIALLY_REFUNDED
- REFUNDED

Every payment must be stored as a separate transaction.

The system must never overwrite an old payment record to represent a new payment.

For online payments, booking confirmation must happen only after the backend verifies the payment gateway response or webhook.

Never store complete card numbers, CVVs or other sensitive card credentials.

### 4.7 Check-in Management

Reception staff must be able to check in a guest with a confirmed booking.

Check-in process

1. Search booking using booking number or guest phone.
2. Verify guest details.
3. Verify payment status.
4. Confirm room assignment.
5. Record actual check-in time.
6. Update booking status to CHECKED_IN.

Only authorized staff should be able to perform check-in.

### 4.8 Check-out Management

Check-out process

1. Retrieve active booking.
2. Calculate room charges.
3. Calculate additional service charges.
4. Calculate applicable taxes.
5. Deduct net payments already received.
6. Display outstanding balance.
7. Collect remaining payment.
8. Generate final invoice.
9. Record actual check-out time.
10. Change booking status to CHECKED_OUT.
11. Change room status to CLEANING.

A room must not automatically become READY immediately after checkout.

Housekeeping must mark the room READY after cleaning.

### 4.9 Additional Services

The hotel must support additional services such as

- Food.
- Laundry.
- Extra bed.
- Other services.

The owner must be able to add, edit and deactivate services and configure their prices.

Each service entry must record

- Booking.
- Room, where applicable.
- Service name.
- Quantity.
- Unit price at the time of service.
- Applicable tax.
- Total amount.
- Date and time.
- Staff member who added the service.

Service prices must be calculated on the backend.

### 4.10 Billing and Invoices

The system must generate invoices containing

- Hotel information.
- Invoice number.
- Booking number.
- Guest information.
- Room details.
- Check-in and check-out dates.
- Number of nights.
- Room charges.
- Additional service charges.
- Discounts, if authorized.
- Applicable taxes.
- Grand total.
- Payments received.
- Refunds issued.
- Outstanding balance.
- Payment method details.

Invoice items must preserve the prices and tax rates applicable when the invoice was issued.

Issued invoices should be immutable. Corrections should use appropriate adjustment or credit-note records rather than silently changing historical invoices.

### 4.11 Cancellation and Refund Management

The owner must be able to configure cancellation policies.

Example policy (illustrative only)

 Cancellation timing  Example refund 
------
 More than 48 hours before check-in  100% 
 24–48 hours before check-in  50% 
 Less than 24 hours before check-in  0% 

The actual policy must be confirmed by the hotel owner.

The system must record

- Cancellation date and time.
- Cancellation reason.
- Policy version used.
- Original booking amount.
- Amount paid.
- Cancellation charge.
- Refund amount.
- Refund status.
- Staff member who processed the cancellation.

Refunds must be tracked separately from payments.

## 5. Database Design

The following database tables are recommended.

### 5.1 User and Permission Tables

- users
- roles
- permissions
- role_permissions

### 5.2 Room Tables

- room_types
- rooms
- room_rate_history
- room_maintenance

### 5.3 Guest Tables

- guests
- guest_documents

### 5.4 Booking Tables

- bookings
- booking_rooms
- booking_status_history
- booking_holds

### 5.5 Payment Tables

- payments
- payment_transactions
- refunds

### 5.6 Service Tables

- service_categories
- services
- booking_services

### 5.7 Billing Tables

- invoices
- invoice_items
- tax_configurations

### 5.8 Configuration and Audit Tables

- hotel_settings
- hotel_profiles
- audit_logs

### Important Database Relationships

- One RoomType has many Rooms.
- One Room belongs to one RoomType.
- One Guest has many Bookings.
- One Booking belongs to one Guest.
- One Booking has many BookingRooms.
- One BookingRoom refers to a room category and, when allocated, a physical Room.
- One Room can appear in many BookingRooms across different dates.
- One Booking has many Payments.
- One Payment can have multiple payment transaction attempts.
- One Payment can have multiple Refund records.
- One Booking has many BookingServices.
- One Booking has one or more Invoice records, depending on the billing design.
- One Invoice has many InvoiceItems.
- One User can perform many operational actions.

### Important Database Rules

1. Use foreign keys for relational integrity.
2. Add indexes for booking dates, room IDs, guest phone numbers and booking numbers.
3. Booking numbers and invoice numbers must be unique.
4. Store monetary values using DECIMAL or integer paise, never floating-point types.
5. Store dates and timestamps consistently.
6. Store booked room prices as snapshots so historical bookings do not change when current room rates change.
7. Store the tax rate and tax amount applicable to each invoice item.
8. Use database transactions for booking, payment and refund operations.
9. Add idempotency protection for booking requests and payment webhooks.

## 6. Laravel Eloquent Models

Create Eloquent models under

appModels

Recommended models

- User
- Role
- Permission
- Guest
- GuestDocument
- RoomType
- Room
- RoomRateHistory
- RoomMaintenance
- Booking
- BookingRoom
- BookingStatusHistory
- BookingHold
- Payment
- PaymentTransaction
- Refund
- ServiceCategory
- Service
- BookingService
- Invoice
- InvoiceItem
- TaxConfiguration
- HotelSetting
- HotelProfile
- AuditLog

Each model must define the correct relationships.

Examples

RoomType
- hasMany(Roomclass)

Room
- belongsTo(RoomTypeclass)

Guest
- hasMany(Bookingclass)

Booking
- belongsTo(Guestclass)
- hasMany(BookingRoomclass)
- hasMany(Paymentclass)
- hasMany(BookingServiceclass)
- hasMany(BookingStatusHistoryclass)

Payment
- belongsTo(Bookingclass)
- hasMany(PaymentTransactionclass)
- hasMany(Refundclass)

Invoice
- belongsTo(Bookingclass)
- hasMany(InvoiceItemclass)

Models should include appropriate fillable fields, casts, and relationship methods. Complicated business rules should be handled by service classes rather than putting all logic into models.

## 7. Recommended Laravel Backend Structure

Use a clean and maintainable Laravel structure.

app
- Models
- Http
  - Controllers
  - Requests
  - Resources
  - Middleware
- Services
- Policies
- Enums
- Events
- Listeners
- Jobs

Responsibilities

Controllers Receive HTTP requests and return responses.

Form Requests Validate incoming data.

Models Represent database tables and relationships.

Services Handle booking, availability, payment, cancellation, billing and check-incheck-out business logic.

Policies Control user permissions.

API Resources Format API responses.

Jobs Handle background work such as expiring booking holds.

## 8. REST API Endpoints

All APIs should use the api prefix.

### Authentication

- POST apiauthlogin
- POST apiauthlogout
- GET apiauthme

### Dashboard

- GET apidashboard

### Rooms

- GET apirooms
- POST apirooms
- GET apirooms{id}
- PUT apirooms{id}
- GET apiroom-types
- POST apiroom-types

### Availability

- GET apiavailability

Parameters

- check_in
- check_out
- room_type_id
- guests

### Guests

- GET apiguests
- POST apiguests
- GET apiguests{id}
- PUT apiguests{id}

### Bookings

- GET apibookings
- POST apibookings
- GET apibookings{id}
- POST apibookings{id}confirm
- POST apibookings{id}cancel
- POST apibookings{id}check-in
- POST apibookings{id}check-out

### Payments

- GET apibookings{id}payments
- POST apibookings{id}payments
- POST apipayments{id}refund
- POST apipaymentswebhook

### Services

- GET apiservices
- POST apiservices
- POST apibookings{id}services

### Billing

- GET apibookings{id}invoice
- POST apibookings{id}invoice

### Reports

- GET apireportsrevenue
- GET apireportsoccupancy
- GET apireportspayments
- GET apireportscancellations

All endpoints must have authentication, authorization and server-side validation as appropriate.

## 9. React Frontend Pages

Recommended frontend pages

### Authentication
- Login
- Forgot Password

### Dashboard
- Dashboard Overview

### Room Management
- Room List
- Add Room
- Edit Room
- Room Categories
- Room Availability Calendar
- Maintenance Management

### Guest Management
- Guest List
- Add Guest
- Guest Profile
- Guest Booking History

### Booking Management
- New Booking
- Walk-in Booking
- Online Booking
- Booking List
- Booking Details
- Booking Calendar
- Cancellation Management

### Reception
- Today's Check-ins
- Today's Check-outs
- Check-in Screen
- Check-out Screen

### Payments and Billing
- Payment List
- Record Payment
- Refund Management
- Invoice List
- Invoice Details

### Services
- Service Categories
- Service List
- Add Service to Booking

### Reports
- Revenue Report
- Occupancy Report
- Payment Report
- Cancellation Report

### Settings
- Hotel Profile
- Room Pricing
- Tax Settings
- Advance Payment Settings
- Check-inCheck-out Settings
- Cancellation Policy
- User and Role Management

## 10. Business Logic and Validation

All business-critical calculations must happen in Laravel, not React.

Examples

- Validate check-out date is after check-in date.
- Validate guest count against room capacity.
- Verify room availability before confirming a booking.
- Verify advance payment requirements.
- Reject unauthorized status changes.
- Reject payments with invalid amounts.
- Prevent duplicate payment webhook processing.
- Prevent double check-in.
- Prevent check-out before check-in.
- Prevent cancellation of already checked-out bookings.
- Validate refund amount against refundable balance.
- Prevent services from being added to an invalid booking.
- Validate room readiness before check-in.

The frontend should display validation errors returned by Laravel.

## 11. Security Requirements

- Use Laravel authentication, such as Sanctum for API authentication.
- Enforce role-based access control on the backend.
- Validate and sanitize incoming data.
- Use HTTPS in production.
- Protect payment webhook endpoints.
- Do not trust frontend-calculated prices.
- Do not store sensitive card information.
- Protect guest identification documents.
- Record important actions in audit_logs.
- Restrict access to financial and personal information.
- Use secure environment variables for database and payment credentials.
- Apply appropriate database backups and access restrictions.

## 12. Reports and Business Calculations

The system should distinguish between

- Booking value Total value of reservations.
- Collections Actual successful payments received.
- Refunds Payments returned to guests.
- Net collections Successful payments minus refunds.
- Room revenue Revenue from accommodation.
- Service revenue Revenue from additional services.
- Outstanding balance Amount still owed by guests.

Example initial maximum daily room revenue at full occupancy

AC rooms 10 × ₹3,000 = ₹30,000

Normal rooms 10 × ₹2,000 = ₹20,000

Total ₹50,000 per day before taxes, discounts and other adjustments.

## 13. Development Phases

Development must happen in the following order.

Phase 1 – Project Setup
- Set up Laravel backend.
- Set up React frontend.
- Configure MySQL.
- Configure local environment.
- Establish API communication.

Phase 2 – Database and Authentication
- Create migrations.
- Create Eloquent models.
- Define model relationships.
- Create seeders.
- Implement login and role-based permissions.

Phase 3 – Room Management
- Room categories.
- Room creation and editing.
- Room pricing.
- Room operational status.

Phase 4 – Guest Management
- Guest registration.
- Guest profiles.
- Guest history.

Phase 5 – Booking Engine
- Availability checking.
- Walk-in bookings.
- Online booking workflow.
- Booking status management.
- Double-booking prevention.

Phase 6 – Advance Payments
- Payment recording.
- Payment gateway integration.
- Payment verification.
- Booking confirmation.

Phase 7 – Check-in and Check-out
- Guest check-in.
- Room assignment.
- Guest check-out.
- Room cleaning workflow.

Phase 8 – Additional Services and Billing
- Service management.
- Service charges.
- Tax calculation.
- Invoice generation.

Phase 9 – Cancellation and Refunds
- Cancellation rules.
- Refund calculations.
- Refund tracking.

Phase 10 – Dashboard and Reports
- Revenue.
- Occupancy.
- Payments.
- Outstanding balances.
- Operational reports.

Phase 11 – Testing and Deployment
- Functional testing.
- Security testing.
- Concurrency testing.
- User acceptance testing.
- Production deployment.

Do not start the next major phase until the previous phase has been tested and approved.

## 14. Testing Requirements

The development team must test

1. Room creation and editing.
2. Correct AC and Normal room pricing.
3. Guest registration.
4. Walk-in booking.
5. Online booking.
6. Booking availability.
7. Simultaneous booking attempts for the same room.
8. Advance payment calculation.
9. Payment success and failure.
10. Duplicate payment webhook requests.
11. Check-in and check-out.
12. Additional service charges.
13. Tax calculations.
14. Invoice generation.
15. Cancellation and refunds.
16. Role permissions.
17. Dashboard calculations.
18. Date boundary conditions.

Automated Laravel feature tests and React component tests should be included.


## 15. Important Development Instructions

1. Follow the approved requirements and database design.
2. Do not hardcode room rates, taxes, timings or cancellation policies.
3. Use reusable React components.
4. Use Laravel service classes for business logic.
5. Use Eloquent relationships correctly.
6. Use database transactions for critical operations.
7. Prevent double booking through concurrency-safe allocation.
8. Use server-side validation for all important operations.
9. Maintain clear naming conventions.
10. Keep frontend and backend code organized.
11. Write tests for critical business rules.
12. Do not modify existing functionality without checking dependencies.
13. Use Git branches and pull requests for team development.
14. Document API changes and database changes.
15. Obtain owner approval before changing hotel business rules.

## 16. Definition of Project Completion

The project will be considered ready for initial production use when

- All 20 rooms are configured correctly.
- Room prices can be changed by the owner.
- Staff can create walk-in bookings.
- Customers can make online reservations.
- Double bookings are prevented.
- Advance payments are handled correctly.
- Check-in and check-out work correctly.
- Additional services are billed correctly.
- Invoices and refunds are accurate.
- Taxes and cancellation policies are configurable.
- Staff permissions work correctly.
- Dashboard and reports show accurate information.
- Critical automated tests pass.
- Database backup and recovery procedures are established.
- The application has been tested and approved by the hotel owner.

---

End of Project Documentation

Note This document describes the initial scope and technical direction. Final tax rules, payment gateway, cancellation terms, guest-document requirements and operational policies must be approved by the hotel owner and relevant professionals before production deployment.