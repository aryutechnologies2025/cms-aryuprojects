<?php

namespace Drupal\lawnbot\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\lawnbot\ServicebotInterface;

/**
 * Defines the servicebot entity class.
 *
 * @ContentEntityType(
 *   id = "servicebot",
 *   label = @Translation("ServiceBot"),
 *   label_collection = @Translation("servicebots"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\lawnbot\Form\ServicebotForm",
 *       "edit" = "Drupal\lawnbot\Form\ServicebotForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "servicebot",
 *   revision_table = "servicebot_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "access servicebot overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *     "revision_user" = "revision_user"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/servicebot/add",
 *     "canonical" = "/servicebot/{servicebot}",
 *     "edit-form" = "/admin/content/servicebot/{servicebot}/edit",
 *     "delete-form" = "/admin/content/servicebot/{servicebot}/delete"
 *   },
 * )
 */
class Servicebot extends RevisionableContentEntityBase implements ServicebotInterface
{

    use EntityChangedTrait;

    /**
     * {@inheritdoc}
     */
    public function getTitle()
    {
        return $this->get('title')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setTitle($title)
    {
        $this->set('title', $title);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return (bool)$this->get('status')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($status)
    {
        $this->set('status', $status);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedTime()
    {
        return $this->get('created')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedTime($timestamp)
    {
        $this->set('created', $timestamp);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {

        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['title'] = BaseFieldDefinition::create('string')
            ->setRevisionable(TRUE)
            ->setLabel(t('Label'))
            ->setDescription(t('The label for this servicebot.'))
            ->setRequired(TRUE)
            ->setSetting('max_length', 255)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => -5,
            ])
            ->setDisplayOptions('view', [
                'label' => 'hidden',
                'type' => 'string',
                'weight' => -5,
            ]);

        $fields['status'] = BaseFieldDefinition::create('boolean')
            ->setRevisionable(TRUE)
            ->setLabel(t('Enabled'))
            ->setDescription(t('A boolean indicating whether this servicebot is enabled.'))
            ->setDefaultValue(TRUE)
            ->setSetting('on_label', 'Enabled')
            ->setDisplayOptions('form', [
                'type' => 'boolean_checkbox',
                'settings' => [
                    'display_label' => FALSE,
                ],
                'weight' => 0,
            ])
            ->setDisplayOptions('view', [
                'type' => 'boolean',
                'label' => 'above',
                'weight' => 0,
                'settings' => [
                    'format' => 'enabled-disabled',
                ],
            ]);

        $fields['customer_id'] = BaseFieldDefinition::create('string')
            ->setRevisionable(TRUE)
            ->setLabel(t('Customer Id'))
            ->setDescription(t('Customer ID from ServiceBot for this bot'))
            ->setRequired(TRUE)
            ->setSetting('max_length', 255)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'hidden',
                'type' => 'string',
                'weight' => 1,
            ]);

        $fields['bot_id'] = BaseFieldDefinition::create('string')
            ->setRevisionable(TRUE)
            ->setLabel(t('Bot Id'))
            ->setDescription(t('Bot ID from ServiceBot for this bot'))
            ->setRequired(TRUE)
            ->setSetting('max_length', 255)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'hidden',
                'type' => 'string',
                'weight' => 1,
            ]);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the servicebot was created.'))
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'timestamp',
                'weight' => 20,
            ])
            ->setDisplayOptions('form', [
                'type' => 'datetime_timestamp',
                'weight' => 20,
            ]);

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the servicebot was last edited.'));

        return $fields;
    }

    public function getCustomerId() {
        return $this->get('customer_id')->value;
    }

    public function getBotId() {
        return $this->get('bot_id')->value;
    }
}
