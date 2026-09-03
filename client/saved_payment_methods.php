<?php
/*
 * Client Portal - AutoPay Configuration (Stripe or Square, whichever is active)
 */

require_once "includes/inc_all.php";
require_once '../includes/square_api.php';

enforceContactCan('accounting');

// Get the single active payment provider
$provider_query = mysqli_query($mysqli, "
    SELECT payment_provider_id, payment_provider_name, payment_provider_private_key, payment_provider_public_key, payment_provider_location_id
    FROM payment_providers WHERE payment_provider_active = 1 LIMIT 1
");
$provider = mysqli_fetch_assoc($provider_query);

if (!$provider) {
    echo "Online payment error - no payment provider is configured.";
    include_once 'includes/footer.php';
    exit();
}

$provider_id = intval($provider['payment_provider_id']);
$provider_name = escapeHtml($provider['payment_provider_name']);
$public_key = escapeHtml($provider['payment_provider_public_key']);
$private_key = escapeHtml($provider['payment_provider_private_key']);
$location_id = escapeHtml($provider['payment_provider_location_id']);

if (!$public_key || !$private_key) {
    echo "$provider_name payment error - credentials missing. Please contact support.";
    include_once 'includes/footer.php';
    exit();
}

// Get client's provider customer ID
$provider_customer_query = mysqli_query($mysqli, "
    SELECT payment_provider_client FROM client_payment_provider
    WHERE client_id = $session_client_id AND payment_provider_id = $provider_id
    LIMIT 1
");
$provider_customer = mysqli_fetch_assoc($provider_customer_query);
$provider_customer_id = $provider_customer ? escapeSql($provider_customer['payment_provider_client']) : null;

// Get saved payment methods
$saved_methods_query = mysqli_query($mysqli, "
    SELECT * FROM client_saved_payment_methods
    WHERE saved_payment_client_id = $session_client_id
    AND saved_payment_provider_id = $provider_id
");

$saved_methods = [];
while ($row = mysqli_fetch_assoc($saved_methods_query)) {
    $saved_methods[] = $row;
}

?>

<h3>Saved Payment Methods</h3>
<hr>
<div class="row">
    <div class="col-md-6">

        <?php if (!$provider_customer_id) { ?>
            In order to set up automatic payments, you must create a <?= $provider_name ?> customer record.
            <br>
            By saving your card details, you grant consent for automatic payments.
            <small class="text-muted d-block mt-2"><?= $provider_name ?> processes your information in accordance with its Privacy Policy and Terms.</small>
            <br>

            <form action="post.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-3">
                    <button type="submit" class="btn btn-success" name="create_<?= strtolower($provider_name) ?>_customer"><strong><i class="fas fa-check me-2"></i>Continue</strong></button>
                </div>
            </form>

        <?php } else { ?>

            <b>Manage saved payment methods</b><br><br>

            <?php if (empty($saved_methods)) { ?>
                <p>You currently have no saved payment methods. Please add one below.</p>
            <?php } else { ?>
                <p>Payment methods you've authorized us to save for future payments:</p>
                <ul class="list-unstyled">
                    <?php

                    if ($provider_name === 'Stripe') {
                        try {
                            require_once '../includes/stripe_init.php';
                            $stripe = new \Stripe\StripeClient($private_key);

                            foreach ($saved_methods as $method) {
                                $stripe_pm_id = $method['saved_payment_provider_method'];

                                $pm = $stripe->paymentMethods->retrieve($stripe_pm_id, []);
                                $brand = escapeHtml($pm->card->brand);
                                $last4 = escapeHtml($pm->card->last4);
                                $exp_month = escapeHtml($pm->card->exp_month);
                                $exp_year = escapeHtml($pm->card->exp_year);

                                $payment_icon = paymentBrandIcon($brand);

                                echo "<li><i class='$payment_icon fa-2x me-2'></i>$brand x<strong>$last4</strong> | Exp. $exp_month/$exp_year";
                                echo " – <a class='text-danger' href='post.php?delete_saved_payment={$method['saved_payment_id']}&csrf_token={$_SESSION['csrf_token']}'>Remove</a></li>";
                            }
                        } catch (Exception $e) {
                            $error = $e->getMessage();
                            error_log("Stripe payment error: $error");
                            logApp("Stripe", "error", "Exception retrieving payment methods: $error");
                            echo "<p class='text-danger'>Unable to retrieve payment methods from Stripe.</p>";
                        }
                    } else {
                        // Square - the description saved at card-creation time already has brand/last4/exp,
                        // no need for a live API call just to render the list
                        foreach ($saved_methods as $method) {
                            $description = escapeHtml($method['saved_payment_description']);
                            $payment_icon = paymentBrandIcon($description);

                            echo "<li><i class='$payment_icon fa-2x me-2'></i>$description";
                            echo " – <a class='text-danger' href='post.php?delete_saved_payment={$method['saved_payment_id']}&csrf_token={$_SESSION['csrf_token']}'>Remove</a></li>";
                        }
                    }
                    ?>
                </ul>
            <?php } ?>
        </div>
        <div class="col-md-6">
            <b>Add a new payment method</b>
            <p>If you save payment details, you grant consent for automatic payments.</p>
            <br><br>

            <?php if ($provider_name === 'Stripe') { ?>
                <input type="hidden" id="stripe_publishable_key" value="<?= $public_key ?>">
                <script src="https://js.stripe.com/v3/"></script>
                <script src="../js/autopay_setup_stripe.js"></script>
                <div id="checkout">
                    <!-- Checkout form dynamically loaded -->
                </div>
            <?php } else {
                $square_sandbox = squareIsSandbox($provider['payment_provider_public_key']);
                $square_js_host = $square_sandbox ? 'sandbox.web.squarecdn.com' : 'web.squarecdn.com';
            ?>
                <script src="https://<?= $square_js_host ?>/v1/square.js"></script>
                <input type="hidden" id="square_application_id" value="<?= $public_key ?>">
                <input type="hidden" id="square_location_id" value="<?= $location_id ?>">
                <form id="payment-form" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="source_id" name="source_id" value="">
                    <div id="card-container"></div>
                    <br>
                    <button type="submit" name="create_square_card" id="submit" class="btn btn-success" hidden="hidden">
                        <div class="spinner hidden" id="spinner"></div>
                        <span id="button-text"><i class="fas fa-check me-2"></i>Save Card</span>
                    </button>
                    <div id="payment-message" class="d-none text-danger"></div>
                </form>
                <script src="../js/autopay_setup_square.js"></script>
            <?php } ?>

        <?php } ?>

    </div>
</div>

<?php require_once "includes/footer.php"; ?>
