## Description of modules

### lane_client_api

This modules provides integration with the client's own API. It exposes itself
as a Drupal service `lane_client_api.interface`, so that it can easily be
called from other parts of the system, e.g.:

```php
$client_api = \Drupal::service('lane_client_api.interface');
$client_api->addDonation($donation);
```

### lane_donations

This module does several things:

- Provides a `Donation` entity as the main storage mechanism for donation data.
- Provides various forms for taking one-off and recurring donations.
- Provides integration with Stripe for taking payments.
- Adds a Gift Aid section to the Drupal Commerce checkout flow.
- Integrates with the client's API for recording donations remotely.
