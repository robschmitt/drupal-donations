<?php

namespace Drupal\lane_donations\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\lane_donations\DonationHelper;
use Drupal\lane_donations\Entity\Donation;
use Drupal\Core\Link;
use Drupal\lane_donations\Entity\DonationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DonationsController.
 */
class DonationsController extends ControllerBase {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $temp_store;

  public function __construct(PrivateTempStoreFactory $temp_store_factory)
  {
    $this->temp_store = $temp_store_factory->get('lane_donations');
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * @param Donation $donation
   * @param Request $request
   * @return array
   */
  public function paymentRedirect(Request $request)
  {

    $donation_id = $request->get('donation_id');
    if ($donation_id) {
      $donation = Donation::load($donation_id);
      if (!$donation) {
        throw new NotFoundHttpException();
      }
    }

    if ($donation->field_paid->value) {
      // Payment has already been completed.
      throw new NotFoundHttpException();
    }

    /**
     * @var \Drupal\sage_pay_form\SagePayForm $sage_pay_form
     */
    $sage_pay_form = \Drupal::service('sage_pay_form');

    $build[] = $sage_pay_form->buildRedirectForm($donation);

    return $build;

  }

  public function paymentReturn(Request $request)
  {

    $donation_id = $request->get('donation_id');
    if ($donation_id) {
      $donation = Donation::load($donation_id);
      if (!$donation) {
        throw new NotFoundHttpException();
      }
    }

    /**
     * @var \Drupal\sage_pay_form\SagePayForm $sage_pay_form
     */
    $sage_pay_form = \Drupal::service('sage_pay_form');

    try {
      $sage_pay_form->onReturn($donation, $request);
    }
    catch (\SagepayApiException $e) {
      return $this->redirect('lane_donations.donations_controller_error', [
        'donation_id' => $donation->id(),
      ]);
    }

    DonationHelper::sendToClient($donation);
    DonationHelper::sendNotification($donation);
    DonationHelper::sendConfirmation($donation);

    if ($donation->isPurpleDonation()) {
      $route = 'lane_donations.admin_donation_success';
    }
    else {
      $route = 'lane_donations.donations_controller_success';
    }

    return $this->redirect($route, [
      'donation_id' => $donation->id(),
    ]);

  }

  public function paymentCancel(Request $request)
  {

    $donation_id = $request->get('donation_id');
    if ($donation_id) {
      $donation = Donation::load($donation_id);
      if (!$donation) {
        throw new NotFoundHttpException();
      }
    }

    $build[] = [
      '#type' => 'markup',
      '#markup' => $this->t('<h1 class="h3">Your transaction has been cancelled</h1>'),
    ];

    return $build;

  }

  public function paymentError(Request $request)
  {

    $donation_id = $request->get('donation_id');
    if ($donation_id) {
      $donation = Donation::load($donation_id);
      if (!$donation) {
        throw new NotFoundHttpException();
      }
    }

    $build[] = [
      '#type' => 'markup',
      '#markup' => $this->t('<h1>An error has occurred.</h1>'),
    ];

    $build[] = [
      '#type' => 'markup',
      '#markup' => $this->t('<h2 class="h4 mb-3">Your donation has not been processed.</h2>'),
    ];

    return $build;

  }

  /**
   * Donation success / thank you page.
   *
   * @return array
   *   Return Hello string.
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function success(Request $request)
  {
    $donation_id = $request->get('donation_id');
    if ($donation_id) {
      $donation = Donation::load($donation_id);
      if (!$donation) {
        throw new NotFoundHttpException();
      }
    }

    $route = $request->get('_route');

    $build = [];

    if ($this->temp_store->get('log_to_gtm', 1)) {
      $this->temp_store->delete('log_to_gtm');
      $donation_type = $donation->field_donation_type->value;
      if ($route == 'lane_donations.donations_controller_membership_success') {
        $description = 'Monthly Donation';
      }
      else {
        $description = 'Single Donation';
      }

      $build = [
        '#attached' => [
          'drupalSettings' => [
            'laneDonations' => [
              'donation' => [
                'id' => $donation->id(),
                'type' => $donation_type,
                'description' => $description,
                'amount' => number_format($donation->field_donation_amount->value / 100, 2),
              ]
            ],
          ],
          'library' => [
            'lane_donations/gtm-integration'
          ],
        ],
      ];
    }

    $build[] = [
      '#type' => 'markup',
      '#markup' => $this->t('<h1>Thank you for your donation</h1>'),
    ];
    $build[] = [
      '#type' => 'markup',
      '#markup' => '<p>On behalf of all your two and four-legged friends at the Scottish SPCA, thank you so much for your kind donation.</p>'
    ];
    return $build;
  }

  public function adminSuccess(Request $request)
  {

    $donation_id = $request->get('donation_id');
    if ($donation_id) {
      $donation = Donation::load($donation_id);
      if (!$donation) {
        throw new NotFoundHttpException();
      }
    }

    $build[] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>Thank you. The donation has been captured.</p>'),
    ];
    $build[] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>The reference number for this transaction is: <strong>@reference</strong></p>', [
        '@reference' => $donation->reference(),
      ])
    ];
    $build[] = Link::createFromRoute($this->t('Begin a new transaction'), 'lane_donations.admin_donation_contact_form')->toRenderable();
    return $build;
  }

  public function debugOrder(OrderInterface $order)
  {

    $order_items = $order->getItems();
    $ss_data = $order->getData('ss');

    foreach ($order_items as $order_item) {

      if (!$order_item->hasPurchasedEntity()) {
        continue;
      }

      $product_variation = $order_item->getPurchasedEntity();
      $product_variation_type = $product_variation->bundle();

      if ($product_variation_type == 'space') {
        $gift_data = $ss_data[$order_item->id()];
//        if ($gift_data['is_gift']) {
          dsm($gift_data);
          dsm($product_variation->getAttributeValue('attribute_space_location')->field_appeal_code->value, 'appeal code');
          dsm($product_variation->getAttributeValue('attribute_space_location')->field_arrc_location->entity->field_loc_id->value, 'arrc_id');
//        }
      }

//      dsm($product_variation_type, 'bundle');
//      dsm($product_variation->getTitle(), 'title');
//      dsm($product_variation->getAttributeFieldNames(), 'attributes');

    }

    return [
      '#markup' => 'Test order',
    ];

  }



  public function addTestDonation()
  {

    $current_store = \Drupal::service('commerce_store.current_store');
    $store = $current_store->getStore();

    $amount = 25;
    $order_item = \Drupal::entityTypeManager()->getStorage('commerce_order_item')->create([
      'type' => 'donation',
      'title' => t('£@amount donation', ['@amount' => $amount]),
      'unit_price' => [
        'number' => $amount,
        'currency_code' => 'GBP',
      ],
//      'field_frequency' => $form_state->getValue('frequency'),
//      'field_tribute' => $form_state->getValue('tribute'),
//      'field_recipient_name' => $form_state->getValue('recipient_name'),
//      'field_recipient_email' => $form_state->getValue('recipient_email'),
//      'field_description' => $form_state->getValue('description'),
    ]);

    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    // Always use the 'default' order type.
    $cart = $cart_provider->getCart('default', $store);

    if (!$cart) {
      $cart = $cart_provider->createCart('default', $store);
    }
    \Drupal::service('commerce_cart.cart_manager')
      ->addOrderItem($cart, $order_item, FALSE);

//    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
//    $cart_manager = \Drupal::service('commerce_cart.cart_manager');
//
//    dsm('er');
//
//    $product_id = 25;
//
//    $product = Product::load($product_id);
//
//    $product_variation_id = $product->get('variations')
//      ->getValue()[0]['target_id'];
//    $store_id = $product->get('stores')->getValue()[0]['target_id'];
//    $variation = \Drupal::entityTypeManager()
//      ->getStorage('commerce_product_variation')
//      ->load($product_variation_id);
//    $store = \Drupal::entityTypeManager()
//      ->getStorage('commerce_store')
//      ->load($store_id);
//
//    $cart = $cart_provider->getCart('default', $store);
//
//    if (!$cart) {
//      $cart = $cart_provider->createCart('default', $store);
//    }
//
//    $order_item = \Drupal::entityTypeManager()
//      ->getStorage('commerce_order_item')->create([
//      'type' => 'donation',
////      'purchased_entity' => $variation->id(),
//      'quantity' => 1,
//      'unit_price' => 5,//$variation->getPrice(),
//    ]);
//
//    $order_item->save();
//
//    $cart_manager->addOrderItem($cart, $order_item);

//    $cart_provider->addEntity($cart, $order_item);

//    $line_item_type_storage = \Drupal::entityTypeManager()
//      ->getStorage('commerce_order_item_type');

    // Process to place order programatically.
//    $cart_manager = \Drupal::service('commerce_cart.cart_manager');
//    $line_item = $cart_manager->addEntity($cart, $variation);

    return [
      '#markup' => 'Test adding a donation product to the cart'
    ];

  }

  public function testEmailTable()
  {
    $donation = Donation::load(50);

    dsm($donation->toArray());

    $build = [];

    /**
     * Transaction Details
     */
    $rows = [
      [
        [
          'width' => '15%',
          'header' => true,
          'data' => $this->t('Transaction ID'),
        ],
        'REF' . str_pad($donation->id(), 5, '0', STR_PAD_LEFT),
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Value'),
        ],
        '£' . number_format($donation->field_donation_amount->value / 100, 2),
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Transaction Details</h3>'),
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['data-table'],
      ],
    ];

    /**
     * Personal Details
     */

    $rows = [
      [
        [
          'width' => '15%',
          'header' => true,
          'data' => $this->t('Title'),
        ],
        $donation->field_title->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('First name'),
        ],
        $donation->field_first_name->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Surname'),
        ],
        $donation->field_surname->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Date of birth'),
        ],
        $donation->field_dob->value ?? '-',
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Address line 1'),
        ],
        $donation->field_address_line1->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Address line 2'),
        ],
        $donation->field_address_line2->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Town/City'),
        ],
        $donation->field_town->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('County/State'),
        ],
        $donation->field_county->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Country'),
        ],
        $donation->field_country->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Email'),
        ],
        $donation->field_email->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Home phone'),
        ],
        $donation->field_home_phone->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Mobile phone'),
        ],
        $donation->field_mobile_phone->value,
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Personal Details</h3>'),
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['data-table'],
      ],
    ];

    /**
     * Gift Aid
     */
    $rows = [
      [
        [
          'width' => '15%',
          'header' => true,
          'data' => $this->t('Would you like to use Gift Aid?'),
        ],
        $donation->field_gift_aid_eligible->value == 1 ? $this->t('Yes') : $this->t('No'),
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Gift aid</h3>'),
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['data-table'],
      ],
    ];

    /**
     * Stay in touch
     */
    $rows = [
      [
        [
          'width' => '15%',
          'header' => true,
          'data' => $this->t('Post'),
        ],
        $donation->field_ok_to_contact_via_post->value == 1 ? $this->t('Yes') : $this->t('No'),
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Email'),
        ],
        $donation->field_ok_to_contact_via_email->value == 1 ? $this->t('Yes') : $this->t('No'),
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Telephone'),
        ],
        $donation->field_ok_to_contact_via_phone->value == 1 ? $this->t('Yes') : $this->t('No'),
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Text'),
        ],
        $donation->field_ok_to_contact_via_sms->value == 1 ? $this->t('Yes') : $this->t('No'),
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Stay in touch</h3>'),
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['data-table'],
      ],
    ];

    /**
     * Where did you hear about us?
     */
    $rows = [
      [
        [
          'width' => '15%',
          'header' => true,
          'data' => $this->t('Where did you hear about us?'),
        ],
        $donation->field_where_did_you_hear_about->value,
      ],
      [
        [
          'header' => true,
          'data' => $this->t('Media code'),
        ],
        $donation->field_media_code->value,
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => $this->t('<h3>Where did you hear about us?</h3>'),
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['data-table'],
      ],
    ];

    if ($donation->field_donation_type->value == 'recurring') {

      /**
       * Bank instructions
       */

      $rows = [
        [
          [
            'width' => '15%',
            'header' => true,
            'data' => $this->t('Account number'),
          ],
          $donation->field_account_number->value,
        ],
        [
          [
            'header' => true,
            'data' => $this->t('Sort code'),
          ],
          $donation->field_sort_code->value,
        ],
      ];

      $build[] = [
        '#type' => 'table',
        '#prefix' => $this->t('<h3>Bank or building society instructions</h3>'),
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['data-table'],
        ],
      ];

    }

    return $build;

  }

}
