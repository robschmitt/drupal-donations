<?php

namespace Drupal\lane_donations\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lane_donations\Entity\Donation;

class SingleDonationForm extends DonationForm {

  public function getFormId()
  {
    return 'single_donation_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $form = parent::buildForm($form, $form_state);

    $form['submitted_type'] = [
      '#type' => 'hidden',
      '#value' => 'single',
    ];

    if (!$this->admin) {
      // TODO: put this text as configuration.
      $form['your_donation']['donation_type_description']['#markup'] = <<<END
  <div class="donation-type-description">
      <p><strong>Make a single, one-off donation.</strong><br>
      Lorem ipsum dolor sit amet.</p>
  </div>
END;
    }


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

}
