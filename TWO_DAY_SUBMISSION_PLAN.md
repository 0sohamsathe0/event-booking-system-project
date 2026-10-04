# Event Booking System — Two-Day Submission Plan

## Objective

Deliver a stable, submission-ready demonstration of the Event Booking System in
two focused working days.

The final submission should demonstrate:

- Customer and organizer registration and login
- Admin approval of organizers and events
- Event and ticket-type management
- Public event discovery
- Customer booking and booking history
- Customer notifications
- Razorpay payments in Test Mode only
- A working public deployment on Vercel
- Clear setup, demo, and limitation documentation

This is a submission build, not a production launch. Cancellation, automated
refunds, advanced financial reporting, production monitoring, and broad
production hardening are explicitly outside the two-day scope.

## Non-negotiable submission path

The critical path is:

1. Application deploys successfully.
2. Authentication and sessions work on the deployed URL.
3. The deployed application connects to an external MySQL database.
4. A customer can complete a Razorpay Test Mode payment.
5. Core customer, organizer, and admin workflows can be demonstrated.
6. Documentation accurately explains setup and known limitations.

If time becomes constrained, deployment and the payment demonstration take
priority over additional profile features or visual polish.

## Important Vercel constraints

Vercel does not provide an official PHP runtime. The deployment will use the
community `vercel-php` runtime.

Vercel Functions have a read-only deployed filesystem and only temporary
scratch storage. Therefore:

- The production demo cannot rely on `storage/sessions` for persistent sessions.
- PHP sessions must be stored in MySQL for the deployed version.
- Organizer-uploaded posters cannot be stored permanently on the Vercel
  filesystem.
- The submission build will use seeded/static posters and a restrained
  placeholder. Hosted poster replacement may be disabled with a clear message.
- MySQL must be hosted by an external provider and accessed through environment
  variables.

## Required access and credentials

Prepare these before the deployment block begins:

- A Git repository containing the project
- A Vercel account connected to that repository
- An externally hosted MySQL/MariaDB database
- Database host, port, name, username, password, and SSL requirements
- Razorpay Test Mode Key ID
- Razorpay Test Mode Key Secret
- A Razorpay webhook secret
- Access to the Razorpay Test Mode dashboard

Never paste real secrets into source files, Markdown documents, screenshots, or
chat messages. Store deployment secrets only as Vercel environment variables.

## Priority levels

### P0 — Must be completed

- Vercel PHP entry point and routing
- External MySQL configuration through environment variables
- Database-backed sessions
- Deployed login and role authorization
- Existing event, ticket, booking, and notification workflows
- Razorpay Test Mode order, Checkout, callback, and payment verification
- A successful end-to-end test booking
- Final syntax, database, and HTTP smoke tests
- Submission documentation

### P1 — Complete if the P0 path is healthy

- Phase 12.4 booking-history search and filters
- Minimal Phase 12.5 customer profile editing
- Password change if it does not threaten the deployment schedule
- Responsive and accessibility corrections discovered during QA

### P2 — Defer immediately if the schedule slips

- Additional dashboard polish
- Complex profile/email verification
- New organizer reports
- New admin reports
- New image-storage integration
- Cancellation and refund workflows
- Large refactors unrelated to deployment

## Day 1 — Finish the submission feature set

Target: the application is feature-complete locally and structurally ready for
Vercel by the end of Day 1.

### Block 1 — Preflight and freeze scope (45 minutes)

- [ ] Confirm the current database imports successfully.
- [x] Run all PHP syntax checks.
- [x] Run the Phase 12.3 notification regression test.
- [ ] Confirm the three roles and available test accounts.
- [ ] Confirm Razorpay Test Mode access.
- [x] Add `config/payment.local.php` to `.gitignore`.
- [ ] Record the external MySQL connection requirements.
- [x] Freeze the scope to the P0 and P1 lists above.

Exit condition: the current baseline is known and no unrelated work is allowed.

### Block 2 — Phase 12.4 booking history (3 hours)

- [x] Add customer-scoped text search.
- [x] Add allowlisted booking-status filtering.
- [x] Add validated date filtering.
- [x] Add allowlisted sorting if time permits.
- [x] Preserve filter values in the query string and form.
- [x] Display total/filtered result counts.
- [x] Add clear unfiltered and filtered empty states.
- [x] Keep all SQL prepared and customer-scoped.
- [x] Verify mobile recomposition and keyboard focus styles statically.
- [x] Add focused database tests for customer isolation and filters.

Exit condition: a customer can efficiently find their own bookings without
changing booking or payment behavior.

### Block 3 — Minimal Phase 12.5 profile management (2.5 hours)

- [x] Add a customer-only profile page.
- [x] Allow name and phone updates.
- [x] Validate values server-side.
- [x] Use CSRF-protected Post/Redirect/Get updates.
- [x] Keep email read-only for the submission build.
- [ ] Add current-password plus new-password change only if the block remains on
      schedule.
- [x] Update customer navigation and active states.
- [x] Add ownership/authorization tests.

Cut rule: if this block exceeds 2.5 hours, defer password changes and ship only
safe name/phone editing.

### Block 4 — Vercel compatibility foundation (2.5 hours)

- [ ] Add the Vercel PHP function entry point.
- [ ] Add `vercel.json` runtime, rewrite, and static-asset configuration.
- [ ] Make database configuration read Vercel environment variables.
- [ ] Add a MySQL-backed PHP session handler.
- [ ] Add the session table to the fresh schema.
- [ ] Preserve local filesystem sessions for XAMPP development.
- [ ] Detect the Vercel environment explicitly rather than guessing from the
      request URL.
- [ ] Ensure application URLs work at the Vercel domain root.
- [ ] Ensure debug output is disabled in the deployed environment.
- [ ] Make secure session cookies environment-aware.
- [ ] Apply the static/seeded poster policy for Vercel.

Exit condition: local behavior remains intact and the repository has a credible
Vercel build configuration.

### End-of-Day-1 checkpoint (30 minutes)

- [x] Run PHP lint across the entire project (79 files passed after booking fix).
- [x] Run database regression tests (booking service, booking history,
      notifications, and customer profile passed locally).
- [ ] Review all changed routes and form actions.
- [ ] Update `PROJECT_CONTEXT.md` with completed work and remaining risks.
- [ ] Write a compact handoff containing only changed files, test commands,
      current blocker, and the exact next action.

Day 1 expected effort: approximately 9–10 focused hours.

## Day 2 — Deploy, integrate Razorpay, and verify

Target: a reviewer can open the Vercel URL and complete the main demonstration
without local development tools.

### Block 1 — External database preparation (1.5 hours)

- [ ] Create the external MySQL database.
- [ ] Import the latest schema.
- [ ] Configure the hall.
- [ ] Create one admin account with `password_hash()`.
- [ ] Seed or create one approved organizer, event, and ticket configuration.
- [ ] Confirm database timezone behavior remains UTC.
- [ ] Verify TLS/SSL requirements from PHP.
- [ ] Test connectivity using the same environment values intended for Vercel.

Exit condition: the external database contains a clean, demonstrable dataset.

### Block 2 — First Vercel deployment (2 hours)

- [ ] Connect the repository to Vercel.
- [ ] Add database environment variables.
- [ ] Add application environment variables.
- [ ] Deploy the community PHP runtime configuration.
- [ ] Confirm static CSS, JavaScript, and seeded posters load.
- [ ] Confirm registration, login, logout, and database sessions persist across
      multiple requests.
- [ ] Confirm customer, organizer, and admin route protection.
- [ ] Fix deployment blockers only; postpone cosmetic work.

Escalation rule: if PHP runtime deployment remains broken after 90 minutes,
stop feature work and dedicate the remaining time to the Vercel entry point,
rewrites, bundled files, and logs.

### Block 3 — Razorpay Test Mode deployment (2 hours)

- [ ] Add the Test Mode Key ID and Key Secret as Vercel environment variables.
- [ ] Add the webhook secret as a Vercel environment variable.
- [ ] Ensure no Test Mode secret is committed to Git.
- [ ] Configure the Razorpay webhook URL as:
      `https://<vercel-domain>/webhooks/razorpay`
- [ ] Subscribe to `payment.captured`.
- [ ] Confirm Razorpay automatic capture/test behavior.
- [ ] Create a paid booking on the deployed site.
- [ ] Complete a simulated successful payment.
- [ ] Confirm callback signature verification.
- [ ] Confirm remote payment amount, currency, order, and captured-state checks.
- [ ] Confirm the booking becomes `confirmed` exactly once.
- [ ] Confirm reserved inventory moves to sold inventory exactly once.
- [ ] Confirm a linked booking notification is created.
- [ ] Repeat or replay the webhook to verify idempotency.
- [ ] Test the visible payment-cancel path.

Exit condition: one recorded end-to-end Test Mode payment succeeds on the public
Vercel URL and produces the correct booking, payment, inventory, and notification
state.

### Block 4 — Submission QA (2 hours)

Run the following reviewer journey:

1. Register or log in as an organizer.
2. Show organizer approval status.
3. Log in as admin and approve the organizer.
4. Create an event and ticket type as the organizer.
5. Approve the event as admin.
6. Browse/filter events as a visitor.
7. Register or log in as a customer.
8. Book tickets and complete a Razorpay Test Mode payment.
9. Open booking history and booking details.
10. Open notifications and mark records read.
11. Update the customer profile if Phase 12.5 shipped.

QA checklist:

- [ ] No route exposes another user's records.
- [ ] All browser mutations use CSRF protection.
- [ ] No secrets appear in HTML, logs, or the repository.
- [ ] Error messages are understandable.
- [ ] Navigation works on narrow and wide layouts.
- [ ] Forms have visible labels and focus states.
- [ ] Empty states are useful.
- [ ] Uploaded-poster limitations are communicated on Vercel.
- [x] PHP lint passes locally (79 files).
- [x] Focused database regression tests pass locally.
- [ ] The deployed URL passes HTTP smoke checks.

### Block 5 — Documentation and submission package (1.5 hours)

- [ ] Update `README.md` with the final implemented scope.
- [ ] Update `PROJECT_CONTEXT.md` with the actual last completed milestone.
- [ ] Document Vercel deployment and environment-variable names.
- [ ] Document external database import/setup.
- [ ] Document Razorpay Test Mode setup and test-payment steps.
- [ ] Add demo credentials only if the submission rules permit them.
- [ ] Clearly label deferred production features.
- [ ] Prepare a short demonstration script.
- [ ] Save the final Vercel URL.
- [ ] Confirm the repository contains no secrets or local session data.

Day 2 expected effort: approximately 9 focused hours.

## Token- and context-efficient execution strategy

The exact conversation-token allowance can vary, so development should not rely
on one very long chat turn. Use the repository as persistent memory.

### Working rules

- Work on one block at a time.
- Read only the files relevant to the active block.
- Avoid repeating full repository audits.
- Reuse existing repositories, services, controllers, layouts, and CSS tokens.
- Do not rewrite working booking, payment, authentication, or authorization code.
- Prefer small patches followed by focused checks.
- Run full PHP lint at block checkpoints rather than after every tiny edit.
- Store durable decisions and status in `PROJECT_CONTEXT.md`.
- Keep a short handoff after each block so a new conversation can continue
  without reloading the full history.

### Recommended handoff format

At the end of every block, record:

```text
Completed:
- ...

Changed files:
- ...

Verification:
- ...

Remaining blocker:
- ...

Next exact action:
- ...
```

### Suggested conversation sequence

Use separate focused requests in this order:

1. “Implement Phase 12.4 exactly as defined in TWO_DAY_SUBMISSION_PLAN.md.”
2. “Implement the minimal Phase 12.5 profile scope from the plan.”
3. “Make the existing Core PHP app Vercel-compatible according to the plan.”
4. “Run the deployment-readiness audit and give me the exact Vercel variables.”
5. “Help me deploy and verify Razorpay Test Mode on the Vercel URL.”
6. “Run final submission QA and update README.md and PROJECT_CONTEXT.md.”

This sequence limits repeated context, gives every turn a measurable completion
condition, and preserves recovery points if the conversation is compacted.

## Features intentionally deferred

- Confirmed-booking cancellation
- Organizer event-cancellation requests
- Razorpay refunds and refund webhooks
- Payment reconciliation beyond the existing callback/webhook behavior
- Organizer revenue analytics
- Advanced admin reporting
- Category-management UI
- Organizer disabling workflow
- Full migration framework
- Broad automated test suite
- Production monitoring, rate limiting, backups, and disaster recovery

These should be presented as planned enhancements, not as broken submission
features.

## Final definition of done

The submission is complete when all of the following are true:

- [ ] The public Vercel URL loads reliably.
- [ ] External MySQL connectivity works.
- [ ] Database-backed login sessions persist.
- [ ] Admin, organizer, and customer authorization works.
- [ ] An approved event can be discovered and booked.
- [ ] A Razorpay Test Mode payment confirms a booking.
- [ ] Callback and webhook verification do not double-sell inventory.
- [ ] Booking history and notification history work for the owning customer.
- [ ] No secrets are committed.
- [ ] All PHP files pass syntax lint.
- [ ] Focused database tests pass.
- [ ] README and project context match the deployed implementation.
- [ ] Deferred features are documented honestly.

Local booking-engine verification completed on 4 October 2026: native-PDO
inventory release/finalization was corrected, payment-provider failure cleanup
was verified through the HTTP flow, and focused booking service coverage passed.
The final deployed Razorpay Test Mode payment remains unchecked because no local
Test Mode Key ID/Secret is configured in this workspace.

## Time budget summary

| Work area | Time budget |
|---|---:|
| Preflight and scope freeze | 0.75 hour |
| Phase 12.4 | 3 hours |
| Minimal Phase 12.5 | 2.5 hours |
| Vercel compatibility foundation | 2.5 hours |
| Day 1 checkpoint | 0.5 hour |
| External database | 1.5 hours |
| First Vercel deployment | 2 hours |
| Razorpay Test Mode | 2 hours |
| Submission QA | 2 hours |
| Documentation/package | 1.5 hours |
| **Total** | **18.25 hours** |

This is an aggressive plan. Protecting the P0 critical path and applying the cut
rules is more important than completing every P1 item.
