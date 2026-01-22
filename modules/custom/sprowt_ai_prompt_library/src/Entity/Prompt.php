<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\sprowt_ai\AiService;
use Drupal\sprowt_ai_prompt_library\PromptInterface;

/**
 * Defines the prompt entity class.
 *
 * @ContentEntityType(
 *   id = "ai_prompt",
 *   label = @Translation("Prompt"),
 *   label_collection = @Translation("Prompts"),
 *   label_singular = @Translation("prompt"),
 *   label_plural = @Translation("prompts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count prompts",
 *     plural = "@count prompts",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\sprowt_ai_prompt_library\PromptListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\sprowt_ai_prompt_library\Form\PromptForm",
 *       "edit" = "Drupal\sprowt_ai_prompt_library\Form\PromptForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\sprowt_ai_prompt_library\Routing\PromptHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "ai_prompt",
 *   revision_table = "ai_prompt_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer ai_prompt",
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
 *     "collection" = "/admin/config/services/sprowt-ai/ai-prompt",
 *     "add-form" = "/admin/config/services/sprowt-ai/prompt/add",
 *     "canonical" = "/admin/config/services/sprowt-ai/prompt/{ai_prompt}",
 *     "edit-form" = "/admin/config/services/sprowt-ai/prompt/{ai_prompt}",
 *     "delete-form" = "/admin/config/services/sprowt-ai/prompt/{ai_prompt}/delete",
 *     "delete-multiple-form" = "/admin/config/services/sprowt-ai/ai-prompt/delete-multiple",
 *   },
 * )
 */
class Prompt extends RevisionableContentEntityBase implements PromptInterface
{

    use EntityChangedTrait;

    public static $vocab = 'ai_prompt_tag';

    protected $source;

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

        $fields['description'] = BaseFieldDefinition::create('text_long')
            ->setRevisionable(TRUE)
            ->setLabel(t('Description'))
            ->setDescription(t('Description of the prompt. Used to differentiate prompts from each other in the selector.'))
            ->setDisplayOptions('form', [
                'type' => 'text_textarea',
                'weight' => 10,
                'required' => true
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
            ->setDescription('Tags that are associated with this prompt.')
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('form', [
                'type' => 'options_select',
                'label' => 'above',
                'weight' => 10
            ])
            ->setSetting('target_type', 'taxonomy_term')
            ->setSetting('handler_settings', [
                'target_bundles' => [
                    static::$vocab
                ]
            ]);

        $fields['prompt'] = BaseFieldDefinition::create('sprowt_ai_prompt')
            ->setRevisionable(TRUE)
            ->setLabel(t('Prompt text'))
            ->setDescription('An example prompt to send to the ai service.')
            ->setDisplayOptions('form', [
                'type' => 'sprowt_ai_prompt',
                'weight' => 10,
                'required' => true
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'type' => 'basic_string',
                'label' => 'above',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('view', TRUE);


        $fields['connected_examples'] = BaseFieldDefinition::create('entity_reference')
            ->setRevisionable(true)
            ->setLabel(t('Connected entities'))
            ->setDescription('Example entities that this prompt is connected to.')
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
            ->setSetting('target_type', 'sprowt_ai_example');

        $fields['connected_contexts'] = BaseFieldDefinition::create('entity_reference')
            ->setRevisionable(true)
            ->setLabel(t('Connected contexts'))
            ->setDescription('Context entities that this prompt is connected to.')
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
            ->setSetting('target_type', 'sprowt_ai_context');

        $fields['connected_documents'] = BaseFieldDefinition::create('entity_reference')
            ->setRevisionable(true)
            ->setLabel(t('Connected contexts'))
            ->setDescription('Media (document) entities that this prompt is connected to.')
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
            ->setSetting('target_type', 'media');

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the prompt was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the prompt was last edited.'));

        return $fields;
    }

    public function preSave(EntityStorageInterface $storage)
    {
        $promptText = $this->get('prompt')->value;
        if(!empty($promptText)) {
            /** @var AiService $service */
            $service = \Drupal::service('sprowt_ai.service');
            $extracted = $service->extractEntitiesFromPromptText($promptText);
            $examples = [];
            $documents = [];
            $contexts = [];
            /** @var EntityInterface $entity */
            foreach($extracted as $entityType => $entities) {
                foreach($entities as $entity) {
                    if ($entityType == 'sprowt_ai_example') {
                        $examples[] = [
                            'target_id' => $entity->id(),
                        ];
                    }
                    if ($entityType == 'media') {
                        $documents[] = [
                            'target_id' => $entity->id(),
                        ];
                    }
                    if ($entityType == 'sprowt_ai_context') {
                        $contexts[] = [
                            'target_id' => $entity->id(),
                        ];
                    }
                }
            }
            $this->set('connected_examples', $examples);
            $this->set('connected_documents', $documents);
            $this->set('connected_contexts', $contexts);
        }
    }

    public function getConnectedEntities()
    {
        $entities = [];
        $lists = [
            'examples' => $this->get('connected_examples'),
            'documents' => $this->get('connected_documents'),
            'contexts' => $this->get('connected_contexts')
        ];
        /** @var EntityReferenceFieldItemList $list */
        foreach ($lists as $list) {
            if(!$list->isEmpty()) {
                $entities = array_merge($entities, $list->referencedEntities());
            }
        }
        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function postSave(EntityStorageInterface $storage, $update = TRUE)
    {
        //refresh cached map
        \Drupal::service('sprowt_ai_prompt_library.service')->localPromptMap(true);
    }

    public function isEnabled()
    {
        return !empty($this->get('status')->value);
    }

    public static function loadByUuid($uuid)
    {
        $entities = \Drupal::entityTypeManager()->getStorage('ai_prompt')->loadByProperties([
            'uuid' => $uuid
        ]);
        if(empty($entities)) {
            return null;
        }

        return array_shift($entities);
    }

    public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE)
    {
        if ($operation == 'view') {
            if($account->hasPermission('use sprowt_ai_prompt_library') || $account->hasPermission('administer ai_prompt')) {
                if ($return_as_object) {
                    return AccessResult::allowed();
                }
                else {
                    return true;
                }
            }
            if ($return_as_object) {
                return AccessResult::forbidden();
            }
            else {
                return false;
            }
        }
        return parent::access($operation, $account, $return_as_object);
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     * @return self;
     */
    public function setSource($source): self
    {
        $this->source = $source;
        return $this;
    }

    public function toUrl($rel = NULL, array $options = [])
    {
        $url = parent::toUrl($rel, $options);
        $source = $this->getSource();
        if(empty($source) || $source == 'local') {
            return $url;
        }
        $url->setAbsolute(false);
        $url = Url::fromUri($source . $url->toString());
        return $url;
    }

}
