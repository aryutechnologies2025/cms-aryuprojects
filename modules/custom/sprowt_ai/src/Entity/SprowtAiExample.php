<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Renderer;
use Drupal\sprowt_ai\SprowtAiExampleInterface;

/**
 * Defines the example entity class.
 *
 * @ContentEntityType(
 *   id = "sprowt_ai_example",
 *   label = @Translation("Example"),
 *   label_collection = @Translation("Examples"),
 *   label_singular = @Translation("example"),
 *   label_plural = @Translation("examples"),
 *   label_count = @PluralTranslation(
 *     singular = "@count examples",
 *     plural = "@count examples",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\sprowt_ai\SprowtAiExampleListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\sprowt_ai\Form\SprowtAiExampleForm",
 *       "edit" = "Drupal\sprowt_ai\Form\SprowtAiExampleForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\sprowt_ai\Routing\SprowtAiExampleHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "sprowt_ai_example",
 *   revision_table = "sprowt_ai_example_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer sprowt_ai_example",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/sprowt-ai/example",
 *     "add-form" = "/admin/config/services/sprowt-ai/example/add",
 *     "canonical" = "/admin/config/services/sprowt-ai/example/{sprowt_ai_example}",
 *     "edit-form" = "/admin/config/services/sprowt-ai/example/{sprowt_ai_example}",
 *     "delete-form" = "/admin/config/services/sprowt-ai/example/{sprowt_ai_example}/delete",
 *     "delete-multiple-form" = "/admin/config/services/sprowt-ai/example/delete-multiple",
 *   },
 * )
 */
final class SprowtAiExample extends RevisionableContentEntityBase implements SprowtAiExampleInterface
{

    use EntityChangedTrait;

    public static $vocab = 'ai_example_tags';

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
            ->setDisplayConfigurable('view', false);

        $fields['description'] = BaseFieldDefinition::create('text_long')
            ->setRevisionable(TRUE)
            ->setLabel(t('Description'))
            ->setDisplayOptions('form', [
                'type' => 'text_textarea',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', false);

        $fields['prompt'] = BaseFieldDefinition::create('string_long')
            ->setRevisionable(TRUE)
            ->setLabel(t('Prompt text'))
            ->setDescription('An example prompt to send to the ai service. Should be text only.')
            ->setDisplayOptions('form', [
                'type' => 'string_textarea',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'type' => 'basic_string',
                'label' => 'above',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('view', TRUE);

        $fields['result'] = BaseFieldDefinition::create('text_long')
            ->setRevisionable(TRUE)
            ->setLabel(t('Result text'))
            ->setDescription('The expected result from the ai service. Can be in any format. REMEMBER: CKeditor returns html.')
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
            ->setDescription('Tags that are associated with this example.')
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
            ->setDescription(t('The time that the example was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the example was last edited.'));

        return $fields;
    }

    public function renderPrompt() {
        return $this->get('prompt')->value;
    }

    public function renderResult()
    {
        return $this->get('result')->value;
    }
}
