# To-do

- Add Square as a payment provider (alongside Stripe). Done - full parity with Stripe:
  - [x] One-time guest invoice payment via Square (Web Payments SDK + REST
    Payments API create-payment call). Admin can add a Square provider row
    (Application ID / Access Token / Location ID), guest invoice page routes
    "Pay Now" to Stripe or Square depending on which is active.
  - [x] Saved cards (Square Cards on File). Client portal AutoPay page
    (client/saved_payment_methods.php) creates a Square customer on consent,
    then tokenizes/saves a card via the Web Payments SDK + POST /v2/cards.
    Removal disables the card in Square (client/post.php, admin/post/saved_payment_method.php).
    One-time "pay with saved card" (client/post.php add_payment_by_provider,
    agent/post/payment.php add_payment_stripe) branches Stripe/Square by the
    saved method's own provider.
  - [x] Autopay off a saved Square card (cron/nightly_tasks.php) - mirrors the
    Stripe PaymentIntent branch using POST /v2/payments with the stored card id.
  - [x] Nightly fee reconciliation via Square's Payments API (mirrors the
    Stripe balance-transaction reconciliation in cron/nightly_tasks.php) -
    reads the payment's processing_fee once Square has assessed it.
