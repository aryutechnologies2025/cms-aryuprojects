<?php

namespace Drupal\sprowt_subsite\Form;

use Drupal\cer\Form\CorrespondingReferenceForm as BaseForm;

class CorrespondingReferenceForm extends BaseForm
{
    protected function getReferenceFieldMap()
    {
        $refMap = $this->fieldManager->getFieldMapByFieldType('entity_reference');
        $subsiteMap = $this->fieldManager->getFieldMapByFieldType('sprowt_subsite_reference');
        $return = [];
        foreach ($refMap as $entityType => $entityTypeFields) {
            $return[$entityType] = $entityTypeFields ?? [];
            $subsiteEntityTypeFields = $subsiteMap[$entityType] ?? [];
            foreach ($subsiteEntityTypeFields as $fieldName => $field) {
                $return[$entityType][$fieldName] = $field;
            }
        }

        return $return;
    }

    protected function getFieldOptions()
    {
        $opts = parent::getFieldOptions();
        asort($opts);
        return $opts;
    }
}
