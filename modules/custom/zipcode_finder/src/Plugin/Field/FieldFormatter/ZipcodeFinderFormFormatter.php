<?php

namespace Drupal\zipcode_finder\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormState;
use Drupal\zipcode_finder\Form\FindZipcodeForm;

/**
 * Plugin implementation of the 'Zipcode finder form formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "zipcode_finder_zipcode_finder_form_formatter",
 *   label = @Translation("Zipcode finder form formatter"),
 *   field_types = {
 *     "zipcode_finder_zipcode_finder_form"
 *   }
 * )
 */
class ZipcodeFinderFormFormatter extends FormatterBase
{

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $element = [];
        $formBuilder = \Drupal::formBuilder();
        $field_name = $this->fieldDefinition->getName();
        $entity = $items->getEntity();
        $entity_type = $entity->getEntityTypeId();
        // Create an ID suffix from the parents to make sure each widget is unique.
        $prefixArray = [
            $entity_type,
            $entity->id(),
            $field_name
        ];
        foreach ($items as $delta => $item) {
            $settings = [
                'triggerName' => implode('--', array_merge($prefixArray, [
                    $delta,
                    'form-trigger'
                ])),
                'placeholder' => $item->placeholder,
                'submitText' => $item->submit_text,
                'failureUri' => $item->failure_uri
            ];
            $form = $formBuilder->getForm(FindZipcodeForm::class, $settings);

            $element[$delta] = [
                'form' => $form,
            ];
        }

        return $element;
    }

}
