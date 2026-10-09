# Production and Vercel Deployment

Phase 14 keeps the local XAMPP application intact and adds a separate
production configuration path. It does not deploy automatically and it keeps
Razorpay in Test Mode.

## Architecture

Local:

```text
XAMPP/PHP 8.2 -> local MySQL -> file sessions -> public/uploads -> file logs
```

Production:

```text
Vercel -> vercel-php@0.9.0 (PHP 8.5) -> external MySQL/MariaDB
       -> database sessions -> Cloudinary posters -> stderr logs
       -> Razorpay Test Mode callback and signed webhook
```

Vercel currently lists PHP as a recommended community runtime rather than an
official runtime. The repository therefore pins `vercel-php@0.9.0` instead of
using an unversioned runtime. `api/index.php` is only an adapter into the
existing `public/index.php`, bootstrap, routes, controllers, and services.

Required PHP extensions are PDO, pdo_mysql, cURL, fileinfo, OpenSSL, JSON,
mbstring, and session. No Composer package is required.

## Secrets and environments

Never commit `.env`, local PHP overrides, credentials, cookies, signatures, or
session data. `.env.example` contains placeholders only. Configure Preview and
Production separately in Vercel Project Settings -> Environment Variables.

Required production variables:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-preview-or-production-domain
APP_BASE_PATH=
APP_TIMEZONE=Asia/Kolkata

DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
DB_CHARSET=utf8mb4
DB_SSL_MODE=required
DB_SSL_CA=

SESSION_DRIVER=database
SESSION_NAME=event_booking_session
SESSION_LIFETIME=7200

RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...

CLOUDINARY_CLOUD_NAME=...
CLOUDINARY_API_KEY=...
CLOUDINARY_API_SECRET=...
CLOUDINARY_FOLDER=event-booking-system/events
POSTER_STORAGE_DRIVER=cloudinary

LOG_CHANNEL=stderr
```

`DB_SSL_MODE` accepts `disabled`, `preferred`, `required`, `verify_ca`, or
`verify_identity`. Production providers should use at least `required`.
`DB_SSL_CA` may contain the provider's complete PEM certificate or a readable
certificate path. Raw PEM values are materialized into temporary runtime
storage and are never logged. Prefer `verify_ca` or `verify_identity` when the
provider supplies a CA certificate. Verified modes avoid a separate runtime
TLS-status query because a failed certificate-verified handshake already
closes the connection. Do not disable provider-required TLS.

The ignored local `.env` file is loaded for web requests. Existing process or
Vercel environment variables always take precedence. CLI tools skip `.env` by
default so database tests cannot accidentally mutate a cloud database; the
external-service verifier explicitly opts in.

For local browser testing against the external database, start the application
with `scripts/start_local_server.ps1`. It overrides only the local runtime mode,
URL, logging, and session driver, allowing file sessions to avoid two remote
session round trips per request. This optimization is local-only; Preview and
Production must retain database sessions.

Production validation rejects incomplete database, Razorpay, session,
Cloudinary, URL, or driver configuration. It also rejects a Razorpay key ID
that is not a Test Mode `rzp_test_` key.

## Database preparation

For a new database, import `database/event_booking_system.sql`, configure the
hall, and create the first admin with a `password_hash()` value.

For an existing Phase 13 database, select the correct database and execute:

```text
database/phase_14_upgrade.sql
```

This creates `sessions` and adds only these nullable event columns:

```text
events.poster_provider VARCHAR(20) NULL
events.poster_public_id VARCHAR(255) NULL
```

The local convenience command performs the same upgrade using the configured
PDO connection:

```powershell
C:\xampp\php\php.exe scripts\apply_phase_14_upgrade.php
```

Existing Phase 14 databases must also receive the additive ticket issuance
table before deploying this application version:

```text
database/phase_15_ticket_issuance.sql
```

or through the configured PDO connection:

```powershell
C:\xampp\php\php.exe scripts\apply_phase_15_ticket_issuance.php
```

After the table exists, issue tickets for confirmed bookings created before the
upgrade:

```powershell
C:\xampp\php\php.exe scripts\backfill_issued_tickets.php
```

The backfill is idempotent and processes bookings by event and confirmation
time. Run it once against each upgraded database.

The migration does not delete or rewrite users, events, tickets, bookings,
payments, inventory, webhook history, or existing poster paths.

Choose an external MySQL/MariaDB region near the Vercel function region. The
current `vercel.json` uses Mumbai (`bom1`). Change both sides together if the
database is provisioned elsewhere. PDO persistent connections remain disabled.

## Cloudinary setup

1. Create or select a Cloudinary product environment.
2. Copy its cloud name, API key, and API secret into the corresponding Vercel
   environment variables. The API secret must never be sent to the browser.
3. Set `CLOUDINARY_FOLDER` to a controlled folder such as
   `event-booking-system/events`.
4. Set `POSTER_STORAGE_DRIVER=cloudinary`.
5. Do not create an unsigned browser upload preset; uploads are signed on the
   PHP server.
6. After deployment, create an event with a JPEG, PNG, or WebP poster no larger
   than 5 MB, verify its HTTPS URL, then replace it and confirm the former asset
   is removed.

The database stores the secure delivery URL and managed Cloudinary public ID.
Deletion is attempted only for records explicitly marked `cloudinary` with a
validated public ID. If a database write fails after upload, the new asset is
removed as compensation. Existing local paths and poster placeholders continue
to work.

Rotate Cloudinary credentials in its console, update Preview/Production
variables, and redeploy. Old deployments keep their previous environment
values, so remove them when rotation is complete.

Before Vercel deployment, the configured external database and Cloudinary
account can be checked without exposing credentials:

```powershell
C:\xampp\php\php.exe scripts\verify_external_services.php
```

This performs read-only schema checks, writes and destroys one temporary
database session, and uploads then deletes one temporary Cloudinary asset. It
does not create a Razorpay order.

## Vercel deployment

1. Push Phase 14 to the Git repository after reviewing the diff and test report.
2. Import the repository into Vercel and select **Other** as the framework.
3. Keep the repository root as the project root; do not set `public` as an
   output directory.
4. Add all environment variables above to **Preview** first.
5. Import/upgrade the external database before opening the application.
6. Deploy a Preview. Vercel uses `api/index.php` and the pinned runtime from
   `vercel.json`; `/assets/*` is served from `public/assets`.
7. Open `/health`. It must return only `healthy`. A `503 unavailable` result
   means bootstrap, sessions, or database connectivity failed.
8. Inspect Vercel Runtime Logs for sanitized event names. Do not paste logs that
   contain externally added secrets into tickets or documentation.
9. Complete the Preview checklist below.
10. Add equivalent but separate Production values only after Preview passes,
    then promote the verified deployment.

The repository has not been deployed automatically. Runtime build behavior,
external-network access, provider TLS, and cold starts require a real Preview.

## Razorpay Test Mode

1. In the Razorpay dashboard, remain in **Test Mode**.
2. Add the Test Key ID and Test Key Secret to Vercel.
3. Generate a distinct webhook secret and add it as
   `RAZORPAY_WEBHOOK_SECRET`.
4. Create a webhook with this HTTPS URL:

   ```text
   https://your-domain.example/webhooks/razorpay
   ```

5. Subscribe to `payment.captured`.
6. Complete a Test Mode booking and confirm the callback verifies the HMAC,
   remote order, amount, INR currency, and captured state.
7. Inspect the booking, payment, inventory, and notification records.
8. Replay the same webhook event and confirm the booking is not confirmed or
   sold twice.
9. Confirm one `issued_tickets` row exists per booked quantity, with unique
   ticket codes and unique event-wide seat numbers.

Rotate Razorpay credentials in Test Mode, update Vercel variables, redeploy,
test a new order, and then retire the previous keys. Live Mode is outside Phase
14 and must not be enabled as part of this deployment.

## Preview verification checklist

- [ ] 1. Home page loads.
- [ ] 2. CSS and JavaScript load.
- [ ] 3. Event discovery works.
- [ ] 4. Registration and login work.
- [ ] 5. Customer session persists across requests.
- [ ] 6. Organizer session persists across requests.
- [ ] 7. Admin session persists across requests.
- [ ] 8. Organizer creates an event.
- [ ] 9. Cloudinary poster uploads.
- [ ] 10. Organizer replaces a poster and the old managed asset is removed.
- [ ] 11. Customer reserves one ticket.
- [ ] 12. Customer reserves multiple tickets with the correct total.
- [ ] 13. Excess inventory is rejected.
- [ ] 14. Expired reservations release inventory correctly.
- [ ] 15. Competing reservations cannot oversell.
- [ ] 16. Razorpay Test Checkout opens.
- [ ] 17. Razorpay callback succeeds.
- [ ] 18. Razorpay signed webhook succeeds.
- [ ] 19. Booking and inventory confirm exactly once.
- [ ] 19a. Confirmed quantity produces the same number of unique tickets/seats.
- [ ] 20. Organizer sees only bookings for owned events.
- [ ] 21. Admin sees the correct booking and payment state.
- [ ] 22. Logout invalidates the database session.
- [ ] 23. Error pages and logs expose no secrets or stack traces.
- [ ] 24. Preview and local credentials remain separate.
- [ ] 25. `/health` returns only `healthy` with status 200.

Do not promote to Production until all 25 checks pass.

## Troubleshooting

- **Build fails before PHP runs:** confirm `vercel.json` still references an
  available pinned `vercel-php` release and inspect the build log. Community
  runtime availability is an external dependency.
- **All routes contain `/api`:** confirm `APP_ENV=production` and
  `APP_BASE_PATH` is empty, then redeploy.
- **Assets return 404:** confirm the `/assets/(.*)` route precedes the catch-all
  route and `public/assets` exists in the deployment.
- **Health is unavailable:** verify database variables, network allowlists, TLS
  mode/CA, migration, and the Vercel function/database regions.
- **Login does not persist:** verify `SESSION_DRIVER=database`, the `sessions`
  table, secure HTTPS cookies, and the configured domain.
- **Poster upload fails:** verify cURL/fileinfo support, Cloudinary variables,
  folder syntax, file format, and the 5 MB limit.
- **Webhook is rejected:** verify the exact raw-body webhook secret, public URL,
  `payment.captured`, `refund.created`, `refund.processed`, and `refund.failed`
  subscriptions, and Razorpay Test Mode.

## Phase 16 cancellation/refund deployment

Before deploying Phase 16, back up the external database and import
`database/phase_16_cancellations_refunds.sql` after the Phase 15 upgrade. The
migration preserves historical tickets and replaces permanent seat uniqueness
with active-seat uniqueness so cancelled seats can be assigned again safely.

Keep the Razorpay dashboard in **Test Mode** and add these events to the existing
signed webhook endpoint:

```text
payment.captured
refund.created
refund.processed
refund.failed
```

The application refuses refund calls unless `RAZORPAY_KEY_ID` starts with
`rzp_test_`. After deployment, test one 100% partial refund, one 50% partial
refund, a failed/retried refund, and an approved event cancellation. Confirm
that duplicate webhook delivery changes neither refund totals nor inventory.

## Rollback

The Phase 14 schema is additive and can remain in place during rollback.

Local/XAMPP rollback values are:

```text
APP_ENV=local
SESSION_DRIVER=file
POSTER_STORAGE_DRIVER=local
LOG_CHANNEL=file
```

Restore the Phase 13 application commit if necessary. Do not drop the session
table or poster metadata during an urgent rollback; unused additive structures
are harmless. Preserve Cloudinary public IDs in the database so managed assets
can still be audited. Revoke compromised external credentials independently.
