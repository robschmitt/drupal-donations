<?php

namespace Drupal\lane_donations\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Donation entities.
 *
 * @ingroup lane_donations
 */
interface DonationInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Donation name.
   *
   * @return string
   *   Name of the Donation.
   */
  public function getName();

  /**
   * Sets the Donation name.
   *
   * @param string $name
   *   The Donation name.
   *
   * @return \Drupal\lane_donations\Entity\DonationInterface
   *   The called Donation entity.
   */
  public function setName($name);

  /**
   * Gets the Donation creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Donation.
   */
  public function getCreatedTime();

  /**
   * Sets the Donation creation timestamp.
   *
   * @param int $timestamp
   *   The Donation creation timestamp.
   *
   * @return \Drupal\lane_donations\Entity\DonationInterface
   *   The called Donation entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Donation published status indicator.
   *
   * Unpublished Donation are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Donation is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Donation.
   *
   * @param bool $published
   *   TRUE to set this Donation to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\lane_donations\Entity\DonationInterface
   *   The called Donation entity.
   */
  public function setPublished($published);

  public function reference();
  public function getAmount();
  public function isComplete();
  public function isPaid();
  public function isPurpleDonation();

}
