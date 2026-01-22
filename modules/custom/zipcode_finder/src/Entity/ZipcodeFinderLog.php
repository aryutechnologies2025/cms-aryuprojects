<?php

namespace Drupal\zipcode_finder\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\zipcode_finder\Plugin\DataType\ZipcodeFinderZipcodeEntityProperty;
use Drupal\zipcode_finder\Plugin\Field\FieldType\ZipcodeFinderComputedItemList;
use Drupal\zipcode_finder\ZipcodeFinderLogInterface;

/**
 * Defines the zipcode finder log entity class.
 *
 * @ContentEntityType(
 *   id = "zipcode_finder_log",
 *   label = @Translation("Zipcode finder log"),
 *   label_collection = @Translation("Zipcode finder logs"),
 *   label_singular = @Translation("zipcode finder log"),
 *   label_plural = @Translation("zipcode finder logs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count zipcode finder logs",
 *     plural = "@count zipcode finder logs",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\zipcode_finder\ZipcodeFinderLogListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\zipcode_finder\Routing\ZipcodeFinderLogHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "zipcode_finder_log",
 *   admin_permission = "administer zipcode finder log",
 *   entity_keys = {
 *     "id" = "zipcode",
 *     "label" = "zipcode",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/zipcode-finder-log",
 *     "canonical" = "/admin/structure/zipcode-finder/zipcode-finder-log/{zipcode_finder_log}",
 *     "delete-form" = "/admin/structure/zipcode-finder/zipcode-finder-log/{zipcode_finder_log}/delete",
 *   },
 * )
 */
class ZipcodeFinderLog extends ContentEntityBase implements ZipcodeFinderLogInterface
{

    use EntityChangedTrait;


    public function getZipcode()
    {
        return $this->get('zipcode')->value;
    }

    public function getCount()
    {
        return $this->get('count')->value;
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {

        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['zipcode'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Zipcode'))
            ->setRequired(true)
            ->setDescription(t('The zipcode logged'))
            ->setSetting('length', 255);

        $fields['count'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Count'))
            ->setDescription(t('The log count'))
            ->setSetting('size', 'big');

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Created'))
            ->setDescription(t('The date the zipcode was first logged'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The date the zipcode was last logged'));

        $fields['zipcode_finder'] = BaseFieldDefinition::create('zipcode_finder_computed_item')
            ->setTargetEntityTypeId('zipcode_finder')
            ->setLabel('Referenced Zipcode finder')
            ->setComputed(true)
            ->setRequired(false)
            ->setClass(ZipcodeFinderComputedItemList::class);

        return $fields;
    }

}
