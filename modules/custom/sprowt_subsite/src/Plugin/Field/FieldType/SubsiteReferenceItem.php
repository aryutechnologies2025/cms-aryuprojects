<?php

namespace Drupal\sprowt_subsite\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\node\Entity\Node;
use Drupal\sprowt_subsite\Plugin\DataType\SubsiteEntityProperty;
use Drupal\sprowt_subsite\Plugin\DataType\SubsiteTitleProperty;

/**
 * Defines the 'sprowt_subsite_reference' field type.
 *
 * @FieldType(
 *     id = "sprowt_subsite_reference",
 *     label = @Translation("Subsite Reference"),
 *     category = @Translation("Reference"),
 *     default_widget = "sprowt_subsite_reference_selector",
 *     default_formatter = "sprowt_subsite_reference_view",
 *     list_class = "Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItemList"
 * )
 *
 * @DCG
 * If you are implementing a single value field type you may want to inherit
 * this class form some of the field type classes provided by Drupal core.
 * Check out /core/lib/Drupal/Core/Field/Plugin/Field/FieldType directory for a
 * list of available field type implementations.
 */
class SubsiteReferenceItem extends FieldItemBase
{

    /**
     * {@inheritdoc}
     */
    public static function defaultStorageSettings()
    {
        $settings = [];
        return $settings + parent::defaultStorageSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data)
    {

        $element = [];
//        $element['foo'] = [
//            '#type' => 'textfield',
//            '#title' => $this->t('Foo'),
//            '#default_value' => $this->getSetting('foo'),
//            '#disabled' => $has_data,
//        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public static function defaultFieldSettings()
    {
        $settings = [];
        return $settings + parent::defaultFieldSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function fieldSettingsForm(array $form, FormStateInterface $form_state)
    {

        $element = [];
//        $element['bar'] = [
//            '#type' => 'textfield',
//            '#title' => $this->t('Bar'),
//            '#default_value' => $this->getSetting('bar'),
//        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        $value = $this->get('target')->getValue();
        return $value === NULL || $value === '';
    }

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {

        // @DCG
        // See /core/lib/Drupal/Core/TypedData/Plugin/DataType directory for
        // available data types.
        $properties['target'] = DataDefinition::create('string')
            ->setLabel(t('Subsite uuid or _main for main site'))
            ->setRequired(TRUE);

        $properties['target_id'] = DataReferenceTargetDefinition::create('integer')
            ->setLabel(new TranslatableMarkup('@label ID', ['@label' => 'Node']))
            ->setSetting('unsigned', TRUE);

        $properties['entity'] = DataDefinition::create('entity')
            ->setLabel('Referenced Entity')
            ->setComputed(true)
            ->setClass(SubsiteEntityProperty::class);

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
            'target' => [
                'type' => 'varchar',
                'not null' => FALSE,
                'description' => 'Subsite uuid or _main for main site',
                'length' => 255,
            ],
            'target_id' => [
                'description' => 'The ID of the target entity.',
                'type' => 'int',
                'unsigned' => TRUE,
            ],
        ];

        $schema = [
            'columns' => $columns,
            'indexes' => [
                'target_id' => ['target_id'],
                'target' => ['target']
            ],
            // @DCG Add indexes here if necessary.
        ];

        return $schema;
    }

    public function preSave()
    {
        if($this->target != '_main') {
            if($this->entity instanceof Node) {
                $this->target_id = $this->entity->id();
            }
        }
        parent::preSave();
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSampleValue(FieldDefinitionInterface $field_definition)
    {
        $random = new Random();
        $values['target'] = $random->word(mt_rand(1, 50));
        return $values;
    }

}
