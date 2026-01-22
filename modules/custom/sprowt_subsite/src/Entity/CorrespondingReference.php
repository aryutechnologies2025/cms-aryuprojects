<?php

namespace Drupal\sprowt_subsite\Entity;

use Drupal\cer\CorrespondingReferenceOperations;
use Drupal\cer\Entity\CorrespondingReference as BaseEntity;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItem;
use Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItemList;

class CorrespondingReference extends BaseEntity
{
    public function synchronizeCorrespondingField(FieldableEntityInterface $entity, FieldableEntityInterface $correspondingEntity, $correspondingFieldName, $operation = NULL)
    {
        if (is_null($operation)) {
            $operation = CorrespondingReferenceOperations::ADD;
        }

        if (!$correspondingEntity->hasField($correspondingFieldName)) {
            return;
        }

        $field = $correspondingEntity->get($correspondingFieldName);
        if(!$field instanceof SubsiteReferenceItemList) {
            return parent::synchronizeCorrespondingField($entity, $correspondingEntity, $correspondingFieldName, $operation);
        }

        $target_type = 'node';
        if ($entity->getEntityTypeId() != $target_type) {
            return;
        }

        $target_bundles = ['subsite'];
        if (!empty($target_bundles) && !in_array($entity->bundle(), $target_bundles)) {
            return;
        }

        $values = $field->getValue();

        $index = NULL;

        foreach ($values as $idx => $value) {
            if ($value['target'] == $entity->uuid()) {
                if ($operation == CorrespondingReferenceOperations::ADD) {
                    return;
                }

                $index = $idx;
            }
        }

        $set = FALSE;

        switch ($operation) {
            case CorrespondingReferenceOperations::REMOVE:
                if (!is_null($index)) {
                    unset($values[$index]);
                    $set = TRUE;
                }
                break;
            case CorrespondingReferenceOperations::ADD:
                $synced_values = ['target' => $entity->uuid()];
                switch ($this->getAddDirection()) {
                    default:
                        $values[] = $synced_values;
                        $set = TRUE;
                        break;
                }
                break;
        }

        if ($set) {
            $field->setValue($values);
            $correspondingEntity->save();
        }
    }

    protected function calculateDifferences(FieldableEntityInterface $entity, $fieldName, $deleted = FALSE)
    {
        /** @var FieldableEntityInterface $original */
        $original = isset($entity->original) ? $entity->original : NULL;

        $differences = [
            CorrespondingReferenceOperations::ADD => [],
            CorrespondingReferenceOperations::REMOVE => [],
        ];

        if (!$entity->hasField($fieldName)) {
            return $differences;
        }

        $entityField = $entity->get($fieldName);
        if(!$entityField instanceof SubsiteReferenceItemList) {
            return parent::calculateDifferences($entity, $fieldName, $deleted);
        }

        // If entity is deleted, remove references to it.
        if ($deleted) {
            /** @var FieldItemInterface $fieldItem */
            foreach ($entityField as $fieldItem) {
                if(isset($fieldItem->entity) && $this->isValid($fieldItem->entity)) {
                    $differences[CorrespondingReferenceOperations::REMOVE][] = $fieldItem->entity;
                }
            }
            return $differences;
        }

        if (empty($original)) {
            foreach ($entityField as $fieldItem) {
                if(isset($fieldItem->entity) && $this->isValid($fieldItem->entity)) {
                    $differences[CorrespondingReferenceOperations::ADD][] = $fieldItem->entity;
                }
            }

            return $differences;
        }

        $originalField = $original->get($fieldName);

        foreach ($entityField as $fieldItem) {
            if (!$this->entityHasValue($original, $fieldName, $fieldItem->target)) {
                if(isset($fieldItem->entity) && $this->isValid($fieldItem->entity)) {
                    $differences[CorrespondingReferenceOperations::ADD][] = $fieldItem->entity;
                }
            }
        }

        foreach ($originalField as $fieldItem) {
            if (!$this->entityHasValue($entity, $fieldName, $fieldItem->target)) {
                if(isset($fieldItem->entity) && $this->isValid($fieldItem->entity)) {
                    $differences[CorrespondingReferenceOperations::REMOVE][] = $fieldItem->entity;
                }
            }
        }

        return $differences;
    }

    public function entityHasValue(FieldableEntityInterface $entity, $fieldName, $uuid)
    {
        if (!$entity->hasField($fieldName)) {
            return FALSE;
        }
        $list = $entity->get($fieldName);
        if(!$list instanceof SubsiteReferenceItemList) {
            return parent::entityHasValue($entity, $fieldName, $uuid);
        }

        foreach ($entity->get($fieldName) as $fieldItem) {
            if ($fieldItem->target == $uuid) {
                return TRUE;
            }
        }

        return FALSE;
    }

}
