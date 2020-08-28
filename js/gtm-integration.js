(function ($, Drupal) {

    Drupal.behaviors.gtmIntegration = {

        attach: function (context, settings) {

            $(document).once('lane-gtm-integration-processed').each(function() {
                if (typeof settings.laneDonations.donation !== "undefined") {
                    var donation = settings.laneDonations.donation;
                    window.dataLayer.push({
                        'event': donation.type === 'single' ? 'purchase' : 'subscribe',
                        'ecommerce': {
                            'purchase': {
                                'actionField': {
                                    'id': donation.id, // Transaction ID. Required for purchases and refunds.
                                    'affiliation': 'Public Donation Form',
                                    'revenue': donation.amount, // Total transaction value (incl. tax and shipping)
                                    // 'tax':'4.90',
                                    // 'shipping': '5.99',
                                    // 'coupon': 'SUMMER_SALE'
                                },
                                'products': [{ // List of productFieldObjects.
                                    'name': donation.description, // Name or ID is required.
                                    'id': donation.id,
                                    'price': donation.amount,
                                    // 'brand': 'Google',
                                    // 'category': 'Apparel',
                                    // 'variant': 'Gray',
                                    'quantity': 1,
                                }]
                            }
                        }
                    });
                }
            });

        }

    };

})(jQuery, Drupal);
