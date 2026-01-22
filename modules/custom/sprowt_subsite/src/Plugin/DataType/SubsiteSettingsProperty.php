<?php

namespace Drupal\sprowt_subsite\Plugin\DataType;


use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\sprowt_subsite\SettingsManager;

class SubsiteSettingsProperty extends FieldItemList
{

    protected $settings;

    /**
     * {@inheritdoc}
     */
    public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
        // The properties are dynamic and can not be defined statically.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function schema(FieldStorageDefinitionInterface $field_definition) {
        return [];
    }

    public function getValue()
    {
        if(isset($this->settings)) {
            return $this->settings;
        }
        /** @var SettingsManager $manager */
        $manager = \Drupal::service('sprowt_subsite.settings_manager');

        /** @var Node $parent */
        $parent = $this->getEntity();
        $pid = $parent->id();
        if(empty($pid)) {
            $this->settings = [];
            return $this->settings;
        }

        $this->settings = $manager->getSubsiteSettings($parent);
        return $this->settings;
    }

    public function setValue($values, $notify = TRUE)
    {
        $this->settings = $values;
        // Notify the parent of any changes.
        if ($notify && isset($this->parent)) {
            $this->parent->onChange($this->name);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name) {
        return $this->settings[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function __set($name, $value) {
        if (isset($value)) {
            $this->settings[$name] = $value;
        }
        else {
            unset($this->settings[$name]);
        }
    }

}
