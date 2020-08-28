<?php

namespace Drupal\lane_donations;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Render\Element\Date;

/**
 * Defines a class to build a listing of SSPCA Donation entities.
 *
 * @ingroup lane_donations
 */
class DonationListBuilder extends EntityListBuilder {

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort('created', 'desc');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('SSPCA Donation ID');
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    $header['description'] = $this->t('Description');
    $header['amount'] = $this->t('Amount');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\lane_donations\Entity\SspcaDonation */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.donation.edit_form',
      ['donation' => $entity->id()]
    );
    $row['type'] = $entity->field_donation_type->value;
    $row['description'] = substr($entity->field_description->value, 0, 25);
    if (strlen($entity->field_description->value) > 25) {
      $row['description'] .= '...';
    }
    if ($entity->field_donation_amount->value) {
      $row['amount'] = '£' . number_format($entity->field_donation_amount->value / 100, 2);
    }
    else {
      $row['amount'] = '–';
    }
    $row['created'] = \Drupal::service('date.formatter')->format($entity->getCreatedTime(), 'short');
    return $row + parent::buildRow($entity);
  }

}
