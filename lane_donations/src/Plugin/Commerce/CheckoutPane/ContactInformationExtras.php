<?php
namespace Drupal\lane_donations\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\ContactInformation;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the contact information pane with additional fields.
 *
 * @CommerceCheckoutPane(
 *   id = "contact_information_extras",
 *   label = @Translation("Contact information"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class ContactInformationExtras extends ContactInformation implements CheckoutPaneInterface {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
      return parent::defaultConfiguration();
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    if (!empty($this->configuration['double_entry'])) {
      $summary = $this->t('Require double entry of email: Yes');
    }
    else {
      $summary = $this->t('Require double entry of email: No');
    }
    
    return $summary;
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
  public function isVisible() {
    // Show the pane only for guest checkout.
    return TRUE;
  }
  
  public function emailIsEditable() {
    // Only allow editing of email address for guest checkout.
    return empty($this->order->getCustomerId());
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    return [
      '#markup' => $this->order->getEmail() . '<br>' . $this->order->getData('phone_home'),
    ];
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    
    if ($this->emailIsEditable()) {
      $pane_form['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Email'),
        '#default_value' => $this->order->getEmail(),
        '#required' => TRUE,
      ];
      if ($this->configuration['double_entry']) {
        $pane_form['email_confirm'] = [
          '#type' => 'email',
          '#title' => $this->t('Confirm email'),
          '#default_value' => $this->order->getEmail(),
          '#required' => TRUE,
        ];
      }
    }
    
    $pane_form['phone_home'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Home phone'),
      '#default_value' => $this->order->getData('phone_home'),
    ];
  
    $pane_form['phone_mobile'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile phone'),
      '#default_value' => $this->order->getData('phone_mobile'),
    ];
    
    return $pane_form;
  }
  
  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if ($this->emailIsEditable() && $this->configuration['double_entry'] && $values['email'] != $values['email_confirm']) {
      $form_state->setError($pane_form['email'], $this->t('The specified emails do not match.'));
      $form_state->setError($pane_form['email_confirm'], $this->t('The specified emails do not match.'));
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if ($this->emailIsEditable()) {
      $this->order->setEmail($values['email']);
    }
    $this->order->setData('phone_home', $values['phone_home']);
    $this->order->setData('phone_mobile', $values['phone_mobile']);
  }
  
}

