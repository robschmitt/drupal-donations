<?php

namespace Drupal\lane_donations\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\lane_donations\DonationHelper;
use Drupal\user\UserInterface;

/**
 * Defines the Donation entity.
 *
 * @ingroup lane_donations
 *
 * @ContentEntityType(
 *   id = "donation",
 *   label = @Translation("Donation"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\lane_donations\DonationListBuilder",
 *     "views_data" = "Drupal\lane_donations\Entity\DonationViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\lane_donations\Form\DonationForm",
 *       "add" = "Drupal\lane_donations\Form\DonationForm",
 *       "edit" = "Drupal\lane_donations\Form\DonationForm",
 *       "delete" = "Drupal\lane_donations\Form\DonationDeleteForm",
 *     },
 *     "access" = "Drupal\lane_donations\DonationAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\lane_donations\DonationHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "donation",
 *   admin_permission = "administer donation entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/donation/{donation}",
 *     "add-form" = "/admin/content/donation/add",
 *     "edit-form" = "/admin/content/donation/{donation}/edit",
 *     "delete-form" = "/admin/content/donation/{donation}/delete",
 *     "collection" = "/admin/content/donation",
 *   },
 *   field_ui_base_route = "donation.settings"
 * )
 */
class Donation extends ContentEntityBase implements DonationInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Donation entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Donation entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Donation is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  public function reference()
  {
    $donation_type = $this->field_donation_type->value;
    $prefix = 'REF-';
    if ($donation_type == 'single') {
      $prefix = 'REFWEBDON-';
    }
    elseif ($donation_type == 'recurring') {
      $prefix = 'REFWEBMEM-';
    }
    return $prefix . str_pad($this->id(), 5, '0', STR_PAD_LEFT);
  }

  public function getAmount()
  {
    $amount = 0;
    if ($this->field_donation_amount->value) {
      $amount = $this->field_donation_amount->value;
    }
    return $amount / 100;
  }

  public function singleDonationType()
  {
    return $this->field_single_donation_type->value;
  }

  public function isComplete()
  {
    return $this->field_donation_complete->value;
  }

  public function isPaid()
  {
    return $this->field_paid->value;
  }

  public function isPurpleDonation()
  {
    return $this->field_purple_user->value;
  }

}
