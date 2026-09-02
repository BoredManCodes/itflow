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

  document.getElementById("submit").hidden = false;
}

async function handleSubmit(e) {
  e.preventDefault();
  setLoading(true);

  const result = await card.tokenize();

  if (result.status !== "OK") {
    const message = (result.errors && result.errors[0] && result.errors[0].message)
      || "Please check your card details and try again.";
    showMessage(message);
    setLoading(false);
    return;
  }

  document.getElementById("source_id").value = result.token;

  // Real form submit - the response is a fresh page load handled server-side by
  // guest_pay_invoice_square.php, same as a traditional non-AJAX checkout.
  e.target.submit();
}

function showMessage(messageText) {
  const messageContainer = document.querySelector("#payment-message");

  messageContainer.classList.remove("d-none");
  messageContainer.textContent = messageText;
}

function setLoading(isLoading) {
  if (isLoading) {
    document.querySelector("#submit").disabled = true;
    document.querySelector("#spinner").classList.remove("hidden");
    document.querySelector("#button-text").classList.add("hidden");
  } else {
    document.querySelector("#submit").disabled = false;
    document.querySelector("#spinner").classList.add("hidden");
    document.querySelector("#button-text").classList.remove("hidden");
  }
}
