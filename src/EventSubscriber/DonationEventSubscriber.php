<?php
namespace Drupal\lane_donations\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\lane_sspca_api\SSPCA_API;
use Drupal\lane_donations\Entity\SspcaDonation;

class SspcaDonationEventSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\lane_sspca_api\SSPCA_API
   */
  protected $sspca_api;

  public function __construct(SSPCA_API $sspca_api) {
    $this->sspca_api = $sspca_api;
  }

  public static function getSubscribedEvents() {
    // The format for adding a state machine event to subscribe to is:
    // {group}.{transition key}.pre_transition or {group}.{transition key}.post_transition
    // depending on when you want to react.
    $events = ['commerce_order.place.post_transition' => 'onOrderPlace'];
    return $events;
  }

  public function onOrderPlace(WorkflowTransitionEvent $event) {

    $order = $event->getEntity();
    $billing_profile = $order->getBillingProfile();
    $billing_address = $billing_profile->get('address')->getValue()[0];

    $gdpr_post = $order->getData('gdpr_post') === "1" ? 1 : 0;
    $gdpr_email = $order->getData('gdpr_email') === "1" ? 1 : 0;
    $gdpr_telephone = $order->getData('gdpr_telephone') === "1" ? 1 : 0;
    $gdpr_sms = $order->getData('gdpr_sms') === "1" ? 1 : 0;

    $order_items = $order->getItems();

    /*
     * TODO: Checkout process needs to collect:
     * Gift Aid eligibility
     */
    $contact = [
      'date' => date('d/m/Y'),
//      'title' => 'UNDEFINED',
      'first_name' => $billing_address['given_name'],
      'surname' => $billing_address['family_name'],
      'address_line1' => $billing_address['address_line1'],
      'address_line2' => $billing_address['address_line2'],
      'town' => $billing_address['locality'],
      'county' => $billing_address['administrative_area'],
      'postcode' => $billing_address['postal_code'],
      'country' => $billing_address['country_code'],
      'email' => $order->getEmail(),
      'home_phone' => $order->getData('phone_home'),
      'mobile_phone' => $order->getData('phone_mobile'),
      'ok_to_contact_via_post' => $gdpr_post,
      'ok_to_contact_via_email' => $gdpr_email,
      'ok_to_contact_via_phone' => $gdpr_telephone,
      'ok_to_contact_via_sms' => $gdpr_sms,
      'gift_aid_eligible' => 0,
      'where_did_you_hear_about_us' => $order->getData('where_did_you_hear_about_us'),
    ];

    if ($contact_id = $this->sspca_api->createContact($contact)) {
      $contact['contact_id'] = $contact_id;
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $payments = \Drupal::entityTypeManager()
      ->getStorage('commerce_payment')
      ->loadMultipleByOrder($order);
    $payment = reset($payments); // For this shop, there's only a single payment per order.
    $stripe_charge_id = $payment->getRemoteId();

    // Get the order sponsored space metadata (if any)
    $ss_data = $order->getData('ss');

    // Get the virtual gift gifting data (if any)
    $vg_data = $order->getData('vg');

    // TODO: write donation(s) to API. Each line item is treated as a donation.
    foreach ($order_items as $order_item) {

      $appeal_code = '62000';
      $donation_type = 'single';
      $single_donation_type = 'shop';

      // Commerce stores the price in a string that looks like a float.
      $donation_amount = number_format((float) $order_item->getTotalPrice()->getNumber(), 2) * 100;

      $donation = [
        'contact_id' => $contact_id,
        'appeal' => $appeal_code,
        'charge_id' => $stripe_charge_id,
        'donation_type' => $donation_type,
        'single_donation_type' => $single_donation_type,
        'donation_amount' => $donation_amount,
        'description' => $order_item->getTitle(),
      ];

      if ($order_item->hasPurchasedEntity()) {
        // getPurchasedEntity() returns the product variation
        $product_variation = $order_item->getPurchasedEntity();

        switch ($product_variation->bundle()) {

          case 'space':

            if (!empty($ss_data[$order_item->id()])) {

              $gift_data = $ss_data[$order_item->id()];

              // The appeal code is saved on the "space location" product attribute
              $donation['appeal'] = $product_variation->getAttributeValue('attribute_space_location')
                ->field_appeal_code->value;
              $donation['donation_type'] = 'sponsor';
              $donation['arrc_id'] = $product_variation->getAttributeValue('attribute_space_location')
                ->field_arrc_location->entity->field_space_arrc_id->value;
              $donation['space_type'] = $product_variation->getAttributeValue('attribute_space_location')
                ->field_arrc_location->entity->field_space_id->value;
              $donation['is_gift'] = $gift_data['is_gift'];
              if ($gift_data['is_gift']) {
                $donation['message'] = $gift_data['message'];
                $donation['recipient_first_name'] = $gift_data['first_name'];
                $donation['recipient_surname'] = $gift_data['surname'];
                $donation['recipient_address_line1'] = $gift_data['address_line_1'];
                $donation['recipient_address_line2'] = $gift_data['address_line_2'];
                $donation['recipient_town'] = $gift_data['town'];
                $donation['recipient_postcode'] = $gift_data['postcode'];
                $donation['recipient_county'] = $gift_data['county'];
                $donation['recipient_country'] = $gift_data['country'];
              }
            }

            break;

        }


      }

      // Save the donation locally
      $donation = $this->createDonation($contact, $donation);

      if ($donation->id() && !empty($contact['contact_id'])) {
        $this->sspca_api->addDonation($donation);
      }

    }

  }

  protected function createDonation($contact, $donation_data) {

    $donation = SspcaDonation::create([
      'name' => $contact['first_name'] . ' ' . $contact['surname'],
      'uid' => 1,
    ]);

    if (!empty($contact['contact_id'])) {
      $donation->field_contact_id = $contact['contact_id'];
    }
    $donation->field_first_name = $contact['first_name'];
    $donation->field_surname = $contact['surname'];
    $donation->field_email = $contact['email'];
    $donation->field_home_phone = $contact['home_phone'];
    $donation->field_mobile_phone = $contact['mobile_phone'];
    $donation->field_address_line1 = $contact['address_line1'];
    $donation->field_address_line2 = $contact['address_line2'];
    $donation->field_town = $contact['town'];
    $donation->field_county = $contact['county'];
    $donation->field_postcode = $contact['postcode'];
    $donation->field_country = $contact['country'];

    $donation->field_ok_to_contact_via_email = $contact['ok_to_contact_via_email'];
    $donation->field_ok_to_contact_via_phone = $contact['ok_to_contact_via_phone'];
    $donation->field_ok_to_contact_via_post = $contact['ok_to_contact_via_post'];
    $donation->field_ok_to_contact_via_sms = $contact['ok_to_contact_via_sms'];

    $donation->field_gift_aid_eligible = $contact['gift_aid_eligible'];
    $donation->field_where_did_you_hear_about = $contact['where_did_you_hear_about_us'];

    $donation->field_donation_type = $donation_data['donation_type'];
    $donation->field_single_donation_type = $donation_data['single_donation_type'];
    $donation->field_donation_amount = $donation_data['donation_amount'];
    $donation->field_description = $donation_data['description'];

    $donation->field_stripe_charge_id = $donation_data['charge_id'];
    $donation->field_appeal = $donation_data['appeal'];

    if ($donation_data['donation_type'] == 'sponsor') {
      $donation->field_arrc_id = $donation_data['arrc_id'];
      $donation->field_space_type = $donation_data['space_type'];
      $donation->field_is_gift = $donation_data['is_gift'];
      if ($donation_data['is_gift']) {
        $donation->field_message = $donation_data['message'];
        $donation->field_recipient_first_name = $donation_data['recipient_first_name'];
        $donation->field_recipient_surname = $donation_data['recipient_surname'];
        $donation->field_recipient_address_line1 = $donation_data['recipient_address_line1'];
        $donation->field_recipient_address_line2 = $donation_data['recipient_address_line2'];
        $donation->field_recipient_town = $donation_data['recipient_town'];
        $donation->field_recipient_postcode = $donation_data['recipient_postcode'];
        $donation->field_recipient_county = $donation_data['recipient_county'];
        $donation->field_recipient_country = $donation_data['recipient_country'];
      }
    }

    $donation->save();

    return $donation;

  }
}
