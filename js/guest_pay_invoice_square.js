let card;

initialize();

document
  .querySelector("#payment-form")
  .addEventListener("submit", handleSubmit);

async function initialize() {
  const applicationId = document.getElementById("square_application_id").value;
  const locationId = document.getElementById("square_location_id").value;

  const payments = Square.payments(applicationId, locationId);
  card = await payments.card();
  await card.attach("#card-container");

  document.getElementById("pay-submit").hidden = false;
}

async function handleSubmit(e) {
  e.preventDefault();
  setLoading(true);

  const result = await card.tokenize();

  if (result.status !== "OK") {
    const message = (result.errors && result.errors[0] && result.errors[0].message)
      || "Please check your card details and try again.";
    showMessage(message);
    logTokenizeFailure(message);
    setLoading(false);
    return;
  }

  document.getElementById("source_id").value = result.token;

  // Real form submit - the response is a fresh page load handled server-side by
  // guest_pay_invoice_square.php, same as a traditional non-AJAX checkout.
  // Calling the prototype method directly (rather than e.target.submit()) survives
  // a future form control being given id/name="submit", which otherwise shadows
  // the form's native submit() with that element - exactly what broke this before.
  HTMLFormElement.prototype.submit.call(e.target);
}

function showMessage(messageText) {
  const messageContainer = document.querySelector("#payment-message");

  messageContainer.classList.remove("d-none");
  messageContainer.classList.add("alert", "alert-danger");
  messageContainer.textContent = messageText;
  messageContainer.scrollIntoView({ behavior: "smooth", block: "center" });
}

// So a rejected card shows up on the invoice in ITFlow instead of vanishing -
// Square's SDK stops a bad card before it ever reaches guest_pay_invoice_square.php.
function logTokenizeFailure(message) {
  const body = new URLSearchParams({
    log_square_tokenize_failure: "1",
    invoice_id: document.getElementById("invoice_id").value,
    url_key: document.getElementById("url_key").value,
    message: message,
  });

  navigator.sendBeacon("guest_post.php", body);
}

function setLoading(isLoading) {
  if (isLoading) {
    document.querySelector("#pay-submit").disabled = true;
    document.querySelector("#spinner").classList.remove("hidden");
    document.querySelector("#button-text").classList.add("hidden");
  } else {
    document.querySelector("#pay-submit").disabled = false;
    document.querySelector("#spinner").classList.add("hidden");
    document.querySelector("#button-text").classList.remove("hidden");
  }
}
