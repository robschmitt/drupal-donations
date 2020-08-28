<?php
namespace Drupal\lane_donations\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\config_pages\ConfigPagesLoaderService;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\lane_donations\WhereDidYouHearAboutUsOptions;

/**
 * Provides the contact information pane with additional fields.
 *
 * @CommerceCheckoutPane(
 *   id = "where_did_you_hear_about_us",
 *   label = @Translation("Where did you hear about us?"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class WhereDidYouHearAboutUs extends CheckoutPaneBase implements CheckoutPaneInterface {
  /**
   * @var ConfigPagesLoaderService
   */
  protected $config_page_loader;

  /**
   * @var \Drupal\lane_donations\WhereDidYouHearAboutUsOptions
   */
  protected $where_did_you_hear_options;

  /**
  * Constructs a new WhereDidYouHearAboutUs object.
  *
  * @param array $configuration
  *   A configuration array containing information about the plugin instance.
  * @param string $plugin_id
  *   The plugin_id for the plugin instance.
  * @param mixed $plugin_definition
  *   The plugin implementation definition.
  * @param \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface $checkout_flow
  *   The parent checkout flow.
  * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
  *   The entity type manager.
  * @param \Drupal\config_pages\ConfigPagesLoaderService $config_page_loader
  *   The inline form manager.
  */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              CheckoutFlowInterface $checkout_flow,
                              EntityTypeManagerInterface $entity_type_manager,
                              ConfigPagesLoaderService $config_page_loader,
                              WhereDidYouHearAboutUsOptions $where_did_you_hear_options) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $checkout_flow, $entity_type_manager);
    
    $this->config_page_loader = $config_page_loader;
    $this->where_did_you_hear_options = $where_did_you_hear_options;
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
      $container->get('config_pages.loader'),
      $container->get('lane_donations.where_did_your_hear_about_us_options')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration();
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    
    return $form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $options = $this->where_did_you_hear_options->getOptions();
    $options = $options[$this->order->getData('where_did_you_hear_about_us')];
    return [
      '#markup' => $options,
    ];
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['where_hear'] = [
      '#type' => 'select',
      '#title' => $this->t('Where did you hear about us?'),
      '#options' => $this->where_did_you_hear_options->getSelectOptions(),
      '#default_value' => $this->order->getData('where_did_you_hear_about_us'),
      '#required' => true,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ]
      ],
    ];
    
    
    return $pane_form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setData('where_did_you_hear_about_us', $values['where_hear']);
  }
  
}

