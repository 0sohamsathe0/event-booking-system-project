# Event Booking System

A single-hall event booking application built with Core PHP, MariaDB/MySQL,
HTML, CSS, Vanilla JavaScript, and Razorpay.

## Local development

1. Start Apache and MySQL from XAMPP.
2. Import `database/event_booking_system.sql` through phpMyAdmin.
3. Configure the hall record in phpMyAdmin.
4. If your database credentials differ from the XAMPP defaults, copy
   `config/database.php` to `config/database.local.php` and edit the local copy.
5. Serve the project through Apache and open the project's `/public/` directory.

For PHP's built-in server, run:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public\index.php
```

Existing Phase 13 databases must receive the additive Phase 14 upgrade once:

```powershell
C:\xampp\php\php.exe scripts\apply_phase_14_upgrade.php
```

For the safest Apache setup, configure a virtual host whose document root is the
`public` directory. The root `index.php` redirects basic folder-based access to
that directory.

## Development progress

- **Phase 0 – Inspection:** Confirmed that development started as a fresh project.
- **Phase 1 – Requirements:** Defined customers, organizers, admins, events,
  ticket booking, payments, cancellations, and dashboard requirements.
- **Phase 2 – Planning:** Finalized the single-hall rules, workflows, permissions,
  security requirements, and phased implementation plan.
- **Phase 3 – Database design:** Created the ERD, DFD, entity relationships,
  inventory model, Razorpay flow, and architecture documentation.
- **Phase 4 – SQL implementation:** Created the database schema with 13 tables,
  constraints, foreign keys, indexes, and default event categories.
- **Phase 5 – PHP architecture:** Added the router, PDO connection, sessions,
  CSRF support, views, responsive public layout, storage, and configuration.
- **Phase 6 – Authentication:** Added customer and organizer registration, shared
  login, secure passwords, logout, account-status handling, and role guards.
- **Phase 7 – Organizer management:** Added the admin dashboard, organizer lists,
  approval/rejection with reasons, notifications, and rejected-organizer
  resubmission.
- **Phase 8 – Event management:** Added approved-organizer event listings,
  creation, editing/resubmission, ownership protection, schedule and capacity
  validation, status history, admin notifications, and secure poster uploads.
- **Phase 9 – Hall scheduling validation:** Added admin event review, approval
  and rejection, required rejection reasons, hall-row locking, organizer and
  capacity checks, and transactional approved-event overlap prevention.
- **Phase 10 – Ticket types and inventory:** Added organizer ticket-type
  creation and editing, prices including free tickets, capacities, display
  order, activation/deactivation, inventory counters, event-capacity enforcement,
  and automatic reapproval requirements after ticket changes.
- **Visual system foundation:** Reworked the shared interface into a dark,
  premium editorial theme with reusable design tokens, accessible controls,
  responsive navigation, photography-first event cards, and distinct attendee,
  organizer, and platform-admin role accents.
- **Phase 11 – Booking Engine and Razorpay:** Added the public event catalogue,
  multi-ticket booking (maximum 10), server-priced snapshots, unique references,
  concurrency-safe reservations, free-ticket confirmation, Razorpay Orders,
  server-side payment verification, inventory release, and booking history.
  A signed, idempotent Razorpay webhook also confirms captured payments when
  the browser callback is interrupted.
- **Booking-engine stabilization:** Fixed native-PDO inventory release/finalize
  statements that could fail after a Razorpay order error, added safe
  payment-service feedback with local diagnostic logging, added ticket-count and
  total feedback plus duplicate-submit protection, and added transactional
  booking-service regression coverage. Paid local checkout still requires
  Razorpay Test Mode credentials through environment variables or
  `config/payment.local.php`.
- **Phase 13 – Organizer and Admin Booking Management:** Added ownership-scoped
  organizer booking lists/details, platform-wide admin booking and customer
  reporting, organizer/customer profile summaries, confirmed revenue and ticket
  metrics, server-side search/filters/pagination, responsive management views,
  and database-backed privacy and authorization regression coverage.

## Current phase

**Phase 14 - Production/Vercel Compatibility is implemented locally.** The
repository now supports local file sessions/posters and production database
sessions/Cloudinary through configuration. Vercel uses the pinned community
`vercel-php@0.9.0` runtime because Vercel does not provide an official native
PHP runtime. External MySQL, Cloudinary, and Razorpay Test Mode credentials are
still required before an actual Preview deployment can be verified.

Phase 13 added
read-only operational visibility without changing the booking or Razorpay
mutation paths. Organizers see customer name/email and bookings only for events
they own; admins see platform-wide bookings plus customer phone details. Revenue
uses confirmed free bookings and captured paid bookings. Phase 12 previously
completed the customer frontend: milestone 12.1 added the
attendee dashboard, customer-scoped upcoming/recent/payable booking summaries,
a read-only notification preview and unread count, and a consistent responsive
customer navigation shell across account and booking pages. Milestone 12.2 added
server-side event search, category/date/availability filters, safe sorting,
persistent query values, result counts, and filtered catalogue empty states.
Milestone 12.3 added paginated customer notification history, unread/read
presentation, CSRF-protected mark-one/mark-all controls, ownership-safe related
booking/event links, active dashboard navigation, responsive styling, and a
database-backed ownership regression test. Milestone 12.4 added customer-scoped
booking-reference/event/venue search, allowlisted status and booking-date
filters, safe sorting, persistent query values, total/filtered counts, distinct
history empty states, and database regression coverage for filters and customer
isolation. Milestone 12.5 added a customer-only profile page with validated name
and phone updates, read-only email, CSRF-protected Post/Redirect/Get handling,
active attendee navigation, and customer/role isolation coverage.

## Deployment

Follow [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the external database,
Cloudinary, Vercel, environment-variable, Razorpay webhook, Preview validation,
and rollback procedures. No production deployment is performed automatically.

## Next milestone

Create the external MySQL database, configure Vercel Preview environment
variables, deploy the repository, and execute the documented 25-step Preview
verification matrix before promoting anything to Production.

This progress section will be updated after every completed phase.
