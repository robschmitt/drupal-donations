<?php

namespace Drupal\lane_donations\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;

/**
 * Class RecurringDonationForm.
 */
class RecurringDonationForm extends DonationForm {

  public function getFormId()
  {
    return 'recurring_donation_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $form = parent::buildForm($form, $form_state);

    if ($this->direct_debit_dialog_content) {
      $dd_modal_content = <<<END
<div class="modal fade" id="dd-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body pt-3 pb-2 px-2 pt-md-3 px-md-4 pb-md-4">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                {$this->direct_debit_dialog_content}
            </div>
        </div>
    </div>
</div>
END;
      $form['#suffix'] = Markup::create($dd_modal_content);
    }

    $form['submitted_type'] = [
      '#type' => 'hidden',
      '#value' => 'recurring',
    ];

    if (!$this->admin) {
      // TODO: put this text as configuration.
      $form['your_donation']['donation_type_description']['#markup'] = <<<END
  <div class="donation-type-description">
      <p><strong>Donate monthly and become a member of the Scottish SPCA.</strong><br>
      We rely on your donations to help put an end to animal cruelty and help us save animals in Scotland</p>
  </div>
END;
    }

    $form['dd_instructions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Instructions to your bank or building society'),
    ];

    $form['dd_instructions']['day_of_month'] = [
      '#type' => 'radios',
      '#title' => $this->t('Preferred day of the month direct debit should be taken'),
      '#options' => [1 => $this->t('1st'), 15 => $this->t('15th')],
      '#required' => true,
      '#default_value' => 1,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];

    $form['dd_instructions']['account_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account name'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ]
      ],
    ];

    $form['dd_instructions']['account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account number'),
      '#pattern' => '[0-9]+',
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ]
      ],
      '#attributes' => ['class' => ['field-account-number']],
    ];

    $form['dd_instructions']['sort_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sort code'),
      '#pattern' => '[0-9-]+',
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ]
      ],
      '#attributes' => ['class' => ['field-sort-code']],
    ];

    $form['dd_instructions']['confirm_held_file'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this instruction will be held on file'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'mb-2',
        ]
      ],
    ];

    if ($this->direct_debit_dialog_content) {

      $form['dd_instructions']['confirm_guarantee'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I understand the <a href=":dd_guarantee_link" data-toggle="modal" data-target="#dd-modal">Direct Debit Guarantee</a>', [
          ':dd_guarantee_link' => '#',
        ]),
        '#required' => true,
        '#wrapper_attributes' => [
          'class' => [
            'mb-2',
          ]
        ],
      ];

    }

    $form['dd_instructions']['confirm_account_holder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I verify that I am the account holder'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'mb-2',
        ]
      ],
    ];

    $form['dd_instructions']['form-actions'] = [
      '#type' => 'container',
      '#prefix' => '<hr/>',
      '#attributes' => [
        'class' => [
          'text-right',
        ]
      ],
    ];

    $form['dd_instructions']['form-actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process donation'),
    ];

    return $form;

  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {

    parent::validateForm($form, $form_state);

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
