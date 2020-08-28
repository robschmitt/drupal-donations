<?php
namespace Drupal\lane_donations\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_pages\ConfigPagesLoaderService;

/**
 * Provides the contact information pane with additional fields.
 *
 * @CommerceCheckoutPane(
 *   id = "gdpr_contact_options",
 *   label = @Translation("Stay in touch"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class GDPRContactOptions extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * @var ConfigPagesLoaderService
   */
  protected $config_page_loader;

  protected $config_page;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration();
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow, EntityTypeManagerInterface $entity_type_manager, ConfigPagesLoaderService $config_page_loader) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);

    $this->checkoutFlow = $checkout_flow;
    $this->order = $checkout_flow->getOrder();
    $this->setConfiguration($configuration);
    $this->entityTypeManager = $entity_type_manager;

    $this->config_page_loader = $config_page_loader;
    $this->config_page = $this->config_page_loader->load('site_settings');
    $this->terms_url = $this->config_page->field_link_to_terms_page->first()->getUrl()->toString();

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $checkout_flow,
      $container->get('entity_type.manager'),
      $container->get('config_pages.loader')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    return [
      '#markup' =>  'Post: ' . $this->getDisplayValueForGDPROption($this->order->getData('gdpr_post')) .  '<br>' . 'Email: ' . $this->getDisplayValueForGDPROption($this->order->getData('gdpr_email')) . '<br>' . 'Telephone: ' . $this->getDisplayValueForGDPROption($this->order->getData('gdpr_telephone')) . '<br>' . 'SMS: ' . $this->getDisplayValueForGDPROption($this->order->getData('gdpr_sms')),
    ];
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
  
    $pane_form['stay_in_touch'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>We would like to send you further information about our work and how you can support us. Are you happy for us to contact you via:</p>')
    ];

    $pane_form['gdpr_post'] = [
      '#type' => 'radios',
      '#title' => $this->t('Post'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#default_value' => $this->order->getData('gdpr_post') ? $this->order->getData('gdpr_post') : null,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];
  
    $pane_form['gdpr_email'] = [
      '#type' => 'radios',
      '#title' => $this->t('Email'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#default_value' => $this->order->getData('gdpr_post') ? $this->order->getData('gdpr_email') : null,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];
  
    $pane_form['gdpr_telephone'] = [
      '#type' => 'radios',
      '#title' => $this->t('Telephone'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#default_value' => $this->order->getData('gdpr_post') ? $this->order->getData('gdpr_telephone') : null,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];
  
    $pane_form['gdpr_sms'] = [
      '#type' => 'radios',
      '#title' => $this->t('Text'),
      '#options' => [
        1 => $this->t('Yes'),
        2 => $this->t('No'),
      ],
      '#required' => true,
      '#default_value' => $this->order->getData('gdpr_post') ? $this->order->getData('gdpr_sms') : null,
      '#wrapper_attributes' => [
        'class' => [
          'custom-radios', 'inline-label', 'grid-4',
        ]
      ],
    ];

    $pane_form['terms'] = [
      '#type' => 'checkbox',
      '#required' => true,
      '#title' => $this->t('<p>I understand the Scottish SPCA\'s <a href="@terms_url" target="_blank">privacy policy and terms and conditions</a>.</p>', [
        '@terms_url' => $this->terms_url,
      ])
    ];
    
    return $pane_form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    
    $this->order->setData('gdpr_post', $values['gdpr_post']);
    $this->order->setData('gdpr_email', $values['gdpr_email']);
    $this->order->setData('gdpr_telephone', $values['gdpr_telephone']);
    $this->order->setData('gdpr_sms', $values['gdpr_sms']);
  }
  
  protected function getDisplayValueForGDPROption($value)
  {
    return $value === "1" ? 'Yes' : 'No';
  }
  
}

