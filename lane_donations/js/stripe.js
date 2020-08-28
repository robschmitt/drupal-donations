(function ($, Drupal) {

    Drupal.behaviors.stripe = {

        attach: function (context, settings) {

            $("#card-element", context).once("card-element-processed").each(function() {
                var stripe = Stripe(settings.lane_donations.stripe.api_key);
                var client_secret = settings.lane_donations.stripe.client_secret;
                var billing_details = settings.lane_donations.billing_details;

                var elements = stripe.elements();
                var cardElement = elements.create('card', {
                    hidePostalCode: true,
                    'style': {
                        'base': {
                            'border': '2px solid #bfbfbf',
                            'height': '51px',
                        },
                        'invalid': {
                            'color': 'red',
                        },
                    }
                });
                cardElement.mount('#card-element');

                var displayError = document.getElementById('card-errors');
                $(displayError).hide();

                cardElement.addEventListener('change', function(event) {
                    if (event.error) {
                        displayError.textContent = event.error.message;
                        $(displayError).show();
                    } else {
                        displayError.textContent = '';
                        $(displayError).hide();
                    }
                });

                var form = $(this).parents("form");
                var chargeId = form.find("[name='charge_id']");
                var cardButton = form.find(".form-submit");

                cardButton.on("click", function(ev) {

                    ev.preventDefault();

                    var form = this.form;
                    var originalValue = cardButton.attr("value");

                    cardButton
                      .prop('disabled', true)
                      .attr("value", "Processing...");

                    stripe.confirmCardPayment(client_secret, {
                      payment_method: {
                        card: cardElement,
                        billing_details: billing_details
                      }
                    }).then(function(result) {
                      if (result.error) {
                        // Show error to your customer (e.g., insufficient funds)
                        displayError.textContent = result.error.message;
                        $(displayError).show();
                        cardButton
                          .prop('disabled', false)
                          .attr("value", originalValue);
                      }
                      else {
                        // The payment has been processed!
                        if (result.paymentIntent.status === 'succeeded') {
                          displayError.textContent = '';
                          $(displayError).hide();
                          chargeId.val(result.paymentIntent.id);
                          form.submit();
                          cardButton
                            .attr("value", "Success!");
                        }
                      }
                    });

                });

            });

        }

    };

})(jQuery, Drupal);
