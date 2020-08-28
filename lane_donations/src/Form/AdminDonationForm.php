<?php

namespace Drupal\lane_donations\Form;

use Drupal\config_pages\ConfigPagesLoaderService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\lane_donations\DonationHelper;
use Drupal\lane_donations\Entity\Donation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\lane_client_api\CLIENT_API;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Class AdminDonationForm.
 */
class AdminDonationForm extends FormBase {

  /**
   * Drupal\lane_client_api\CLIENT_API definition.
   *
   * @var \Drupal\lane_client_api\CLIENT_API
   */
  protected $client_api;

  /**
   * @var ConfigPagesLoaderService
   */
  protected $config_page_loader;

  protected $config_page;

  /**
   * @var \Drupal\lane_donations\Entity\Donation
   */
  protected $donation;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $temp_store;

  protected $direct_debit_dialog_content;

  /**
   * Constructs a new AdminDonationForm object.
   */
  public function __construct(CLIENT_API $lane_client_api_interface,
                              PrivateTempStoreFactory $temp_store_factory,
                              ConfigPagesLoaderService $config_page_loader,
                              Renderer $renderer)
  {
    $this->client_api = $lane_client_api_interface;
    $this->temp_store = $temp_store_factory->get('lane_donations');
    $this->config_page_loader = $config_page_loader;
    $this->config_page = $this->config_page_loader->load('site_settings');
    $this->renderer = $renderer;
    $donation_id = \Drupal::request()->get('donation_id');
    $this->donation = Donation::load($donation_id);
    if (!$this->donation) {
      throw new NotFoundHttpException();
    }
    if ($this->config_page->field_direct_debit_dialog->value) {
      $dd_dialog = $this->config_page->field_direct_debit_dialog->view('default');
      $this->direct_debit_dialog_content = $this->renderer->renderRoot($dd_dialog);
    }
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('lane_client_api.interface'),
      $container->get('tempstore.private'),
      $container->get('config_pages.loader'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'admin_donation_form';
  }

  protected function formattedContactName()
  {
    $lines = [
      $this->donation->field_title->value,
      $this->donation->field_first_name->value,
      $this->donation->field_surname->value,
    ];
    $lines = array_filter($lines);
    return implode(' ', $lines);
  }

  protected function formattedContactAddress()
  {
    $lines = [
      $this->donation->field_address_line1->value,
      $this->donation->field_address_line2->value,
      $this->donation->field_town->value,
      $this->donation->field_county->value,
      $this->donation->field_postcode->value,
      $this->donation->field_country->value,
    ];
    $lines = array_filter($lines);
    return implode(', ', $lines);
  }

  protected function createPaymentIntent()
  {
    /**
     * Create a Stripe PaymentIntent to be used in the next step when
     * processing the payment.
     * https://stripe.com/docs/payments/accept-a-payment#web
     */

    \Stripe\Stripe::setApiKey(\getenv('STRIPE_SECRET_KEY'));
    $intent = \Stripe\PaymentIntent::create([
      'amount' => $this->donation->field_donation_amount->value,
      'currency' => 'gbp',
      'receipt_email' => $this->donation->field_email->value,
      'description' => 'Donation reference: ' . $this->donation->reference(),
    ]);

    return $intent->client_secret;

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $donation_type = $this->donation->field_donation_type->value;

    $may_continue = true;
    if ($donation_type == 'recurring' && $this->donation->isComplete()) {
      $may_continue = false;
    } elseif ($donation_type == 'single' && $this->donation->isPaid()) {
      $may_continue = false;
    }
    if (!$may_continue) {
      $this->messenger()->addWarning($this->t('The requested donation has already been completed.'));
      return $this->redirect('user.page');
    }

    $form['submitted_type'] = [
      '#type' => 'hidden',
      '#value' => $donation_type,
    ];

    $form = [
      '#attributes' => [
        'class' => ['donation-form'],
      ],
      '#attached' => [
        'library' => [
          'lane_donations/admin-css',
          'lane_donations/donation-forms',
        ],
      ],
    ];

    $edit_link = Url::fromRoute('lane_donations.admin_donation_contact_form', [
      'donation_id' => $this->donation->id(),
    ])->toString();

    $form['contact_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact details') . ' <a href="' . $edit_link . '" class="button">Edit</a>',
    ];

    $form['contact_details']['details'] = [
      '#type' => 'markup',
      '#markup' => <<<END
<table>
  <tr>
    <th>Name</th>
    <td>{$this->formattedContactName()}</td>
  </tr>
  <tr>
    <th>Address</th>
    <td>{$this->formattedContactAddress()}</td>
  </tr>
</table>
END
    ];

    $form['donation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Donation') . ' <a href="' . $edit_link . '" class="button">Edit</a>',
    ];

    $form['donation']['donation_type'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>' . $this->t('Donation type:') .
        '</strong> ' . $donation_type . '</p>',
    ];

    $form['donation']['donation_amount'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>' . $this->t('Donation amount:') .
        '</strong> £' . number_format($this->donation->field_donation_amount->value / 100, 2) . '</p>',
    ];

    $form['payment_details'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'payment-details-wrapper'],
    ];

    if ($donation_type == 'recurring') {
      $form = $this->buildFormRecurringPayment($form, $form_state);
    }
    else {
      $form = $this->buildFormSinglePayment($form, $form_state);
    }

    return $form;
  }

  public function buildFormSinglePayment(array $form, FormStateInterface $form_state)
  {

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

    return $form;
  }

  public function buildFormRecurringPayment(array $form, FormStateInterface $form_state)
  {

    $form['payment_details']['recurring'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recurring donation'),
    ];

    $form['payment_details']['recurring']['day_of_month'] = [
      '#type' => 'radios',
      '#title' => $this->t('Preferred day of the month direct debit should be taken'),
      '#options' => [1 => 1, 15 => 15],
      '#required' => true,
      '#default_value' => 1,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
    ];

    $form['payment_details']['recurring']['account_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account name'),
      '#required' => true,
    ];

    $form['payment_details']['recurring']['account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account number'),
      '#pattern' => '[0-9]+',
      '#required' => true,
      '#attributes' => ['class' => ['field-account-number']],
    ];

    $form['payment_details']['recurring']['sort_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sort code'),
      '#pattern' => '[0-9-]+',
      '#required' => true,
      '#attributes' => ['class' => ['field-sort-code']],
    ];

    $form['payment_details']['recurring']['confirm_held_file'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this instruction will be held on file'),
      '#required' => true,
    ];

    $form['payment_details']['recurring']['confirm_account_holder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I verify that I am the account holder'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'mb-2',
        ]
      ],
    ];

    if ($this->direct_debit_dialog_content) {
      $dd_guarantee = <<<END
<div style="border: 1px solid #c0c0c0; padding: .5rem;">
  {$this->direct_debit_dialog_content}
</div>
END;

      $form['payment_details']['recurring']['confirm_guarantee'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I understand the <a href=":dd_guarantee_link">Direct Debit Guarantee</a>', [
          ':dd_guarantee_link' => '#',
        ]),
        '#required' => true,
        '#wrapper_attributes' => [
          'class' => [
            'mb-2',
          ]
        ],
        '#suffix' => Markup::create($dd_guarantee),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process donation'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);

    $donation_type = $this->donation->field_donation_type->value;

    if ($donation_type == 'recurring') {
      $account_number = $form_state->getValue('account_number');
      if (strlen(preg_replace('/[^\d]/', '', $account_number)) !== 8) {
        $form_state->setErrorByName('account_number', $this->t('The account number appears to be invalid.'));
      }
      $sort_code = $form_state->getValue('sort_code');
      if (strlen(preg_replace('/[^\d]/', '', $sort_code)) !== 6) {
        $form_state->setErrorByName('sort_code', $this->t('The sort code appears to be invalid.'));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $form_values = $form_state->getValues();

    $donation_type = $this->donation->field_donation_type->value;
    $single_donation_type = '';
    if ($donation_type == 'single') {
      $single_donation_type = 'oneoff';
    }

    $values = [
      'date' => date('d/m/Y'),
      'title' => $this->donation->field_title->value,
      'first_name' => $this->donation->field_first_name->value,
      'surname' => $this->donation->field_surname->value,
      'address_line1' => $this->donation->field_address_line1->value,
      'address_line2' => $this->donation->field_address_line2->value,
      'town' => $this->donation->field_town->value,
      'county' => $this->donation->field_county->value,
      'postcode' => $this->donation->field_postcode->value,
      'country' => $this->donation->field_country->value,
      'email' => $this->donation->field_email->value,
      'home_phone' => $this->donation->field_home_phone->value,
      'mobile_phone' => $this->donation->field_mobile_phone->value,
      'ok_to_contact_via_post' => $this->donation->field_ok_to_contact_via_post->value,
      'ok_to_contact_via_email' => $this->donation->field_ok_to_contact_via_email->value,
      'ok_to_contact_via_sms' => $this->donation->field_ok_to_contact_via_sms->value,
      'ok_to_contact_via_phone' => $this->donation->field_ok_to_contact_via_phone->value,
      'gift_aid_eligible' => $this->donation->field_gift_aid_eligible->value,
      'purple_username' => $this->donation->field_purple_user->value,
      'media_code' => $this->donation->field_media_code->value,
    ];

    if ($this->donation->field_dob->value) {
      $values['dob'] = $this->donation->field_dob->value;
    }

    $contact_id = $this->client_api->createContact($values);
    if ($contact_id) {
      $this->donation->field_contact_id = $contact_id;
    }

    if ($donation_type == 'single') {
      $this->donation->field_donation_type = 'single';
      $this->donation->field_single_donation_type = $single_donation_type;
      $this->donation->field_appeal = '62000';
    }
    else {
      $this->donation->field_donation_type = 'recurring';
      $this->donation->field_day_of_month = $form_values['day_of_month'];
      $this->donation->field_account_name = $form_values['account_name'];
      $this->donation->field_account_number = $form_values['account_number'];
      $this->donation->field_sort_code = $form_values['sort_code'];
      $this->donation->field_appeal = '61000';
    }

    $this->donation->field_donation_complete = 1;

    $this->donation->save();

    if ($donation_type == 'recurring') {
      /**
       * No payment redirect is required so we can send the donation straight
       * to the client, and send our email confirmations.
       */
      $this->client_api->addDonation($this->donation);
      DonationHelper::sendConfirmation($this->donation);
      DonationHelper::sendNotification($this->donation);

      $redirect_route = 'lane_donations.admin_donation_success';
    }
    else {
      // Fundraisers and single donations require payment
      $redirect_route = 'lane_donations.admin_donation_payment';
    }

    $form_state->setRedirect($redirect_route, [
      'donation_id' => $this->donation->id(),
    ]);

  }

}
