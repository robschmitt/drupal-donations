<?php

namespace Drupal\lane_donations\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class BannerDonationForm.
 */
class BannerDonationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'banner_donation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $build_info = $form_state->getBuildInfo();
    $title = !empty($build_info['args'][0]) ? $build_info['args'][0] : '';
    $intro = !empty($build_info['args'][1]) ? $build_info['args'][1] : '';
    $amount_content = !empty($build_info['args'][2]) ? $build_info['args'][2] : [];
    $link_below = !empty($build_info['args'][3]) ? $build_info['args'][3] : '';

    $form['#attached']['library'] = [
      'lane_donations/donation-forms',
    ];

    $form['title'] = [
      '#type' => 'markup',
      '#markup' => '<h2 class="h3">' . $title . '</h2>',
    ];

    if ($intro) {
      $form['intro'] = [
        '#type' => 'container',
        '#markup' => $intro,
        '#attributes' => [
          'class' => ['small-text'],
        ]
      ];
    }

    $form['donation_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Donation type'),
      '#title_display' => 'invisible',
      '#options' => [
        'monthly' => $this->t('Monthly'),
        'single' => $this->t('One off'),
      ],
      '#default_value' => 'monthly',
      '#attributes' => [
        'class' => ['custom-radios'],
      ],
    ];

    $form['donation_info_one_off'] = [
      '#type' => 'container',
      '#markup' => '<p class="small-text">' . $this->t('Make a donation today and help us save animals in Scotland.') . '</p>',
      '#states' => [
        'visible' => [
          ':input[name="donation_type"]' => [
            'value' => 'single',
          ]
        ]
      ]
    ];

    $form['donation_info_monthly'] = [
      '#type' => 'container',
      '#markup' => '<p class="small-text">' . $this->t('Donate monthly and become a member of the Scottish SPCA.') . '</p>',
      '#states' => [
        'visible' => [
          ':input[name="donation_type"]' => [
            'value' => 'monthly',
          ]
        ]
      ]
    ];

    $form['donation_amount'] = [
      '#type' => 'radios',
      '#title' => $this->t('Donation amount'),
      '#title_display' => 'invisible',
      '#options' => [
        '5' => '£5',
        '10' => '£10',
        '15' => '£15',
        'other' => '£___'
      ],
      '#default_value' => '10',
      '#attributes' => [
        'class' => ['custom-radios', 'grid-4'],
      ],
    ];

    $form['other_amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other amount'),
      '#pattern' => '[0-9.]+',
      '#states' => [
        'visible' => [
          ':input[name="donation_amount"]' => ['value' => 'other']
        ]
      ],
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Please specify the amount'),
      '#wrapper_attributes' => [
        'class' => ['inline-label', 'form-item-other-amount'],
      ],
      '#attributes' => ['class' => ['field-other-amount']],
    ];

    if (!empty($amount_content['amount_1'])) {

      $form['donation_amount_5_info'] = [
        '#type' => 'container',
        '#markup' => $amount_content['amount_1'],
        '#states' => [
          'visible' => [
            ':input[name="donation_amount"]' => ['value' => '5']
          ]
        ]
      ];
    }

    if (!empty($amount_content['amount_2'])) {
      $form['donation_amount_10_info'] = [
        '#type' => 'container',
        '#markup' => $amount_content['amount_2'],
        '#states' => [
          'visible' => [
            ':input[name="donation_amount"]' => ['value' => '10']
          ]
        ]
      ];
    }

    if (!empty($amount_content['amount_3'])) {
      $form['donation_amount_15_info'] = [
        '#type' => 'container',
        '#markup' => $amount_content['amount_3'],
        '#states' => [
          'visible' => [
            ':input[name="donation_amount"]' => ['value' => '15']
          ]
        ]
      ];
    }

    if (!empty($amount_content['amount_other'])) {
      $form['donation_amount_other_info'] = [
        '#type' => 'container',
        '#markup' => $amount_content['amount_other'],
        '#states' => [
          'visible' => [
            ':input[name="donation_amount"]' => ['value' => 'other']
          ]
        ]
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Donate'),
    ];

    $footer_content = '';
    if ($link_below) {
      $footer_content .= '<div class="small-text mr-2 link-below">' . $link_below . '</div>';
    }
    $footer_content .= '<span class="icon-direct-debit ml-auto"></span>';

    $form['footer'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'mt-2', 'justify-content-center'],
      ],
      '#markup' => $footer_content,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);

    $amount = $form_state->getValue('donation_amount');
    $other_amount = $form_state->getValue('other_amount');

    if ($amount == 'other') {
      if ($other_amount && !$this->isValidAmount($other_amount)) {
        $form_state->setErrorByName('other_amount', $this->t('Please enter a valid amount.'));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $config_page = \Drupal\config_pages\Entity\ConfigPages::config('site_settings');
    if (!empty($config_page->field_donation_page_link->uri)) {

      $amount = $form_state->getValue('donation_amount');

      $query = [
        'type' => $form_state->getValue('donation_type'),
        'amount' => $amount,
      ];

      if ($amount === 'other') {
        $query['other_amount'] = $form_state->getValue('other_amount');
      }

      $url = Url::fromUri($config_page->field_donation_page_link->uri, [
        'query' => $query,
      ]);

      $form_state->setRedirectUrl($url);

    }

  }

  protected function isValidAmount($amount)
  {
    return $amount === preg_replace('/[^\d.]+/', '', $amount);
  }

}
