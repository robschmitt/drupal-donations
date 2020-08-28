<?php

namespace Drupal\lane_donations;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the SSPCA Donation entity.
 *
 * @see \Drupal\lane_donations\Entity\SspcaDonation.
 */
class SspcaDonationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\lane_donations\Entity\SspcaDonationInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished sspca donation entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published sspca donation entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit sspca donation entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete sspca donation entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add sspca donation entities');
  }

}
