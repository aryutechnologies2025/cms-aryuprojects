<?php

namespace Drupal\zipcode_finder\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\link\LinkItemInterface;
use Drupal\zipcode_finder\ZipcodeFinderInterface;

/**
 * Defines the zipcode finder entity class.
 *
 * @ContentEntityType(
 *   id = "zipcode_finder",
 *   label = @Translation("Zipcode finder"),
 *   label_collection = @Translation("Zipcode finders"),
 *   label_singular = @Translation("zipcode finder"),
 *   label_plural = @Translation("zipcode finders"),
 *   label_count = @PluralTranslation(
 *     singular = "@count zipcode finder",
 *     plural = "@count zipcode finders",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\zipcode_finder\ZipcodeFinderListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\zipcode_finder\Form\ZipcodeFinderForm",
 *       "edit" = "Drupal\zipcode_finder\Form\ZipcodeFinderForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "zipcode_finder",
 *   admin_permission = "administer zipcode finder",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/structure/zipcode-finder",
 *     "add-form" = "/admin/structure/zipcode-finder/add",
 *     "canonical" = "/zipcode-finder/{zipcode_finder}",
 *     "edit-form" = "/admin/structure/zipcode-finder/{zipcode_finder}/edit",
 *     "delete-form" = "/admin/structure/zipcode-finder/{zipcode_finder}/delete",
 *   },
 * )
 */
class ZipcodeFinder extends ContentEntityBase implements ZipcodeFinderInterface
{

    use EntityChangedTrait;


    public static function findByZipcode($zipCode) {
        $database = \Drupal::database();
        $entityId = $database->query("
            SELECT entity_id
            FROM {zipcode_finder__zipcodes} zipcodes
            WHERE zipcodes.zipcodes_value = :zipCode
        ", [
            'zipCode' => $zipCode
        ])->fetchField();

        if(!empty($entityId)) {
            return static::load($entityId);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {

        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['label'] = BaseFieldDefinition::create('string')
            ->setRevisionable(TRUE)
            ->setLabel(t('Label'))
            ->setRequired(TRUE)
            ->setSetting('max_length', 255)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => -5,
            ])
            ->setDisplayConfigurable('form', TRUE);

        $fields['status'] = BaseFieldDefinition::create('boolean')
            ->setRevisionable(TRUE)
            ->setLabel(t('Status'))
            ->setDefaultValue(TRUE)
            ->setSetting('on_label', 'Enabled')
            ->setDisplayOptions('form', [
                'type' => 'boolean_checkbox',
                'settings' => [
                    'display_label' => FALSE,
                ],
                'weight' => 0,
            ])
            ->setDisplayConfigurable('form', TRUE);

        $fields['description'] = BaseFieldDefinition::create('text_long')
            ->setRevisionable(TRUE)
            ->setLabel(t('Description'))
            ->setDisplayOptions('form', [
                'type' => 'text_textarea',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('form', TRUE);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the zipcode finder was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the zipcode finder was last edited.'));

        $fields['link'] = BaseFieldDefinition::create('link')
            ->setLabel(t('Link'))
            ->setDescription(t('The location this zipcode finder goes to.'))
            ->setRevisionable(TRUE)
            ->setRequired(TRUE)
            ->setSettings([
                'link_type' => LinkItemInterface::LINK_GENERIC,
                'title' => DRUPAL_DISABLED,
            ])
            ->setDisplayOptions('form', [
                'type' => 'link_default',
                'weight' => 10,
            ]);

        $fields['zipcodes'] = BaseFieldDefinition::create('string')
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
            ->setLabel(t('Zip (postal) codes'))
            ->setDescription(t('The zipcodes to use with this finder'))
            ->setRevisionable(TRUE)
            ->setRequired(TRUE)
            ->setDisplayOptions('form', [
                'type' => 'zipcodes_widget',
                'weight' => 11,
            ]);

        return $fields;
    }

}
