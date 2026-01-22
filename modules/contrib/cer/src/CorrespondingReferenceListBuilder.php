<?php

namespace Drupal\cer;

use Drupal\cer\Entity\CorrespondingReferenceInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * The list builder for Corresponding Reference entities.
 */
class CorrespondingReferenceListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   Returns header details for the List Builder.
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Label'),
      'id' => $this->t('Machine name'),
      'fields' => $this->t('Corresponding fields'),
      'add_direction' => $this->t('Append/Prepend'),
      'enabled' => $this->t('Enabled'),
    ];

    return $header + parent::buildHeader();
  }

  /**
   * Builds the row for the entity listing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is being referred.
   *
   * @return array
   *   The values for the table listing.
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\cer\Entity\CorrespondingReferenceInterface $entity */

    $row = [
      'label' => $entity->label(),
      'id' => $entity->id(),
      'fields' => $this->getCorrespondingFields($entity),
      'add_direction' => $entity->getAddDirection(),
      'enabled' => $entity->isEnabled() ? $this->t('Yes') : $this->t('No'),
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * Gets the fields for the referenced list builder.
   */
  protected function getCorrespondingFields(CorrespondingReferenceInterface $entity) {
    $fields = $entity->getCorrespondingFields();

    $items = [];

    foreach ($fields as $field) {
      $items[] = $field;
    }

    return \Drupal::theme()->render('item_list', ['items' => $items]);
  }

}
