<?php


namespace Drupal\sprowt_install\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
Use Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem as BaseFieldType;

/**
 * extends Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem
 * Class LayoutSectionItem
 * @package Drupal\sprowt_install\Plugin\FieldType
 */
class LayoutSectionItem extends BaseFieldType
{

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition) {
        $schema = parent::schema($field_definition);
        $schema['columns']['section']['size'] = 'big';

        return $schema;
    }

}
