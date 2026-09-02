# To-do

- Add Square as a payment provider (alongside Stripe).
  - [x] One-time guest invoice payment via Square (Web Payments SDK + REST
    Payments API create-payment call). Admin can add a Square provider row
    (Application ID / Access Token / Location ID), guest invoice page routes
    "Pay Now" to Stripe or Square depending on which is active.
  - [ ] Saved cards (Square Cards on File)
  - [ ] Autopay off a saved Square card (cron/nightly_tasks.php)
  - [ ] Nightly fee reconciliation via Square's Payments API (mirrors the
    Stripe balance-transaction reconciliation in cron/nightly_tasks.php)
