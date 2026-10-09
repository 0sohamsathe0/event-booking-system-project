# Vercel Deployment Plan

This checklist deploys the Event Booking System to Vercel while preserving the
existing XAMPP installation as the local development and rollback environment.
Razorpay must remain in **Test Mode** throughout this deployment.

## Deployment architecture

```text
Browser
  -> Vercel HTTPS domain
  -> api/index.php
  -> existing PHP bootstrap and router
  -> external MySQL/MariaDB
  -> database-backed sessions
  -> Cloudinary poster storage
  -> Razorpay Test Mode
```

Vercel uses the pinned `vercel-php@0.9.0` community runtime from
`vercel.json`. Static files under `/assets/*` are served from
`public/assets`; all other requests enter the existing PHP router through
`api/index.php`.

## 1. Complete the pre-deployment checks

- [ ] Finish the manual customer partial/full cancellation tests.
- [ ] Finish the organizer event-cancellation and admin-approval tests.
- [ ] Confirm 100%, 50%, and blocked-within-48-hours policies.
- [ ] Confirm successful Razorpay Test refunds in the Razorpay dashboard.
- [ ] Confirm duplicate cancellation submission does not cancel more tickets.
- [ ] Confirm cancelled seats become available for later bookings.
- [ ] Confirm customer, organizer, and admin screens show the correct state.
- [ ] Back up the online MySQL/MariaDB database.
- [ ] Confirm `/database/phase_14_upgrade.sql`, Phase 15, and Phase 16 were
      imported successfully. Do not import them again.

Run the configured external-service preflight locally:

```powershell
C:\xampp\php\php.exe scripts\verify_external_services.php
```

Expected result:

```text
External database connectivity: passed
External database schema: 17/17 tables passed
Confirmed booking ticket quantities: passed
Database session persistence and logout: passed
Cloudinary signed upload and deletion: passed
Razorpay Test Mode configuration: passed
```

The preflight creates and deletes one temporary database session and one
temporary Cloudinary asset. It does not contact Razorpay.

## 2. Review Git safety

From the project directory, run:

```powershell
git status
git check-ignore -v .env
git ls-files .env config/database.local.php config/payment.local.php
```

Required result:

- `.env` is ignored.
- `config/database.local.php` is ignored.
- `config/payment.local.php` is ignored.
- The `git ls-files` command produces no output for those sensitive files.
- `.env.example`, `vercel.json`, `.vercelignore`, and `api/index.php` remain
  tracked.
- No database export containing real customer data or credentials is added to
  the deployment commit unless its inclusion is explicitly intended and safe.

Never commit real database, Razorpay, Cloudinary, cookie, session, webhook, or
API credentials.

## 3. Commit and push the deployment candidate

Review all changes before committing:

```powershell
git diff --stat
git diff
git status
```

Then commit and push through the normal repository workflow:

```powershell
git add <reviewed-files>
git commit -m "Prepare Event Booking System for Vercel deployment"
git push
```

Do not use `git add .` until every untracked file has been reviewed.

## 4. Create the Vercel project

1. Sign in to Vercel.
2. Choose **Add New -> Project**.
3. Import the Event Booking System Git repository.
4. Select **Other** as the framework preset.
5. Keep the project root as the repository root.
6. Do not set `public` as the root or output directory.
7. Do not add a build command.
8. Do not deploy until the environment variables below are entered.

The repository already supplies:

- `vercel.json`
- `api/index.php`
- `.vercelignore`
- the existing `public/index.php`

## 5. Add Vercel environment variables

Open **Project Settings -> Environment Variables**. Add the variables below
to the **Preview** environment first. Copy values from the corresponding
private provider dashboards or local `.env`; never copy `.env` into Git.

### Application

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-project-domain>.vercel.app
APP_BASE_PATH=
APP_TIMEZONE=Asia/Kolkata
LOG_CHANNEL=stderr
```

Use the expected Vercel project domain for the initial deployment. Once Vercel
assigns the real domain, verify it, correct `APP_URL` if necessary, and
redeploy. `APP_BASE_PATH` must be an empty value.

### External MySQL/MariaDB

```text
DB_HOST=<provider-host>
DB_PORT=3306
DB_DATABASE=<database-name>
DB_USERNAME=<database-user>
DB_PASSWORD=<database-password>
DB_CHARSET=utf8mb4
DB_SSL_MODE=required
DB_SSL_CA=
```

Follow the database provider's exact port and TLS instructions. If the provider
supplies a CA certificate, set `DB_SSL_CA` to the complete PEM value and use
`DB_SSL_MODE=verify_ca` or `verify_identity`. Do not disable provider-required
TLS. Ensure the provider accepts connections from Vercel serverless functions.

### Database sessions

```text
SESSION_DRIVER=database
SESSION_NAME=event_booking_session
SESSION_LIFETIME=7200
```

### Cloudinary

```text
CLOUDINARY_CLOUD_NAME=<cloud-name>
CLOUDINARY_API_KEY=<api-key>
CLOUDINARY_API_SECRET=<api-secret>
CLOUDINARY_FOLDER=event-booking-system/events
POSTER_STORAGE_DRIVER=cloudinary
```

Do not create an unsigned browser upload preset. Poster uploads are signed by
the PHP server.

### Razorpay Test Mode

```text
RAZORPAY_KEY_ID=rzp_test_<test-key-id>
RAZORPAY_KEY_SECRET=<test-key-secret>
RAZORPAY_WEBHOOK_SECRET=<separate-test-webhook-secret>
```

The key ID must start with `rzp_test_`. Do not use Live Mode credentials.

## 6. Deploy a Preview

1. Recheck that every required variable is available to **Preview**.
2. Trigger the first Preview deployment.
3. Inspect both the Build Logs and Runtime Logs.
4. Confirm the build uses `vercel-php@0.9.0`.
5. Confirm no credential or PHP stack trace appears in the logs.
6. Open:

   ```text
   https://<preview-domain>/health
   ```

7. Require HTTP `200` with this exact safe body:

   ```text
   healthy
   ```

If it returns `503 unavailable`, inspect database connectivity, TLS settings,
the selected database, the imported schema, and session configuration.

## 7. Perform the Preview smoke test

- [ ] Home page loads over HTTPS.
- [ ] CSS and JavaScript load without `/api`, `/public`, or `index.php` in URLs.
- [ ] Event discovery and event details load.
- [ ] Existing Cloudinary posters render.
- [ ] Customer registration and login work.
- [ ] Customer login persists across several requests.
- [ ] Organizer login persists across several requests.
- [ ] Admin login persists across several requests.
- [ ] Logout destroys the database-backed session.
- [ ] CSRF-protected forms submit successfully.
- [ ] Organizer can create an event with a poster.
- [ ] The new poster appears in Cloudinary and the event record.
- [ ] Organizer can replace the poster safely.
- [ ] Admin can approve the event.
- [ ] Organizer can create and manage ticket types.
- [ ] Customer can reserve one ticket.
- [ ] Customer can reserve multiple tickets with the correct total.
- [ ] Zero, over-limit, and over-inventory requests are rejected.
- [ ] Two competing reservations cannot oversell inventory.
- [ ] Razorpay Test Checkout opens.
- [ ] Test payment callback confirms the booking exactly once.
- [ ] Correct ticket and seat records are issued after payment.
- [ ] Organizer sees bookings only for owned events.
- [ ] Admin sees the correct customer, booking, payment, and refund state.
- [ ] Customer cancellation and Test Mode refund work.
- [ ] Event cancellation approval and refund batching work.
- [ ] Error responses contain no SQL, stack trace, filesystem path, or secret.

Inspect the browser Network panel during this test. Every application request
must remain on the HTTPS deployment domain.

## 8. Configure the Razorpay Test webhook

Configure this only after a stable public Vercel domain is available.

1. Open the Razorpay dashboard in **Test Mode**.
2. Open the Webhooks settings.
3. Add this endpoint:

   ```text
   https://<stable-vercel-domain>/webhooks/razorpay
   ```

4. Use the same secret stored in Vercel as `RAZORPAY_WEBHOOK_SECRET`.
5. Subscribe to:

   ```text
   payment.captured
   refund.created
   refund.processed
   refund.failed
   ```

6. Save the webhook.
7. Complete a new Razorpay Test payment.
8. Confirm the webhook receives HTTP `200`.
9. Replay a delivery and confirm no duplicate booking, ticket, inventory, or
   refund mutation occurs.
10. Process a Test Mode refund and confirm its status is reconciled.

Never log or expose the webhook signature or complete webhook request body.

## 9. Validate production-domain configuration

After the domain is finalized:

1. Set `APP_URL` to the exact HTTPS production domain, without a trailing slash.
2. Ensure production environment variables are separate from Preview values.
3. Add all required variables to the **Production** environment.
4. Redeploy after changing environment variables.
5. Update the Razorpay Test webhook if the stable domain changed.
6. Recheck `/health` and the complete payment/refund flow.

## 10. Promote only after approval

Promote the tested deployment only when:

- [ ] The complete Preview checklist passes.
- [ ] Manual cancellation/refund testing passes.
- [ ] Vercel runtime logs show no unresolved failures.
- [ ] Database and Cloudinary activity is correct.
- [ ] Razorpay Test callback and webhook both work.
- [ ] No secrets are present in Git or public responses.
- [ ] A current database backup exists.

Do not switch Razorpay to Live Mode as part of this deployment.

## 11. Post-deployment monitoring

Immediately after promotion:

1. Check `/health`.
2. Inspect Vercel Runtime Logs for sanitized error event names.
3. Check the database provider's connections and latency.
4. Check Cloudinary for only expected poster assets.
5. Check Razorpay Test webhook deliveries.
6. Perform one controlled end-to-end Test Mode booking.
7. Perform one controlled cancellation/refund test.
8. Confirm database-session logout.

## 12. Rollback

If Preview or production fails:

1. Stop further promotion.
2. Use Vercel's deployment history to restore the last known-good deployment.
3. Preserve the external database; do not drop Phase 14, 15, or 16 tables.
4. Do not delete Cloudinary metadata or issued-ticket/refund history.
5. Restore the database backup only if a confirmed data-corruption incident
   requires it.
6. Keep XAMPP available with:

   ```text
   APP_ENV=local
   SESSION_DRIVER=file
   POSTER_STORAGE_DRIVER=local
   LOG_CHANNEL=file
   ```

7. Rotate and revoke credentials if exposure is suspected.

## Deployment stopping conditions

Do not continue deployment when any of these occur:

- `/health` returns `unavailable`.
- PHP runtime/build installation fails.
- Database TLS or connectivity fails.
- Login does not persist between requests.
- Cloudinary upload or replacement is inconsistent.
- Booking inventory can oversell.
- Payment or refund processing is not idempotent.
- The deployed application exposes errors, credentials, signatures, or paths.
- Any Preview checklist item affecting security, payment, inventory, or data
  integrity fails.
