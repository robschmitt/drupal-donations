<?php

namespace Drupal\lane_donations;

use Drupal\lane_donations\Entity\SspcaDonationInterface;

class DonationHelper {

  public static function sendToSspca(SspcaDonationInterface $donation)
  {
    /** @var \Drupal\lane_sspca_api\SSPCA_API $sspca_api */
    $sspca_api = \Drupal::service('lane_sspca_api.interface');
    $sspca_api->addDonation($donation);
  }

  public static function sendConfirmation(SspcaDonationInterface $donation)
  {

    $to = $donation->field_email->value;
    $donation_type = $donation->field_donation_type->value;

    $params = [
      'donation_reference' => $donation->reference(),
    ];
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    switch ($donation_type) {
      case 'single':
        $params['body'] = [
          '#theme' => 'lane_donations_confirmation_single'
        ];
        break;
      case 'recurring':
        $params['body'] = [
          '#theme' => 'lane_donations_confirmation_recurring'
        ];
        break;
      case 'fundraiser':
        $params['body'] = [
          '#theme' => 'lane_donations_confirmation_fundraiser'
        ];
        break;
    }

    $result = \Drupal::service('plugin.manager.mail')
      ->mail('lane_donations', $donation_type, $to, $langcode, $params);

    if ($result['result'] != true) {
      $message = t('There was a problem sending your email confirmation to @email.', array('@email' => $to));
      \Drupal::logger('lane_donations')->error($message);
      return;
    }

    $message = t('An email confirmation has been sent to @email ', array('@email' => $to));
    \Drupal::logger('lane_donations')->notice($message);
  }

  /**
   * @param SspcaDonationInterface $donation
   */
  public static function sendNotification(SspcaDonationInterface $donation)
  {

    $current_user = \Drupal::currentUser();
    $donation_type = $donation->field_donation_type->value;

    $config_page_loader = \Drupal::service('config_pages.loader');
    $config_page = $config_page_loader->load('site_settings');

    $params = [
      'bcc' => $current_user->getEmail(),
    ];

    $langcode = $current_user->getPreferredLangcode();

    $build = [];

    switch ($donation_type) {
      case 'single':
        if ($donation->isPurpleDonation()) {
          $to = $config_page->field_admn_donation_email_oneoff->value;
        }
        else {
          $to = $config_page->field_donation_email_oneoff->value;
        }
        $build[] = [
          '#markup' => t('A new donation payment has been received.'),
        ];
        break;
      case 'recurring':
        if ($donation->isPurpleDonation()) {
          $to = $config_page->field_admn_donation_email_recurr->value;
        }
        else {
          $to = $config_page->field_donation_email_recurring->value;
        }
        $build[] = [
          '#markup' => t('A new recurring donation has been received.'),
        ];
        break;
      case 'fundraiser':
        $to = $config_page->field_donation_email_oneoff->value;
        $build[] = [
          '#markup' => t('A new fundraiser payment has been received.'),
        ];
        break;
    }

    self::buildConfirmationTable($donation, $build);

    $params['body'] = $build;

    $result = \Drupal::service('plugin.manager.mail')
      ->mail('lane_donations', 'notification', $to, $langcode, $params);

    if ($result['result'] != true) {
      $message = t('There was a problem sending the email notification to @email.', array('@email' => $to));
      \Drupal::logger('lane_donations')->error($message);
      return;
    }

    $message = t('An email notification has been sent to @email ', array('@email' => $to));
    \Drupal::logger('lane_donations')->notice($message);

  }


  public static function buildConfirmationTable(SspcaDonationInterface $donation, array &$build)
  {

    /**
     * Transaction Details
     */
    $rows = [
      [
        [
          'width' => '15%',
          'header' => true,
          'data' => t('Transaction ID'),
        ],
        $donation->reference(),
      ],
      [
        [
          'header' => true,
          'data' => t('Value'),
        ],
        '£' . number_format($donation->field_donation_amount->value / 100, 2),
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => t('<h3>Transaction Details</h3>'),
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
          'data' => t('Title'),
        ],
        $donation->field_title->value,
      ],
      [
        [
          'header' => true,
          'data' => t('First name'),
        ],
        $donation->field_first_name->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Surname'),
        ],
        $donation->field_surname->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Date of birth'),
        ],
        $donation->field_dob->value ?? '-',
      ],
      [
        [
          'header' => true,
          'data' => t('Organisation'),
        ],
        $donation->field_organisation->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Address line 1'),
        ],
        $donation->field_address_line1->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Address line 2'),
        ],
        $donation->field_address_line2->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Town/City'),
        ],
        $donation->field_town->value,
      ],
      [
        [
          'header' => true,
          'data' => t('County/State'),
        ],
        $donation->field_county->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Country'),
        ],
        $donation->field_country->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Postcode'),
        ],
        $donation->field_postcode->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Email'),
        ],
        $donation->field_email->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Home phone'),
        ],
        $donation->field_home_phone->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Mobile phone'),
        ],
        $donation->field_mobile_phone->value,
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => t('<h3>Personal Details</h3>'),
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
          'data' => t('Would you like to use Gift Aid?'),
        ],
        $donation->field_gift_aid_eligible->value == 1 ? t('Yes') : t('No'),
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => t('<h3>Gift aid</h3>'),
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
          'data' => t('Post'),
        ],
        $donation->field_ok_to_contact_via_post->value == 1 ? t('Yes') : t('No'),
      ],
      [
        [
          'header' => true,
          'data' => t('Email'),
        ],
        $donation->field_ok_to_contact_via_email->value == 1 ? t('Yes') : t('No'),
      ],
      [
        [
          'header' => true,
          'data' => t('Telephone'),
        ],
        $donation->field_ok_to_contact_via_phone->value == 1 ? t('Yes') : t('No'),
      ],
      [
        [
          'header' => true,
          'data' => t('Text'),
        ],
        $donation->field_ok_to_contact_via_sms->value == 1 ? t('Yes') : t('No'),
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => t('<h3>Stay in touch</h3>'),
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
          'data' => t('Where did you hear about us?'),
        ],
        $donation->field_where_did_you_hear_about->value,
      ],
      [
        [
          'header' => true,
          'data' => t('Media code'),
        ],
        $donation->field_media_code->value,
      ],
    ];

    $build[] = [
      '#type' => 'table',
      '#prefix' => t('<h3>Where did you hear about us?</h3>'),
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
            'data' => t('Account number'),
          ],
          $donation->field_account_number->value,
        ],
        [
          [
            'header' => true,
            'data' => t('Sort code'),
          ],
          $donation->field_sort_code->value,
        ],
      ];

      $build[] = [
        '#type' => 'table',
        '#prefix' => t('<h3>Bank or building society instructions</h3>'),
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['data-table'],
        ],
      ];

    }
    else {

      /**
       * Sage Pay Payment reference
       */

      $rows = [
        [
          [
            'width' => '15%',
            'header' => true,
            'data' => t('Sage Pay payment reference'),
          ],
          $donation->field_payment->entity->vendor_tx_code->value,
        ],
      ];

      $build[] = [
        '#type' => 'table',
        '#prefix' => t('<h3>Payment details</h3>'),
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['data-table'],
        ],
      ];

    }

    return $build;

  }

}
