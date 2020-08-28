<?php

namespace Drupal\lane_donations\Form;

use Drupal\config_pages\ConfigPagesLoaderService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\lane_donations\DonationHelper;
use Drupal\lane_donations\Entity\Donation;
use Drupal\Core\Locale\CountryManager;
use Drupal\lane_donations\WhereDidYouHearAboutUsOptions;
use Drupal\lane_sspca_api\SSPCA_API;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DonationForm extends FormBase {

  /**
   * @var \Drupal\lane_sspca_api\SSPCA_API
   */
  protected $sspca_api;

  protected $config_page;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * @var \Drupal\lane_donations\WhereDidYouHearAboutUsOptions
   */
  protected $where_did_you_hear_options;

  protected $default_donation_type;

  protected $default_amount;

  protected $other_amount;

  /**
   * @var True if the form is being displayed in the admin panel
   */
  protected $admin = false;

  protected $terms_url;

  protected $direct_debit_dialog_content;

  /**
   * @var \Drupal\lane_donations\Entity\Donation
   */
  protected $donation;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $temp_store;

  public function getFormId() {
    return 'donation_form';
  }

  public function __construct(SSPCA_API $sspca_api,
                              ConfigPagesLoaderService $config_page_loader,
                              CurrentRouteMatch $current_route_match,
                              PrivateTempStoreFactory $temp_store_factory,
                              Renderer $renderer,
                              WhereDidYouHearAboutUsOptions $where_did_you_hear_options)
  {

    $this->admin = (strpos($current_route_match->getRouteObject()->getPath(), '/admin') === 0);

    $request = $this->getRequest();

    $this->sspca_api = $sspca_api;
    $this->config_page = $config_page_loader->load('site_settings');

    $this->temp_store = $temp_store_factory->get('lane_donations');
    $this->renderer = $renderer;

    $this->where_did_you_hear_options = $where_did_you_hear_options;

    $this->default_donation_type = 'monthly';

    $req_default_amount = $request->get('amount');
    if ($req_default_amount && in_array($req_default_amount, ['5', '10', '15', 'other'])) {
      $this->default_amount = $req_default_amount;
    }
    $req_other_amount = $request->get('other_amount');
    if ($req_other_amount) {
      $this->other_amount = $req_other_amount;
    }
    $req_default_type = $request->get('submitted_type');
    if (!$req_default_type) {
      $req_default_type = $request->get('type');
    }
    if ($req_default_type && in_array($req_default_type, ['single', 'monthly'])) {
      $this->default_donation_type = $req_default_type;
    }

    $this->terms_url = $this->config_page->field_link_to_terms_page->first()->getUrl()->toString();

    if ($this->config_page->field_direct_debit_dialog->value) {
      $dd_dialog = $this->config_page->field_direct_debit_dialog->view('default');
      $this->direct_debit_dialog_content = $this->renderer->renderRoot($dd_dialog);
    }

  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('lane_sspca_api.interface'),
      $container->get('config_pages.loader'),
      $container->get('current_route_match'),
      $container->get('tempstore.private'),
      $container->get('renderer'),
      $container->get('lane_donations.where_did_your_hear_about_us_options')
    );
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $form = [
      '#attributes' => [
        'class' => ['donation-form'],
      ],
      '#attached' => [
        'library' => [
          'lane_donations/donation-forms',
          'lane_donations/tabs'
        ],
      ],
    ];

    if ($this->direct_debit_dialog_content) {
      $form['#attached']['library'][] = 'lane/bootstrap.modal';
    }

    if ($this->admin){
      $form['#attached']['library'][] = 'lane_donations/admin-css';
    }

    $form['your_donation'] = [
      '#type' => 'fieldset',
      '#title' => $this->admin ? $this->t('Donation details') : $this->t('Your donation'),
    ];

    if (!$this->admin) {
      $form['your_donation']['donation_type'] = [
        '#type' => 'markup',
        '#markup' => $this->getTypeTabs(),
      ];

      $form['your_donation']['donation_type_description'] = [
        '#type' => 'markup',
        '#markup' => '',
      ];
    }

    $form['your_donation']['donation_amount'] = [
      '#type' => 'radios',
      '#title' => $this->t('Donation amount'),
      '#options' => [
        '5' => '£5',
        '10' => '£10',
        '15' => '£15',
        'other' => '£___'
      ],
      '#required' => true,
      '#default_value' => $this->default_amount,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label',
        ]
      ],
    ];

    if ($this->getFormId() == 'single_donation_form') {
      $selector = '#single-donation-form :input[name="donation_amount"]';
    }
    else {
      $selector = '#recurring-donation-form :input[name="donation_amount"]';
    }

    $form['your_donation']['other_amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other amount'),
      '#pattern' => '[0-9.]+',
      '#states' => [
        'visible' => [
          $selector => ['value' => 'other']
        ]
      ],
      '#default_value' => $this->other_amount,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ],
      '#attributes' => ['class' => ['field-other-amount']],
    ];

    $form['personal_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Personal details'),
    ];

    $form['personal_details']['title'] = [
      '#type' => 'radios',
      '#title' => $this->t('Title'),
      '#options' => [
        'Mr' => 'Mr',
        'Mrs' => 'Mrs',
        'Miss' => 'Miss',
        'Ms' => 'Ms',
        'Dr' => 'Dr',
      ],
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-5'
        ]
      ],
      '#weight' => -10,
    ];

    $form['personal_details']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ],
      '#weight' => -9,
    ];

    $form['personal_details']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ],
      '#weight' => -8,
    ];

    $form['personal_details']['dob'] = [
      '#type' => 'datelist',
      '#title' => 'Date of birth',
      '#date_part_order' => [
        'day',
        'month',
        'year',
      ],
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ],
      '#weight' => -7,
    ];

    $form['personal_details']['address1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address line 1'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['address2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address line 2'),
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['town'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town / City'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['county'] = [
      '#type' => 'textfield',
      '#title' => $this->t('County / State'),
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => CountryManager::getStandardList(),
      '#default_value' => 'GB',
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['home_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Home phone'),
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    $form['personal_details']['mobile_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile phone'),
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ]
    ];

    if ($this->admin) {
      $gift_aid_title = $this->t('Gift Aid');
    }
    else {
      $gift_aid_title = $this->t('Gift Aid: make your donation worth 25% more!');
    }

    $form['gift_aid'] = [
      '#type' => 'fieldset',
      '#title' => $gift_aid_title,
    ];

    if (!$this->admin) {
      $form['gift_aid']['intro'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Using Gift Aid means we receive an extra 25 pence from the Inland Revenue for every pound you donate. This means £10 can be turned into £12.50, just so long as donations are made through Gift Aid. Would you like to use Gift Aid?'),
        '#prefix' => '<div class="giftaid-info"><p>',
        '#suffix' => '</p></div>',
      ];
      // TODO: Consider whether the option text needs to be configurable.
      $form['gift_aid']['status'] = [
        '#type' => 'radios',
        '#title_display' => 'hidden',
        '#options' => [
          1 => $this->t('<p>Yes, I want the Scottish SPCA to treat all donations I have made for the past four tax years and from this date, unless I notify you otherwise, as Gift Aid donations.</p><p>I am a UK taxpayer and understand that if I pay less Income Tax and/or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that year it is my responsibility to pay any difference.I will notify the Scottish SPCA if I want to cancel this declaration, change my name or home address, or no longer pay sufficient tax on my income and/or capital gains.</p><p>* If you pay income tax at the higher or additional rate and want to receive the additional tax relief due to you, you must include all your Gift Aid donations on your Self-Assessment tax return or ask HM Revenue and Customs to adjust your tax code.</p>'),
          0 => $this->t('<p>I do not qualify for Gift Aid</p>'),
        ],
        '#default_value' => 1,
      ];
    }
    else {
      $form['gift_aid']['status'] = [
        '#type' => 'radios',
        '#title_display' => 'hidden',
        '#options' => [
          1 => $this->t('Yes'),
          0 => $this->t('No'),
        ],
        '#default_value' => 1,
      ];
    }

    $form['stay_in_touch'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stay in touch')
    ];

    $form['stay_in_touch']['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>We would like to tell you more about the work we do and how you can support us. We’d also love to send you our bi-annual magazine direct to your doorstep or our e-magazine straight into your email inbox. Please let us know which methods you’re happy for us to contact you via:</p>')
    ];

    $form['stay_in_touch']['gdpr_post'] = [
      '#type' => 'radios',
      '#title' => $this->t('Post'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => TRUE,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];

    $form['stay_in_touch']['gdpr_email'] = [
      '#type' => 'radios',
      '#title' => $this->t('Email'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => true,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];

    $form['stay_in_touch']['gdpr_telephone'] = [
      '#type' => 'radios',
      '#title' => $this->t('Telephone'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => true,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];

    $form['stay_in_touch']['gdpr_sms'] = [
      '#type' => 'radios',
      '#title' => $this->t('Text'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => true,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];

    // TODO: sort out link, probably putting this text in config.
    $form['stay_in_touch']['terms'] = [
      '#type' => 'checkbox',
      '#required' => true,
      '#title' => $this->t('<p>I understand the Scottish SPCA\'s <a href="@terms_url" target="_blank">privacy policy and terms and conditions</a>.</p>', [
        '@terms_url' => $this->terms_url,
      ])
    ];

    $form['where_hear'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Where did you hear about us?')
    ];

    $form['where_hear']['option'] = [
      '#type' => 'select',
      '#title' => $this->t('Where did you hear about us?'),
      '#options' => $this->where_did_you_hear_options->getSelectOptions(),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ]
      ],
    ];

    return $form;
  }

  protected function addStripePayment(array &$form, $step) {

    if ($step === 2) {

      $form['#attributes']['class'][] = 'stripe-form';

      /**
       * Pass the client secret created during validation of step 1 as well as
       * the billing details back to the form. It is safe to do this:
       * https://stripe.com/docs/payments/payment-intents#passing-to-client
       */
      $form['#attached']['drupalSettings']['lane_donations']['stripe']['client_secret'] = $form_state->get('client_secret');
      $form['#attached']['drupalSettings']['lane_donations']['billing_details'] = [
        'name' => $form_state->getValue('first_name') . ' ' . $form_state->getValue('last_name'),
        'email' => $form_state->getValue('email'),
        'address' => [
          'line1' => $form_state->getValue('address1'),
          'line2' => $form_state->getValue('address2'),
          'city' => $form_state->getValue('town'),
          'postal_code' => $form_state->getValue('postcode'),
          'state' => $form_state->getValue('county'),
          'country' => $form_state->getValue('country'),
        ],
      ];

    }

    if (!$this->admin) {
      // TODO: put this text as configuration.
      $form['your_donation']['donation_type_description']['#markup'] = <<<END
  <div class="donation-type-description">
      <p><strong>Make a single, one-off donation.</strong><br>
      Lorem ipsum dolor sit amet.</p>
  </div>
END;
    }

    if ($step === 1) {

      $form['step'] = [
        '#type' => 'value',
        '#value' => 1,
      ];

      $form['form-actions'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'text-right', 'mb-3',
          ]
        ],
      ];


      $form['form-actions']['continue'] = [
        '#type' => 'submit',
        '#value' => $this->t('Continue to payment'),
      ];

    }
    else {

      $form['step'] = [
        '#type' => 'value',
        '#value' => 2,
      ];

      $form['payment'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Payment details'),
      ];

      if ($this->getFormId() === 'fundraising_form') {
        $donation_amount = $form_state->getValue('amount');
      }
      else {
        $donation_amount = $form_state->getValue('donation_amount');
        if ($donation_amount == 'other') {
          $donation_amount = $form_state->getValue('other_amount');
        }
      }

      $confirmation = $this->t('Please enter your card details below to complete your donation of: <strong>£@amount</strong>', [
        '@amount' => $donation_amount,
      ]);

      $form['payment']['card'] = [
        '#prefix' => '<p class="mb-3">' . $confirmation . '</p>',
        '#suffix' => '<div id="card-errors" class="alert alert-warning mt-3" role="alert"></div>',
        '#type' => 'markup',
        '#markup' => '<div id="card-element"></div>',
      ];

      $form['payment']['form-actions'] = [
        '#type' => 'container',
        '#prefix' => '<hr/>',
        '#attributes' => [
          'class' => [
            'text-right',
          ]
        ],
      ];

      $form['payment']['form-actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Pay securely'),
      ];

    }

  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {

    if (strlen($form_state->getValue('postcode')) > 10) {
      $form_state->setErrorByName('postcode', $this->t('Please provide a valid postcode.'));
    }

    if ($this->getFormId() !== 'fundraising_form') {
      $donation_amount = $form_state->getValue('donation_amount');
      $other_amount = $form_state->getValue('other_amount');
      if ($donation_amount === 'other') {
        if (!$other_amount) {
          $form_state->setErrorByName('other_amount', $this->t('Please specify the amount you wish to donate.'));
        }
        elseif (!$this->isValidAmount($other_amount)) {
          $form_state->setErrorByName('other_amount', $this->t('Please enter a valid amount.'));
        }
      }
    }

  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $form_values = $form_state->getValues();
    $donation_type = $form_values['submitted_type'];
    $single_donation_type = '';
    if ($donation_type == 'single') {
      $single_donation_type = 'oneoff';
    }
    elseif ($donation_type == 'fundraiser') {
      $single_donation_type = 'raisedfunds';
    }

    $donation = Donation::create([
      'uid' => 1,
    ]);
    $donation->save();

    $values = [
      'donation_type' => $donation_type,
      'single_donation_type' => $single_donation_type,
      'date' => date('d/m/Y'),
      'title' => $form_values['title'],
      'first_name' => $form_values['first_name'],
      'surname' => $form_values['last_name'],
      'organisation' => !(empty($form_values['organisation'])) ? $form_values['organisation'] : '',
      'address_line1' => $form_values['address1'],
      'address_line2' => $form_values['address2'],
      'town' => $form_values['town'],
      'county' => $form_values['county'],
      'postcode' => $form_values['postcode'],
      'country' => $form_values['country'],
      'email' => $form_values['email'],
      'home_phone' => $form_values['home_phone'],
      'mobile_phone' => $form_values['mobile_phone'],
      'ok_to_contact_via_post' => $form_values['gdpr_post'] === "1" ? 1 : 0,
      'ok_to_contact_via_email' => $form_values['gdpr_email'] === "1" ? 1 : 0,
      'ok_to_contact_via_sms' => $form_values['gdpr_sms'] === "1" ? 1 : 0,
      'ok_to_contact_via_phone' => $form_values['gdpr_telephone'] === "1" ? 1 : 0,
      'gift_aid_eligible' => !empty($form_values['status']) && $form_values['status'] === "1" ? 1 : 0,
      'where_did_you_hear_about_us' => $form_values['option'],
    ];

    if ($form_values['dob']) {
      $values['dob'] = $form_values['dob']->format('d/m/Y');
    }

    if ($contact_id = $this->sspca_api->createContact($values)) {
      $values['contact_id'] = $contact_id;
    }

    if ($this->getFormId() === 'fundraising_form') {
      $values['donation_amount'] = $form_state->getValue('amount');
    }
    else {
      $values['donation_amount'] = $form_values['donation_amount'];
      if ($form_values['donation_amount'] == 'other') {
        $values['donation_amount'] = $form_values['other_amount'];
      }
    }

    if ($donation_type == 'recurring') {
      $values['day_of_month'] = $form_values['day_of_month'];
      $values['account_name'] = $form_values['account_name'];
      $values['account_number'] = $form_values['account_number'];
      $values['sort_code'] = $form_values['sort_code'];
    }
    elseif ($donation_type === 'fundraiser') {
      $values['fundraising_source'] = $form_state->getValue('fundraising_source');
    }

    $donation = $this->updateDonation($donation, $values);

    if ($donation_type == 'recurring') {
      /**
       * No payment redirect is required so we can send the donation straight
       * to the client, and send our email confirmations
       */
      $this->sspca_api->addDonation($donation);
      DonationHelper::sendConfirmation($donation);
      DonationHelper::sendNotification($donation);

      $redirect_route = 'lane_donations.donations_controller_membership_success';
    }
    else {
      // Fundraisers and single donations require payment
      $redirect_route = 'lane_donations.donations_controller_payment';
    }

    $this->temp_store->set('log_to_gtm', 1);

    $form_state->setRedirect($redirect_route, [
      'donation_id' => $donation->id(),
    ]);

  }

  protected function updateDonation($donation, $values)
  {

    $donation->name = $donation->reference() . ': (' . $values['first_name'] . ' ' . $values['surname'] . ')';

    if (!empty($values['contact_id'])) {
      // No contact ID if the call to the SSPCA API failed for some reason.
      $donation->field_contact_id = $values['contact_id'];
    }
    $donation->field_title = $values['title'];
    $donation->field_first_name = $values['first_name'];
    $donation->field_surname = $values['surname'];
    $donation->field_email = $values['email'];
    $donation->field_home_phone = $values['home_phone'];
    $donation->field_mobile_phone = $values['mobile_phone'];
    if (!empty($values['dob'])) {
      $donation->field_dob = $values['dob'];
    }
    $donation->field_organisation = $values['organisation'];
    $donation->field_address_line1 = $values['address_line1'];
    $donation->field_address_line2 = $values['address_line2'];
    $donation->field_town = $values['town'];
    $donation->field_county = $values['county'];
    $donation->field_postcode = $values['postcode'];
    $donation->field_country = $values['country'];

    $donation->field_ok_to_contact_via_email = $values['ok_to_contact_via_email'];
    $donation->field_ok_to_contact_via_phone = $values['ok_to_contact_via_phone'];
    $donation->field_ok_to_contact_via_post = $values['ok_to_contact_via_post'];
    $donation->field_ok_to_contact_via_sms = $values['ok_to_contact_via_sms'];

    $donation->field_donation_type = $values['donation_type'];
    $donation->field_single_donation_type = $values['single_donation_type'];
    $donation->field_donation_amount = $values['donation_amount'] * 100;

    $donation->field_gift_aid_eligible = $values['gift_aid_eligible'];
    $donation->field_where_did_you_hear_about = $values['where_did_you_hear_about_us'];


    if ($values['donation_type'] == 'recurring') {
      $donation->field_day_of_month = $values['day_of_month'];
      $donation->field_account_name = $values['account_name'];
      $donation->field_account_number = $values['account_number'];
      $donation->field_sort_code = $values['sort_code'];
      $donation->field_appeal = '61000';
      $donation->field_description = 'Recurring Donation';
    }
    else {
      $donation->field_appeal = '62000';
      $donation->field_description = 'Single Donation';
      if ($values['donation_type'] == 'fundraiser') {
        $donation->field_fundraising_source = $values['fundraising_source'];
      }
    }

    $donation->field_donation_complete = 1;

    $donation->save();

    return $donation;

  }

  protected function isValidAmount($amount)
  {
    return $amount === preg_replace('/[^\d.]+/', '', $amount);
  }

  protected function getTypeTabs()
  {

    if ($this->default_donation_type == 'single') {
      $single_active = 'active';
      $single_selected = 'true';
      $monthly_active = '';
      $monthly_selected = '';
    }
    else {
      $single_active = '';
      $single_selected = '';
      $monthly_active = 'active';
      $monthly_selected = 'true';
    }

    return <<<END
<div class="row mb-4">
    <div class="col-6">
        <a class="tab-pill {$monthly_active}" id="recurring-donation-form-tab-1"  href="#recurring-donation-form-wrapper" role="tab"
            aria-controls="recurring-donation-form-wrapper" aria-selected="{$monthly_selected}">Monthly</a>
    </div>
    <div class="col-6">
        <a class="tab-pill {$single_active}" id="single-donation-form-tab-1"  href="#single-donation-form-wrapper" role="tab"
            aria-controls="single-donation-form-wrapper" aria-selected="{$single_selected}">One off</a>
    </div>
</div>
END;
  }

}
