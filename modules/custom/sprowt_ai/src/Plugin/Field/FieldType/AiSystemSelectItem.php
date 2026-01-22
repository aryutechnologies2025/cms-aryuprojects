<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

#[FieldType(
    id: "sprowt_ai_ai_system_select",
    label: new TranslatableMarkup("Ai System Select"),
    description: new TranslatableMarkup("Reference field for AI System Users"),
    category: "reference",
    default_widget: "options_select",
    default_formatter: "sprowt_ai_system_user_formatter",
    list_class: EntityReferenceFieldItemList::class,
)]
final class AiSystemSelectItem extends EntityReferenceItem
{

    /**
     * {@inheritdoc}
     */
    public static function defaultStorageSettings() {
        return [
                'target_type' => 'ai_system',
            ] + parent::defaultStorageSettings();
    }


    public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
        $element = parent::storageSettingsForm($form, $form_state, $has_data);
        $element['target_type'] = [
            '#type' => 'value',
            '#value' => $this->getSetting('target_type')
        ];

        return $element;
    }

}
