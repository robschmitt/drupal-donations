<?php

namespace Drupal\lane_donations\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\lane_donations\Entity\Donation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\config_pages\ConfigPagesLoaderService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminDonationContactForm.
 */
class AdminDonationContactForm extends FormBase {

  /**
   * @var \Drupal\lane_donations\Entity\Donation
   */
  protected $donation;

  /**
   * @var array
   */
  protected $media_code_options;

  /**
   * \Drupal\Core\Session\AccountProxy
   */
  protected $current_user;

  /**
   * Constructs a new AdminDonationContactForm object.
   */
  public function __construct(ConfigPagesLoaderService $config_page_loader, \Drupal\Core\Session\AccountProxy $current_user)
  {
    $donation_id = \Drupal::request()->get('donation_id');
    if ($donation_id) {
      $this->donation = Donation::load($donation_id);
      if (!$this->donation) {
        throw new NotFoundHttpException();
      }
    }
    $this->config_page_loader = $config_page_loader;
    $this->current_user = $current_user;
    $this->loadMediaCodeOptions();
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config_pages.loader'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'admin_donation_contact_form';
  }

  protected function loadMediaCodeOptions()
  {
    $config_page = $this->config_page_loader->load('donation_settings');
    $options = array_map(function($item) {
      return trim($item['value']);
    }, $config_page->field_media_codes->getValue());
    $options = array_combine($options, $options);
    $options = array_merge(['' => $this->t('Please select')->render()], $options);
    $this->media_code_options = $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

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

    $form['media_code'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Media code'),
    ];

    $form['media_code']['media_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Media code'),
      '#options' => $this->media_code_options,
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_media_code->value : null,
    ];

    $form['personal_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact details'),
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
      '#default_value' => $this->donation ? $this->donation->field_title->value : null,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
    ];

    $form['personal_details']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_first_name->value : null,
    ];

    $form['personal_details']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname'),
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_surname->value : null,
    ];

    $form['personal_details']['home_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Home phone'),
      '#default_value' => $this->donation ? $this->donation->field_home_phone->value : null,
    ];

    $form['personal_details']['mobile_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile phone'),
      '#default_value' => $this->donation ? $this->donation->field_mobile_phone->value : null,
    ];

    $default_dob = null;
    if ($this->donation && $this->donation->field_dob->value) {
      $default_dob = DrupalDateTime::createFromFormat('d/m/Y', $this->donation->field_dob->value);
    }
    $form['personal_details']['dob'] = [
      '#type' => 'datelist',
      '#title' => 'Date of birth',
      '#date_part_order' => [
        'day',
        'month',
        'year',
      ],
      '#default_value' => $default_dob,
    ];

    $form['personal_details']['address1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address line 1'),
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_address_line1->value : null,
    ];

    $form['personal_details']['address2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address line 2'),
      '#default_value' => $this->donation ? $this->donation->field_address_line2->value : null,
    ];

    $form['personal_details']['town'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Town / City'),
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_town->value : null,
    ];

    $form['personal_details']['county'] = [
      '#type' => 'textfield',
      '#title' => $this->t('County / State'),
      '#default_value' => $this->donation ? $this->donation->field_county->value : null,
    ];

    $form['personal_details']['postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#default_value' => $this->donation ? $this->donation->field_postcode->value : null,
    ];

    $form['personal_details']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => CountryManager::getStandardList(),
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_country->value : 'GB',
    ];

    $form['personal_details']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => true,
      '#default_value' => $this->donation ? $this->donation->field_email->value : null,
    ];

    $form['stay_in_touch'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stay in touch')
    ];

    $form['stay_in_touch']['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>Select whether and how the contact consents to be contacted.</p>')
    ];

    $gdpr_post_default = null;
    if ($this->donation) {
      if ($this->donation->field_ok_to_contact_via_post->value === '0') {
        $gdpr_post_default = 2;
      }
      else {
        $gdpr_post_default = 1;
      }
    }
    $form['stay_in_touch']['gdpr_post'] = [
      '#type' => 'radios',
      '#title' => $this->t('Post'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
      '#default_value' => $gdpr_post_default,
    ];

    $gdpr_email_default = null;
    if ($this->donation) {
      if ($this->donation->field_ok_to_contact_via_email->value === '0') {
        $gdpr_email_default = 2;
      }
      elseif ($this->donation->field_ok_to_contact_via_email->value == '1') {
        $gdpr_email_default = 1;
      }
    }
    $form['stay_in_touch']['gdpr_email'] = [
      '#type' => 'radios',
      '#title' => $this->t('Email'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => true,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
      '#default_value' => $gdpr_email_default,
    ];

    $gdpr_phone_default = null;
    if ($this->donation) {
      if ($this->donation->field_ok_to_contact_via_phone->value === '0') {
        $gdpr_phone_default = 2;
      }
      else {
        $gdpr_phone_default = 1;
      }
    }
    $form['stay_in_touch']['gdpr_telephone'] = [
      '#type' => 'radios',
      '#title' => $this->t('Telephone'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => true,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
      '#default_value' => $gdpr_phone_default,
    ];

    $gdpr_sms_default = null;
    if ($this->donation) {
      if ($this->donation->field_ok_to_contact_via_sms->value === '0') {
        $gdpr_sms_default = 2;
      }
      else {
        $gdpr_sms_default = 1;
      }
    }
    $form['stay_in_touch']['gdpr_sms'] = [
      '#type' => 'radios',
      '#title' => $this->t('Text'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#horizontal_form' => true,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
      '#default_value' => $gdpr_sms_default,
    ];

    $form['donation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Donation'),
    ];

    $form['donation']['donation_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Donation type (can be changed later)'),
      '#options' => [
        'single' => $this->t('Single'),
        'recurring' => $this->t('Recurring'),
      ],
      '#required' => true,
      '#attributes' => [
        'class' => [
          'container-inline',
        ]
      ],
      '#default_value' => $this->donation ? $this->donation->field_donation_type->value : null,
    ];

    $form['donation']['other_amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Donation amount'),
      '#pattern' => '[0-9.]+',
      '#required' => true,
      '#field_prefix' => '£',
      '#placeholder' => '5.00',
      '#size' => 10,
      '#attributes' => ['class' => ['field-other-amount']],
      '#default_value' => $this->donation ? number_format( $this->donation->field_donation_amount->value / 100, 2) : null,
    ];

    $form['gift_aid'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Gift Aid'),
    ];

    $gift_aid_default = null;
    if ($this->donation) {
      if (in_array($this->donation->field_gift_aid_eligible->value, ['0', '1'])) {
        $gift_aid_default = $this->donation->field_gift_aid_eligible->value;
      }
    }
    $form['gift_aid']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose the Gift Aid status'),
      '#options' => [
        1 => $this->t('Yes, the contact would like to use Gift Aid'),
        0 => $this->t('No'),
      ],
      '#required' => true,
      '#default_value' => $gift_aid_default,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    if (strlen($form_state->getValue('postcode')) > 10) {
      $form_state->setErrorByName('postcode', $this->t('Please provide a valid postcode.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $form_values = $form_state->getValues();

    if (!$this->donation) {
      $donation = Donation::create([
        'name' => 'Incomplete Purple donation',
        'uid' => 1,
      ]);
    }
    else {
      $donation = $this->donation;
    }

    $donation->field_media_code = $form_values['media_code'];
    $donation->field_purple_user = $this->current_user->getAccountName();

    $donation->field_title = $form_values['title'];
    $donation->field_first_name = $form_values['first_name'];
    $donation->field_surname = $form_values['last_name'];
    $donation->field_email = $form_values['email'];
    $donation->field_home_phone = $form_values['home_phone'];
    $donation->field_mobile_phone = $form_values['mobile_phone'];
    if (!empty($form_values['dob'])) {
      $donation->field_dob = $form_values['dob']->format('d/m/Y');
    }
    $donation->field_address_line1 = $form_values['address1'];
    $donation->field_address_line2 = $form_values['address2'];
    $donation->field_town = $form_values['town'];
    $donation->field_county = $form_values['county'];
    $donation->field_postcode = $form_values['postcode'];
    $donation->field_country = $form_values['country'];

    $donation->field_ok_to_contact_via_email = $form_values['gdpr_email'] === "1" ? 1 : 0;
    $donation->field_ok_to_contact_via_phone = $form_values['gdpr_telephone'] === "1" ? 1 : 0;
    $donation->field_ok_to_contact_via_post = $form_values['gdpr_post'] === "1" ? 1 : 0;
    $donation->field_ok_to_contact_via_sms = $form_values['gdpr_sms'] === "1" ? 1 : 0;

    $donation->field_gift_aid_eligible = $form_values['status'] === "1" ? 1 : 0;

    $donation->field_donation_type = $form_values['donation_type'];
    $donation->field_donation_amount = $form_values['other_amount'] * 100;

    if ($form_values['donation_type'] == 'single') {
      $donation->field_description = 'One off donation from Purple';
      $donation->field_single_donation_type = 'oneoff';
    }
    else {
      $donation->field_description = 'Recurring donation from Purple';
    }

    $is_new = $donation->isNew();

    $donation->save();

    if ($is_new) {
      $donation->name = $donation->reference() . ': (' . $form_values['first_name'] . ' ' . $form_values['last_name'] . ')';
      $donation->save();
    }

    if ($donation->id()) {
      // Redirect to donation form
      $form_state->setRedirect('lane_donations.admin_donation_form', [
        'donation_id' => $donation->id()
      ]);
    }
    else {
      \Drupal::messenger()->addMessage(t('The contact record could not be created.'), 'error');
    }

    return $donation;

  }

}
