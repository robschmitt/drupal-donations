<?php

namespace Drupal\lane_donations\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lane_donations\Entity\Donation;

/**
 * Class FundraisingForm.
 */
class FundraisingForm extends DonationForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fundraising_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    $form['submitted_type'] = [
      '#type' => 'hidden',
      '#value' => 'fundraiser',
    ];

    $form['personal_details']['organisation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organisation / Company / School'),
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ],
      '#weight' => -6,
    ];

    $form['fundraising_details'] = [
      '#weight' => -100,
      '#type' => 'fieldset',
      '#title' => $this->t('Please enter the amount raised'),
    ];

    $form['fundraising_details']['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#pattern' => '[0-9.]+',
      '#required' => true,
      '#field_prefix' => '£',
      '#placeholder' => '1.00',
      '#description' => $this->t('minimum £1.00'),
      '#size' => 10,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ]
      ],
      '#attributes' => ['class' => ['field-other-amount']],
    ];

    $form['further_information'] = [
      '#weight' => -90,
      '#type' => 'fieldset',
      '#title' => $this->t('Further information'),
    ];

    $form['further_information']['fundraising_source'] = [
      '#type' => 'textarea',
      '#title' => $this->t('How did you raise these funds?'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => ['inline-label']
      ],
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

    // Remove sections we don't need for this form
    unset($form['your_donation']);
    unset($form['gift_aid']);

    return $form;
  }

}
