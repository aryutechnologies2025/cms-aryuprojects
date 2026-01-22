<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\sprowt_ai\Entity\AiSystem;

/**
 * Defines the 'sprowt_ai_prompt' field type.
 *
 * @FieldType(
 *   id = "sprowt_ai_prompt",
 *   label = @Translation("AI Prompt"),
 *   category = @Translation("General"),
 *   default_widget = "sprowt_ai_prompt",
 *   default_formatter = "sprowt_ai_prompt_item_formatter",
 * )
 */
final class PromptItem extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultStorageSettings(): array
    {
        $settings = [
            'system' => null,
            'max_tokens' => 1024,
            'temperature' => 1.0
        ];
        return $settings + parent::defaultStorageSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array
    {

        $element = [];

        $element['max_tokens'] = [
            '#type' => 'number',
            '#title' => $this->t('Max tokens'),
            '#default_value' => $this->getSetting('max_tokens'),
            '#disabled' => $has_data,
            '#description' => 'The maximum number of tokens to be consumed by the api.'
                . ' Tokens represent a sequence of characters.'
                . ' The max number defined here determines the size of the generated content.'
                . ' For more info see <a href="https://lunary.ai/anthropic-tokenizer" target="_blank">here</a>.',
            '#min' => 4,
            '#max' => 4096,
            '#step' => 1,
            '#required' => true
        ];

        $element['temperature'] = [
            '#type' => 'number',
            '#title' => $this->t('Temperature'),
            '#default_value' => $this->getSetting('temperature'),
            '#disabled' => $has_data,
            '#description' => 'Amount of randomness injected into the response.'
                . ' Ranges from 0.0 to 1.0.'
                . ' Use temperature closer to 0.0 for analytical / multiple choice, and closer to 1.0 for creative and generative tasks.'
                . ' Note that even with temperature of 0.0, the results will not be fully deterministic.',
            '#min' => 0,
            '#max' => 1,
            '#step' => 0.1,
            '#required' => true
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public static function defaultFieldSettings(): array
    {
        $settings = [
            'insertField' => null
        ];
        return $settings + parent::defaultFieldSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function fieldSettingsForm(array $form, FormStateInterface $form_state): array
    {
        $entity = $this->getEntity();
        $fieldDefs = $entity->getFieldDefinitions();
        $fieldOpts = [];
        $supportedFieldTypes = \Drupal::config('sprowt_ai.settings')->get('supported_field_types') ?? [];

        /** @var FieldDefinitionInterface $fieldDef */
        foreach($fieldDefs as $fieldDef) {
            $type = $fieldDef->getType();
            if(!in_array($type, $supportedFieldTypes)) {
                continue;
            }
            $fieldName = $fieldDef->getName();
            $label = $fieldDef->getLabel();
            if(in_array($label, array_values($fieldOpts))) {
                $key = array_search($label, $fieldOpts);
                $fieldOpts[$key] = $label . " [{$key}]";
                $label .= " [{$fieldName}]";
            }
            $fieldOpts[$fieldName] = $label;
        }

        $element = [];

        $element['insertField'] = [
            '#type' => 'select',
            '#title' => $this->t('Insert field'),
            '#default_value' => $this->getSetting('insertField'),
            '#options' => $fieldOpts,
            '#description' => 'Field in which the generated content will be inserted.',
            '#empty_option' => '- Select -',
            '#empty_value' => '',
        ];
        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return match ($this->get('value')->getValue()) {
            NULL, '' => TRUE,
            default => FALSE,
        };
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array
    {

        // @DCG
        // See /core/lib/Drupal/Core/TypedData/Plugin/DataType directory for
        // available data types.
        $properties['value'] = DataDefinition::create('string')
            ->setLabel(t('Prompt value'))
            ->setRequired(TRUE);

        $properties['system'] = DataDefinition::create('string')
            ->setLabel(t('System user'))
            ->setRequired(false);

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints(): array
    {
        $constraints = parent::getConstraints();
//
//        $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
//
//        // @DCG Suppose our value must not be longer than 10 characters.
//        $options['value']['Length']['max'] = 10;
//
//        // @DCG
//        // See /core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint
//        // directory for available constraints.
//        $constraints[] = $constraint_manager->create('ComplexData', $options);
        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition): array
    {

        $columns = [
            'value' => [
                'type' => 'text',
                'not null' => FALSE,
                'description' => 'Prompt value',
                'size' => 'normal',
            ],
        ];

        $schema = [
            'columns' => $columns,
        ];

        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSampleValue(FieldDefinitionInterface $field_definition): array
    {
        $random = new Random();
        $values['value'] = $random->word(mt_rand(1, 50));
        return $values;
    }
}
