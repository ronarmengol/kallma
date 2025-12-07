**Kallma Front Desk — One-Page Flow & Checklist**

Purpose: quick reference for reception staff to take and manage bookings, check-in clients, handle payments, and escalate issues.

FLOW (quick linear view)

1. New Inquiry/Call/Walk-in

   - Ask: "Name, Phone, Service, Preferred Date/Time"
   - Check availability in Admin: `/kallma/admin/bookings.php` or use `booking.php` flow
     -> If time available: Proceed to Booking Entry
     -> If not: Offer alternatives or add to waitlist (note manual)

2. Booking Entry

   - Open `booking.php?service_id=...` or add booking in `admin/bookings.php`
   - Capture: Name, Phone, Email (optional), Service, Date, Time, Notes
   - Ask: "Will you be paying now or at the venue?"
     -> If paying now: Record payment (cash/card) and mark Paid
     -> If paying later: Mark as Pending Payment
   - Always provide a confirmation number and summary to client

3. Confirmation & Reminders

   - On booking completion, read confirmation back and note cancellation policy
   - Send manual confirmation via SMS/email template (scripts below)
   - Reminder: Admins send reminders 24 hours before (manual or automated later)

4. Check-in (Day of Appointment)

   - Search client by name or phone in `admin/bookings.php`
   - Confirm booking time and service
   - If late/cancelled: follow cancellation/reschedule policy
   - If not paid: collect payment and update booking to Paid
   - After service: mark booking as Completed and note notes/feedback

5. No-show / Cancellation
   - Record reason in booking notes
   - Follow policy: refund or keep deposit per instructions
   - Offer to reschedule and update booking record

ONE-PAGE CHECKLIST (use every booking)

- [ ] Capture client full name
- [ ] Capture phone number (required)
- [ ] Capture email (if available)
- [ ] Confirm service name and duration
- [ ] Confirm date and time (repeat back)
- [ ] Ask payment preference (Now / Later)
- [ ] If paying now, collect & record amount + method
- [ ] Provide confirmation number / instructions
- [ ] Add notes (allergies, requests, masseuse preference)
- [ ] Tell client cancellation policy and reminder timing
- [ ] Log booking in `admin/bookings.php` and verify it appears

QUICK PHRASES & TEMPLATES

- Booking confirmation (SMS/email):
  "Hi {Name}, your booking for {Service} on {Date} at {Time} is confirmed. Reply if you need to reschedule. - Kallma Spa"

- Reminder (24h):
  "Reminder: your {Service} is tomorrow at {Time}. Reply to confirm or reschedule."

- Cancellation response:
  "We're sorry you can't make it. Please let us know if you'd like to reschedule. Cancellation fee/Refund policy: {policy}."

ESCALATION & CONTACTS

- Admin lead: [Name, phone/email]
- Tech contact (site issues): [Name, phone/email]
- Emergency: [phone]

END-OF-DAY (EOD) TASKS

- [ ] Reconcile payments collected today with bookings (cash/card totals)
- [ ] Mark any completed bookings as Completed and add notes
- [ ] Note no-shows and any follow-ups required
- [ ] Backup/export bookings (if doing manual backup)

WHERE TO FIND THINGS

- Admin Bookings: `/kallma/admin/bookings.php`
- Admin Services: `/kallma/admin/services.php`
- Booking page (customer): `/kallma/booking.php`
- Login for staff/admin: `/kallma/login.php`
- Cancellation & reschedule policy: see `FRONT_DESK_POLICY.md` (create if missing)

QUICK TROUBLESHOOT (TECH)

- If site pages show raw PHP: ensure Apache/PHP is running and file is in `C:\Apache24\htdocs\kallma`
- If CSS missing: confirm `assets/css/style.css` is accessible at `/kallma/assets/css/style.css`
- If bookings not saving: contact dev/admin; record exact error/message and time

-- End of one-page flow --
