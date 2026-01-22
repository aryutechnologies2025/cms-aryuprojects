<?php

namespace Drupal\zipcode_finder\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;

/**
 * Defines the 'zipcode_finder_zipcode_finder_form' field type.
 *
 * @FieldType(
 *   id = "zipcode_finder_zipcode_finder_form",
 *   label = @Translation("Zipcode finder form"),
 *   category = @Translation("General"),
 *   default_widget = "zipcode_finder_zipcode_finder_form_widget",
 *   default_formatter = "zipcode_finder_zipcode_finder_form_formatter"
 * )
 *
 * @DCG
 * If you are implementing a single value field type you may want to inherit
 * this class form some of the field type classes provided by Drupal core.
 * Check out /core/lib/Drupal/Core/Field/Plugin/Field/FieldType directory for a
 * list of available field type implementations.
 */
class ZipcodeFinderFormItem extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {

        // @DCG
        // See /core/lib/Drupal/Core/TypedData/Plugin/DataType directory for
        // available data types.
        $properties['placeholder'] = DataDefinition::create('string')
            ->setLabel(t('Field placeholder text'))
            ->setRequired(true);

        $properties['submit_text'] = DataDefinition::create('string')
            ->setLabel(t('Submit button text'))
            ->setRequired(true);

        $properties['failure_uri'] = DataDefinition::create('uri')
            ->setLabel(t('Destination when no finder can be matched'))
            ->setRequired(true);

        return $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraints()
    {
        $constraints = parent::getConstraints();
        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {

        $columns = [
            'placeholder' => [
                'type' => 'varchar',
                'not null' => FALSE,
                'description' => 'Field placeholder text',
                'length' => 255,
            ],
            'submit_text' => [
                'type' => 'varchar',
                'not null' => FALSE,
                'description' => 'Submit button text',
                'length' => 255,
            ],
            'failure_uri' => [
                'type' => 'varchar',
                'not null' => FALSE,
                'description' => 'Destination when no finder can be matched',
                'length' => 2048,
            ],
        ];

        $schema = [
            'columns' => $columns,
            // @DCG Add indexes here if necessary.
        ];

        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSampleValue(FieldDefinitionInterface $field_definition)
    {
        $random = new Random();
        $values = [];
        $values['placeholder'] = $random->word(mt_rand(1, 50));
        $values['submit_text'] = $random->name(mt_rand(1, 64));;
        $values['failure_uri'] = 'base:' . $random->name(mt_rand(1, 64));
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty() {
        $value = $this->get('failure_uri')->getValue();
        return $value === NULL || $value === '';
    }
    /**
     * {@inheritdoc}
     */
    public function getUrl() {
        return Url::fromUri($this->failure_uri);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($values, $notify = TRUE) {
        // Treat the values as property value of the main property, if no array is
        // given.
        if (isset($values) && !is_array($values)) {
            $values = [
                'failure_uri' => $values,
                'placeholder' => 'Enter your zip code',
                'submit_text' => 'Go'
            ];
        }
        parent::setValue($values, $notify);
    }

}
