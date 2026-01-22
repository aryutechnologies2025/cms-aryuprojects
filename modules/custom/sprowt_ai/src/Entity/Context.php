<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\sprowt_ai\ContextInterface;

/**
 * Defines the context entity class.
 *
 * @ContentEntityType(
 *   id = "sprowt_ai_context",
 *   label = @Translation("Context"),
 *   label_collection = @Translation("Contexts"),
 *   label_singular = @Translation("context"),
 *   label_plural = @Translation("contexts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count contexts",
 *     plural = "@count contexts",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\sprowt_ai\ContextListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\sprowt_ai\Form\ContextForm",
 *       "edit" = "Drupal\sprowt_ai\Form\ContextForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\sprowt_ai\Routing\ContextHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "sprowt_ai_context",
 *   revision_table = "sprowt_ai_context_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer sprowt_ai_context",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *     "revision_user" = "revision_uid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/sprowt-ai/context",
 *     "add-form" = "/admin/config/services/sprowt-ai/context/add",
 *     "canonical" = "/admin/config/services/sprowt-ai/context/{sprowt_ai_context}",
 *     "edit-form" = "/admin/config/services/sprowt-ai/context/{sprowt_ai_context}",
 *     "delete-form" = "/admin/config/services/sprowt-ai/context/{sprowt_ai_context}/delete",
 *     "delete-multiple-form" = "/admin/config/services/sprowt-ai/delete-multiple",
 *   },
 * )
 */
class Context extends RevisionableContentEntityBase implements ContextInterface
{

    use EntityChangedTrait;

    public static $vocab = 'ai_context_tags';

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
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
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'label' => 'hidden',
                'type' => 'string',
                'weight' => -5,
            ])
            ->setDisplayConfigurable('view', TRUE);

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
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'type' => 'boolean',
                'label' => 'above',
                'weight' => 0,
                'settings' => [
                    'format' => 'enabled-disabled',
                ],
            ])
            ->setDisplayConfigurable('view', TRUE);

        $fields['content'] = BaseFieldDefinition::create('text_long')
            ->setRevisionable(TRUE)
            ->setLabel(t('Content'))
            ->setDisplayOptions('form', [
                'type' => 'text_textarea',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'type' => 'text_default',
                'label' => 'above',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('view', TRUE);

        $fields['tags'] = BaseFieldDefinition::create('entity_reference')
            ->setRevisionable(true)
            ->setLabel(t('Tags'))
            ->setDescription('Tags that are associated with this context.')
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('form', [
                'type' => 'options_select',
                'label' => 'above',
                'weight' => 10,
            ])
            ->setSetting('target_type', 'taxonomy_term')
            ->setSetting('handler_settings', [
                'target_bundles' => [
                    static::$vocab
                ]
            ]);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the context was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the context was last edited.'));

        return $fields;
    }

    public function getContent()
    {
        return $this->get('content')->value;
    }

}
