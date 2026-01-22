<?php

namespace Drupal\tag_text_field\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'html_tag_text_field_type' field type.
 *
 * @FieldType(
 *   id = "html_tag_text_field_type",
 *   label = @Translation("Text (plain with html tag option)"),
 *   description = @Translation("Textfield with an html tag selector"),
 *   category = @Translation("Text"),
 *   default_widget = "html_tag_text_field_widget",
 *   default_formatter = "html_tag_text_field_formatter"
 * )
 */
class HtmlTagTextFieldType extends FieldItemBase
{
    public static $possibleTags = [
        'h1' => 'H1',
        'h2' => 'H2',
        'h3' => 'H3',
        'h4' => 'H4',
        'h5' => 'H5',
        'h6' => 'H6',
        'div' => 'Div'
    ];

    public static function tagOptionsArrayToString(array $array)  {
        $strArray = [];
        foreach($array as $key => $val) {
            $strArray[] = trim($key) . '|' . trim($val);
        }
        return implode("\n", $strArray);
    }

    public static function tagOptionsStringToArray($str) {
        $array = explode("\n", $str);
        $return = [];
        foreach($array as $strVal) {
            if(!empty($strVal) && strpos($strVal, '|') !== false) {
                $parts = explode('|', $strVal);
                $key = array_shift($parts);
                $val = implode('|', $parts);
                $return[trim($key)] = trim($val);
            }
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public static function defaultStorageSettings()
    {
        return [
                'max_length' => 255,
                'is_ascii' => FALSE,
                'case_sensitive' => FALSE,
            ] + parent::defaultStorageSettings();
    }

    /**
     * {@inheritdoc}
     */
    public static function defaultFieldSettings() {
        return [
            'defaultTag' => 'div',
            'tagList' => static::tagOptionsArrayToString(static::$possibleTags)
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        // Prevent early t() calls by using the TranslatableMarkup.
        $properties['value'] = DataDefinition::create('string')
            ->setLabel(new TranslatableMarkup('Text value'))
            ->setSetting('case_sensitive', $field_definition->getSetting('case_sensitive'))
            ->setRequired(TRUE);
        $properties['tag'] = DataDefinition::create('string')
            ->setLabel(new TranslatableMarkup('Html tag'))
            ->setRequired(true);

        return $properties;
    }

    public function fieldSettingsForm(array $form, FormStateInterface $form_state)
    {
        $defaultValues = static::defaultFieldSettings();
        $defaultValue = function($key) use ($defaultValues) {
            if(!empty($this->getSetting($key))) {
                return $this->getSetting($key);
            }
            return $defaultValues[$key] ?? null;
        };

        $form = parent::fieldSettingsForm($form, $form_state);
        $form['defaultTag'] = [
            '#type' => 'textfield',
            '#title' => t('Default tag'),
            '#default_value' => $defaultValue('defaultTag'),
            '#required' => true,
            '#description' => t('The default html tag used for this field.'),
            '#attributes' => [
                'class' => ['html-tag-text-field-tag-default-value']
            ]
        ];
        $form['tagList'] = [
            '#type' => 'textarea',
            '#title' => t('Tag options'),
            '#default_value' => $defaultValue('tagList'),
            '#required' => true,
            '#description' => t('List of options for the tag field in the format key|val. The key is the actual tag and the val is the display name of the tag in the tag option select.'),
            '#attributes' => [
                'class' => ['html-tag-text-field-tag-list']
            ]
        ];

        $form['isTagTextField'] = [
            '#type' => 'value',
            '#value' => true
        ];

        $form['#attached']['library'][] = 'tag_text_field/settings';

        return $form;

    }

    public static function validateSettingsForm(&$form, FormStateInterface &$formState) {
        $settings = $formState->getValue('settings');
        $tagList = $settings['tagList'];
        $tagListArray = static::tagOptionsStringToArray($tagList);
        if(empty($tagListArray)) {
            $formState->setError($form['settings']['tagList'],
                t('Tag list is invalid')
            );
        }
        else {
            $settings['tagList'] = static::tagOptionsArrayToString($tagListArray);
            $formState->setValue('settings', $settings);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        $schema = [
            'columns' => [
                'value' => [
                    'type' => $field_definition->getSetting('is_ascii') === TRUE ? 'varchar_ascii' : 'varchar',
                    'length' => (int)$field_definition->getSetting('max_length'),
                    'binary' => $field_definition->getSetting('case_sensitive'),
                ],
                'tag' => [
                    'type' => 'varchar',
                    'length' => 255
                ]
            ],
        ];

        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        $constraints = parent::getConstraints();

        if ($max_length = $this->getSetting('max_length')) {
            $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
            $constraints[] = $constraint_manager->create('ComplexData', [
                'value' => [
                    'Length' => [
                        'max' => $max_length,
                        'maxMessage' => t('%name: may not be longer than @max characters.', [
                            '%name' => $this->getFieldDefinition()->getLabel(),
                            '@max' => $max_length
                        ]),
                    ],
                ],
            ]);
        }

        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSampleValue(FieldDefinitionInterface $field_definition)
    {
        $randomTagKey = rand(0, (count(static::$possibleTags) - 1));
        $randomTagKeys = array_keys(static::$possibleTags);
        $randomTag = $randomTagKeys[$randomTagKey];
        $random = new Random();
        $values['value'] = $random->word(mt_rand(1, $field_definition->getSetting('max_length')));
        $values['tag'] = $randomTag;
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data)
    {
        $elements = [];

        $elements['max_length'] = [
            '#type' => 'number',
            '#title' => t('Maximum length'),
            '#default_value' => $this->getSetting('max_length'),
            '#required' => TRUE,
            '#description' => t('The maximum length of the field in characters.'),
            '#min' => 1,
            '#disabled' => $has_data,
        ];

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $value = $this->get('value')->getValue();
        return $value === NULL || $value === '';
    }

}
