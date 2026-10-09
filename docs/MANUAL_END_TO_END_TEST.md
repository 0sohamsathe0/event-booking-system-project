# Short End-to-End Test

Use unique test emails and Razorpay **Test Mode** only. Keep the admin,
organizer, and customer signed in through separate browser profiles/incognito
windows.

> Admin accounts cannot be registered publicly. Use an existing approved admin
> account, or create the first admin securely with a PHP `password_hash()` value.

## Test checklist

- [ ] Open `/health`; confirm `healthy`.
- [ ] Register an organizer at `/organizer/register`; confirm the account is pending.
- [ ] Register a customer at `/register`; confirm customer login works.
- [ ] Sign in as admin and approve the organizer under **Organizers**.
- [ ] Sign in as organizer and create a future event with a JPEG/PNG/WebP poster.
- [ ] Confirm the poster displays from Cloudinary and the event is pending.
- [ ] Optional guard test: try approving before adding tickets; approval must be rejected.
- [ ] As organizer, add an active `Regular` ticket (for example INR 300, capacity 100).
- [ ] Add any second ticket type needed; combined capacity must not exceed event capacity.
- [ ] As admin, approve the event; confirm it appears in public event discovery.
- [ ] As customer, select 1 Regular ticket and choose **Reserve & continue**.
- [ ] Confirm the displayed quantity and total are correct, then complete Razorpay's test checkout.
- [ ] Confirm the booking becomes `confirmed` and payment becomes `captured`/successful.
- [ ] Confirm one issued ticket appears per booked quantity with a unique code and seat such as `S-001`.
- [ ] Confirm the customer sees the booking in booking history.
- [ ] Confirm the organizer sees it only under their event, including customer and ticket details.
- [ ] Confirm the admin sees the booking, customer, organizer, event, and payment status.
- [ ] Confirm sold inventory increased once and available inventory decreased once.
- [ ] Refresh the success page; confirm no duplicate booking/payment is created.
- [ ] Book multiple paid tickets for an event more than seven days away; cancel a quantity from one ticket type and confirm a 100% Test Mode partial refund.
- [ ] Confirm the highest-numbered valid seats in that type are cancelled, remaining tickets stay valid, sold inventory decreases, and a released seat can be booked again.
- [ ] Repeat with an event between seven days and 48 hours away; confirm the estimate and recorded refund are 50%.
- [ ] Confirm customer cancellation is rejected inside 48 hours and used/cancelled tickets cannot be cancelled again.
- [ ] Cancel a free ticket; confirm inventory is restored without a refund record.
- [ ] As organizer, submit and withdraw a pending event-cancellation request.
- [ ] Submit again; as admin reject it once, then submit another request and approve it.
- [ ] Confirm approval closes the event, releases pending reservations, cancels remaining valid tickets, and queues 100% Test Mode refunds.
- [ ] In admin Refunds, process queued batches and retry a simulated/observed failed refund; confirm the same idempotency key prevents duplication.
- [ ] Deliver `refund.created`, `refund.processed`, and `refund.failed` test webhooks twice; confirm duplicate events do not duplicate refunds or inventory changes.
- [ ] Log out all three roles; confirm protected pages require login again.

## Record the result

- Event title/reference:
- Booking reference:
- Razorpay test payment ID (do not record signatures or secrets):
- Expected total / actual total:
- Final booking status:
- Final payment status:
- Issued ticket codes / seat numbers:
- Failed step and visible message, if any:
