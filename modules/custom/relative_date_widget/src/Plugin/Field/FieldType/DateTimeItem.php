<?php


namespace Drupal\relative_date_widget\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem as BaseDateTimeItem;

class DateTimeItem extends BaseDateTimeItem
{
    public static function schema(FieldStorageDefinitionInterface $field_definition)
    {
        $schema = parent::schema($field_definition);
        $schema['columns']['value']['length'] = 1000;
        $schema['indexes'] = [];
        return $schema;
    }

    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition)
    {
        $properties = parent::propertyDefinitions($field_definition);
        $properties['value'] = DataDefinition::create('string')
            ->setLabel(t('Date value'))
            ->setRequired(TRUE);
        $properties['date'] = DataDefinition::create('any')
            ->setLabel(t('Computed date'))
            ->setDescription(t('The computed DateTime object.'))
            ->setComputed(TRUE)
            ->setClass('\Drupal\relative_date_widget\DateTimeComputed')
            ->setSetting('date source', 'value');

        return $properties;
    }

    public function isEmpty()
    {
        return parent::isEmpty();
    }
}
