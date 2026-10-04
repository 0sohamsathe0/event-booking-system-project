
# Event Booking System Project Context

## Project purpose

This is a single-hall event booking and management system. Visitors discover
approved events, customers reserve and buy tickets, organizers submit events
and ticket types, and a hall manager approves organizers and protects the hall
schedule. V1 uses Razorpay for online INR payments and does not include seat
selection.

## Technology stack

- Core PHP 8 with strict types and a small custom MVC-style structure
- MySQL/MariaDB through PDO with native prepared statements
- HTML5, shared server-rendered PHP layouts, CSS3, and vanilla JavaScript
- Apache/XAMPP; `public/` is the intended document root
- Razorpay Orders API, Standard Checkout, payment lookup, and signed webhooks
- No Composer, frontend framework, build process, or third-party PHP package

## Folder structure

- `app/Core/` - router, PDO connection, sessions, authentication, authorization,
  CSRF, and view rendering
- `app/Controllers/` - HTTP request handlers and role guards
- `app/Services/` - transactions and business rules
- `app/Repositories/` - SQL queries and persistence operations
- `app/Views/` - public, authentication, booking, organizer, and admin templates
- `app/Support/helpers.php` - escaping, URLs, CSRF fields, dates, and money
- `bootstrap/app.php` - autoloading and application bootstrap
- `config/` - application, database, and Razorpay configuration
- `database/event_booking_system.sql` - complete 13-table schema and categories
- `public/` - front controller, `.htaccess`, CSS, JavaScript, and event posters
- `routes/web.php` - all registered GET and POST routes
- `storage/` - ignored local sessions and logs
- `system architecture/` - synopsis, ERD, DFD, and database documentation

## User roles

### Visitor

- Can browse the home page, upcoming approved events, event details, ticket
  prices, availability, and hall information.
- Can register as a customer, apply as an organizer, or log in.
- Cannot book; the UI and backend both require the `customer` role.

### Customer

- Can reserve up to 10 tickets across an event's ticket types.
- Can confirm free bookings or pay through Razorpay.
- Can view booking history, booking details, and resume pending checkout.
- Can view customer-owned notification history, distinguish unread records, and
  mark one or all notifications as read.
- Can update their own name and phone from a customer-only profile page; email is
  read-only in the submission build.
- Cannot yet change a password, cancel a confirmed booking, or request/track a
  refund.

### Organizer

- Registers with `pending` status and may log in to track approval.
- A rejected organizer can correct name/phone and resubmit.
- An approved organizer can manage only their events, posters, and ticket types.
- Editing an approved/rejected event or its tickets returns it to `pending` for
  admin reapproval.
- Cannot yet request event cancellation or view bookings/revenue.

### Admin or Hall Manager

- Admin accounts are created manually with a PHP-generated password hash.
- Can view basic counts, configure the hall, approve/reject organizers, and
  approve/reject events.
- Event approval checks organizer state, ticket configuration, hall capacity,
  aggregate ticket capacity, and approved-event schedule overlap.
- Cannot yet disable organizers, manage categories, process cancellations or
  refunds, inspect payments, or view full reports.

## Database architecture

The schema defines 13 InnoDB tables:

1. `users` - all roles, passwords, account status, and organizer review data
2. `halls` - venue identity, contact details, and maximum capacity
3. `event_categories` - seeded, activatable event categories
4. `events` - organizer, hall, category, schedule, sale window, capacity/status
5. `event_status_history` - append-only event state audit records
6. `ticket_types` - price, capacity, reserved/sold counters, and active state
7. `bookings` - customer/event order, reference, totals, status, and expiry
8. `booking_items` - ticket quantities and unit-price snapshots
9. `payments` - Razorpay order/payment IDs and payment state
10. `refunds` - planned refund state and provider identifiers
11. `payment_webhook_events` - webhook idempotency and retained payloads
12. `event_cancellation_requests` - planned organizer/admin cancellation flow
13. `notifications` - per-user messages and optional event/booking links

Key relationships and constraints:

- One organizer, hall, and category can relate to many events.
- An event has many ticket types, bookings, and status-history rows.
- A customer has many bookings; a booking has items and payment attempts.
- Composite foreign keys ensure booking items reference tickets from the same
  event as their booking.
- Ticket inventory must satisfy `reserved_quantity + sold_quantity <= capacity`.
- Booking quantity is constrained to 1-10 and payment currency to INR.
- Provider order/payment IDs, user email, and booking reference are unique.
- Historical records use restrictive foreign keys instead of cascading deletes.
- Event overlap and aggregate capacities are enforced by application transactions.
- Database timestamps are UTC; application display time is Asia/Kolkata.

## Implemented features

- Custom routing, views, configuration, sessions, CSRF, and PDO infrastructure
- Customer and organizer registration, shared login, logout, password hashing
- Role, approval-state, and ownership authorization
- Organizer approval/rejection, reasons, notifications, and resubmission
- Single-hall settings and capacity protection
- Event creation/editing, poster upload, status history, and reapproval
- Admin event approval/rejection and transactional overlap prevention
- Ticket types, free/paid prices, activation, ordering, and inventory counters
- Public event catalogue and event details
- Concurrency-safe multi-ticket reservations and server-calculated totals
- Free booking confirmation
- Razorpay paid checkout and callback verification
- Searchable and filterable customer-owned booking history and booking details
- Signed, idempotent `payment.captured` webhook confirmation
- Paginated customer notification history, unread state, related booking/event
  links, and customer-scoped mark-one/mark-all-read controls
- Customer-only profile management with validated name/phone updates, read-only
  email, and CSRF-protected Post/Redirect/Get handling
- Dark responsive visual system with role accents and accessible focus states

## Current booking workflow

1. An approved organizer creates an event, initially `pending`.
2. The organizer adds at least one ticket type.
3. Admin approval locks relevant rows and validates organizer, hall, capacity,
   ticket capacity, and schedule overlap.
4. Approved future events with active tickets are published.
5. A customer submits quantities. The server locks the event/tickets, validates
   the sales window and availability, and calculates prices from the database.
6. It creates a `pending_payment` booking and item price snapshots, increments
   reserved inventory, and sets a configurable expiry (currently 15 minutes).
7. Free bookings immediately move reserved inventory to sold and confirm.
8. Paid bookings create a local payment row, commit database locks, then create
   a Razorpay Order.
9. Checkout returns order/payment/signature values. The server verifies the HMAC,
   fetches the payment from Razorpay, and checks order, amount, INR, and captured
   state before confirming transactionally.
10. A signed `payment.captured` webhook can confirm the booking when the browser
    callback is interrupted.
11. Manual checkout cancellation or order-creation failure releases inventory.
12. Confirmed-booking cancellation and refunds are not implemented.

### Booking-engine stabilization (October 2026)

- Fixed repeated named placeholders in native PDO inventory updates. The defect
  raised `SQLSTATE[HY093]` while releasing expired/failed holds or moving held
  inventory to sold, causing the generic booking-start message and leaked holds.
- Applied the same correction to browser payment confirmation, free-ticket
  confirmation, stale-reservation cleanup, and Razorpay webhook confirmation.
- Razorpay order failures now release the booking and payment transactionally,
  log a specific local diagnostic, and show customers a safe payment-service
  message instead of the generic booking-start message.
- Ticket selection now displays selected quantity/total, rejects zero-ticket
  client submissions, and disables the submit button after a valid submission
  to reduce accidental duplicate requests. Server-side validation remains
  authoritative.
- `tests/BookingServiceTest.php` covers zero/maximum validation, server totals,
  paid and free confirmation, provider failure cleanup, unavailable inventory,
  and competing attempts for the final ticket.

## Razorpay architecture

- `RazorpayService` calls `/orders` and `/payments/{id}` with cURL and Basic Auth.
- Secret credentials come from process environment variables or an optional
  `config/payment.local.php`; only the public key ID is sent to Checkout.
- Callback signature: HMAC-SHA256 of `order_id|payment_id` using the key secret.
- Callback confirmation also fetches the remote payment and verifies amount,
  currency, order ID, and `captured` status.
- `PaymentWebhookService` verifies the raw-body webhook HMAC, requires a provider
  event ID, records its payload/hash, rejects duplicates, and processes only
  `payment.captured`.
- Payments store local/provider IDs, amount, state, capture time, signature time,
  and failure description.
- Refund tables/design exist, but no refund API call, service, route, UI, retry,
  or refund webhook handling exists.

## Important business rules

- The application is currently single-hall even though the schema can hold more.
- Only approved organizers can manage events and tickets.
- Only pending organizers/events can be approved or rejected.
- Rejections require a reason.
- An event must be in the future; end must follow start; ticket sales must end no
  later than event start.
- Approved events in the same hall must not overlap.
- Event capacity cannot exceed hall capacity.
- Applicable ticket capacities cannot exceed event capacity.
- Ticket capacity cannot fall below reserved plus sold inventory.
- Events/ticket types with history are deactivated or status-changed, not deleted.
- Prices and totals are authoritative on the server; booking items snapshot price.
- A booking may contain 1-10 tickets total.
- Availability is `capacity - reserved_quantity - sold_quantity`.
- Successful confirmation moves reserved inventory to sold exactly once.
- External Razorpay requests occur outside long database transactions.

## Important routes and pages

Public and authentication:

- `GET /` - home and featured events
- `GET /events`, `GET /events/{id}` - catalogue and event detail
- `GET|POST /login`, `POST /logout`
- `GET|POST /register` - customer registration
- `GET|POST /organizer/register` - organizer application
- `GET /account` - shared account/status page

Customer booking:

- `POST /events/{eventId}/book`
- `GET /bookings`, `GET /bookings/{id}`
- `GET /bookings/{id}/checkout`
- `POST /bookings/{id}/confirm`
- `POST /bookings/{id}/payment-failed`
- `GET /notifications`
- `POST /notifications/{id}/read`, `POST /notifications/read-all`
- `GET|POST /profile` - customer profile display and name/phone update
- `POST /webhooks/razorpay` - signed external webhook, intentionally no CSRF

Organizer:

- `POST /organizer/resubmit`
- `GET /organizer/events`, `GET /organizer/events/create`
- `POST /organizer/events`, `GET|POST /organizer/events/{id}/edit`
- Ticket management under `/organizer/events/{eventId}/tickets`

Admin:

- `GET /admin/dashboard`
- `GET /admin/organizers` and organizer approve/reject POST routes
- `GET /admin/events`, `GET /admin/events/{id}` and approve/reject POST routes
- `GET|POST /admin/hall`

## Security architecture

- Passwords use `password_hash()`/`password_verify()`; no plaintext storage.
- Login regenerates the session ID; protected requests refresh the account state.
- Session cookies are HttpOnly and SameSite=Lax.
- All browser mutations use CSRF tokens; the Razorpay webhook uses HMAC instead.
- Controllers enforce roles, approved-organizer/admin status, and record ownership.
- SQL uses PDO prepared statements with emulation disabled.
- Views normally escape untrusted output through `e()`.
- File uploads allow only validated JPEG/PNG/WebP images up to 5 MB, use random
  filenames, and never trust the original extension.
- Booking/payment logic uses database transactions and ordered row locks.
- Production must disable debug, enable secure HTTPS cookies, add security
  headers, and configure secrets outside source control.

## Customer frontend delivery status

**Phase 12 - Customer Frontend** is complete:

- Milestone 12.1 delivered the attendee dashboard, customer-scoped booking
  summaries, notification preview/unread count, and customer dashboard shell.
- Milestone 12.2 delivered server-side event search, category/date/availability
  filters, allowlisted sorting, query persistence, and catalogue result states.
- Milestone 12.3 delivered paginated notification history, unread/read controls,
  ownership-safe related links, linked notification creation, and active
  dashboard navigation.
- Milestone 12.4 delivered customer-scoped booking search, status and booking-date
  filters, allowlisted sorting, result counts, improved empty states, and focused
  repository regression coverage.
- Milestone 12.5 delivered customer-only profile management for validated name
  and phone updates, read-only email, Post/Redirect/Get form handling, attendee
  navigation, and role-scoped regression coverage.

### Phase 12 milestone plan

1. **12.1 - Attendee Dashboard:** complete.
2. **12.2 - Event Discovery and Filters:** complete.
3. **12.3 - Customer Notifications:** complete.
4. **12.4 - Booking History Improvements:** complete.
5. **12.5 - Customer Profile Management:** complete (minimal submission scope;
   password changes deferred).

### Phase 12.5 completion - Customer Profile Management

- Added customer-only `GET /profile` and `POST /profile` routes protected by the
  existing role authorization and CSRF systems.
- Added server-side name and phone validation with normalized phone storage and
  safe handling for malformed non-string form values.
- Used Post/Redirect/Get for successful and invalid submissions, preserving
  validated old input and field-specific errors through flash data.
- Kept email visible but read-only and did not add an email mutation path.
- Scoped the repository update by both user ID and the `customer` role, then
  refreshed the authenticated session after a successful update.
- Replaced the disabled profile navigation items with active sidebar and
  dashboard links and added responsive attendee-styled profile presentation.
- `tests/CustomerProfileTest.php` covers validation, normalization, another-user
  isolation, customer updates, and organizer protection.
- All 78 PHP files pass syntax lint. The customer profile, booking history, and
  notification database tests pass and roll back their fixtures. Interactive
  multi-viewport browser QA remains a manual verification step.

### Phase 12.4 completion - Booking History Improvements

- Added customer-owned search across booking reference, event title, and venue.
- Added allowlisted status filtering and booking-date ranges interpreted in
  Asia/Kolkata before being compared with stored UTC timestamps.
- Added allowlisted newest, oldest, event-date, and amount sorting with stable
  tie-breakers.
- Preserved validated query values and displayed visible reset messages for
  invalid status, date, and sort inputs.
- Added total/filtered result counts plus separate unfiltered and filtered empty
  states in the attendee visual system.
- Kept every history query scoped by `customer_id` and parameterized all filter
  values.
- `tests/BookingRepositoryTest.php` covers customer isolation, reference/event/
  venue search, status and date filtering, total counts, and sorting.
- All 74 PHP files pass syntax lint. Both booking and notification database
  regression tests pass and roll back their fixtures. Interactive multi-viewport
  browser QA remains a manual verification step because browser control was not
  available in the completion environment.

### Phase 12.3 completion - Customer Notifications

- Added customer-only notification history at `GET /notifications`, limited to
  20 records per page and ordered newest first.
- Added CSRF-protected Post/Redirect/Get actions for marking one or all
  customer-owned notifications read.
- Notification updates are scoped by both notification ID and recipient ID;
  repeated mark-read submissions preserve the original UTC timestamp.
- The history resolves booking links only when the booking belongs to the
  recipient, and event links only when the event is currently public. Historical
  text-only notifications remain supported.
- New booking-confirmation notifications store `booking_id`, and event workflow
  notifications store `event_id` through backward-compatible repository APIs.
- The attendee sidebar, quick action, dashboard preview, unread badges, empty
  state, pagination, semantic timestamps, and responsive notification layout
  now form one consistent customer experience.
- `tests/NotificationRepositoryTest.php` covers customer isolation, idempotent
  read timestamps, mark-all ownership, history scoping, and text-only records.
- All PHP files pass syntax lint. The database regression test passed against
  local MariaDB and rolled back without changing the five existing notification
  records. Interactive browser screenshot testing was unavailable in the latest
  verification environment and remains a manual QA step.

## Known issues

- Stale reservations expire only when another reservation begins; no cron/CLI
  cleanup exists. The on-request cleanup now also marks associated open payments
  failed, but it is not a substitute for scheduled production cleanup.
- Checkout has no explicit Razorpay failure/dismiss handler or failed-payment
  webhook processing.
- Captured payments arriving after expiry/event reapproval require manual support;
  there is no reconciliation or automatic refund path.
- Organizers may edit live approved events/tickets, moving them to pending while
  existing bookings or checkouts exist.
- `disabled`, cancellation, refund, and several payment/webhook states exist in
  the schema without application workflows.
- Planning docs describe 10-minute reservations; code/config uses 15 minutes.
- Admin creation is manual; there is no installer or seed command.
- The schema is a fresh import, not a migration system.
- No broad automated test suite exists; focused database-backed coverage now
  includes booking service/inventory behavior plus Phase 12.3 through 12.5
  notification, booking-history, and profile behavior.
- The supplied directory has no `.git` metadata, so commits/branches are unknown.
- All 79 PHP files, JavaScript syntax, and the booking-service, booking-history,
  notification, and profile database tests currently pass. A live HTTP
  reproduction also confirms failed Razorpay order creation leaves no active
  hold. Real Razorpay Test Mode checkout and full multi-viewport QA remain
  outstanding until test credentials/browser control are available.

## TODO list

1. Establish Git history and expand automated database/service coverage.
2. Add a scheduled stale-reservation cleanup and payment-state reconciliation.
3. Define safe rules for editing events/tickets after reservations or sales.
4. Run interactive customer-page QA at 375, 430, 768, 1024, and 1440
   pixels when browser control is available.
5. Implement customer password changes after the submission-critical deployment
   path is healthy.
6. Implement customer cancellation with transactional inventory restoration.
7. Implement organizer event-cancellation requests and admin review.
8. Implement idempotent Razorpay refunds, retry handling, and refund webhooks.
9. Add organizer booking/revenue views and admin operational reports.
10. Add production configuration, HTTPS/security headers, rate limiting, and QA.

## Last completed development milestone

**Phase 12.5 - Customer Profile Management** is the latest completed milestone.
It added customer-only name and phone editing, server validation, normalized
phone storage, read-only email, CSRF-protected Post/Redirect/Get handling,
active attendee navigation, responsive presentation, and database-backed
customer/role isolation coverage. The later booking-engine stabilization fixed
inventory release/finalization and expanded transactional regression coverage;
it is a corrective maintenance change rather than a new numbered phase.
Password changes remain intentionally deferred.

## Recommended next task

Start the **Vercel compatibility foundation** from the two-day submission plan:
add the community PHP function entry point and routing, environment-driven
external MySQL configuration, database-backed sessions with a schema table,
production-safe debug and cookie behavior, root-domain URLs, and the seeded or
placeholder poster policy. Preserve local XAMPP behavior while making these
changes.
