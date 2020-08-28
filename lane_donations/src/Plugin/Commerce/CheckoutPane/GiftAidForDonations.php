<?php
namespace Drupal\lane_donations\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the contact information pane with additional fields.
 *
 * @CommerceCheckoutPane(
 *   id = "gift_aid_for_donations",
 *   label = @Translation("Gift Aid: make your donation worth 25% more!"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class GiftAidForDonations extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary()
  {
    if (!is_null($this->order->getData('gift_aid'))) {
      return [
        '#markup' => $this->getGiftAidText($this->order->getData('gift_aid')),
      ];
    }
  }

  protected function getGiftAidText($status)
  {
    $text = [
      $this->t('<p>I do not qualify for Gift Aid</p>'),
      $this->t('<p>Yes, I want the Scottish SPCA to treat all donations I have made for the past four tax years and from this date, unless I notify you otherwise, as Gift Aid donations.</p><p>I am a UK taxpayer and understand that if I pay less Income Tax and/or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that year it is my responsibility to pay any difference.I will notify the Scottish SPCA if I want to cancel this declaration, change my name or home address, or no longer pay sufficient tax on my income and/or capital gains.</p><p>* If you pay income tax at the higher or additional rate and want to receive the additional tax relief due to you, you must include all your Gift Aid donations on your Self-Assessment tax return or ask HM Revenue and Customs to adjust your tax code.</p>'),
    ];
    return $text[$status];
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible()
  {
    $visible = false;
    foreach ($this->order->getItems() as $item) {
      if (!$item->hasPurchasedEntity()) {
        // Assume item with no purchased entity is a donation
        $visible = true;
      }
      else {
        $entity = $item->getPurchasedEntity();
        if ($entity->bundle() == 'virtual_item') {
          $visible = true;
        }
      }
    }
    return $visible;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form)
  {

    $pane_form['intro'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Using Gift Aid means we receive an extra 25 pence from the Inland Revenue for every pound you donate. This means £10 can be turned into £12.50, just so long as donations are made through Gift Aid. Would you like to use Gift Aid?'),
      '#prefix' => '<div class="giftaid-info"><p>',
      '#suffix' => '</p></div>',
    ];

    $pane_form['gift_aid_status'] = [
      '#type' => 'radios',
      '#title_display' => 'hidden',
      '#options' => [
        1 => $this->getGiftAidText(1),
        0 => $this->getGiftAidText(0),
      ],
      '#default_value' => 1,
    ];

    return $pane_form;

  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form)
  {
    $values = $form_state->getValue($pane_form['#parents']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form)
  {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setData('gift_aid', $values['gift_aid_status']);
  }

}

