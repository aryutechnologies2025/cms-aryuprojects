<?php

namespace Drupal\color_variables\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\color_variables\ColorVariableItemInterface;
use Drupal\Core\Render\Markup;

/**
 * Defines the theme color variables entity class.
 *
 * @ContentEntityType(
 *   id = "color_variable_item",
 *   label = @Translation("Color Variable Item"),
 *   label_collection = @Translation("Color Variable Items"),
 *   handlers = {
 *     "view_builder" = "Drupal\color_variables\ColorVariableItemViewBuilder",
 *     "list_builder" = "Drupal\color_variables\ColorVariableItemListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\color_variables\ColorVariableItemAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\color_variables\Form\ColorVariableItemForm",
 *       "edit" = "Drupal\color_variables\Form\ColorVariableItemForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "revision-revert" = "Drupal\Core\Entity\Form\RevisionRevertForm",
 *       "revision-delete" = "Drupal\Core\Entity\Form\RevisionDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *       "revision" = "Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "color_variable_item",
 *   revision_table = "color_variable_item_revision",
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer theme color variables",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "theme",
 *     "uuid" = "uuid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *     "revision_user" = "revision_user"
 *   },
 *   links = {
 *     "add-form" = "/admin/appearance/color-variables/{theme}/add",
 *     "canonical" = "/admin/appearance/color-variables/{color_variable_item}",
 *     "edit-form" = "/admin/appearance/color-variables/{color_variable_item}/edit",
 *     "delete-form" = "/admin/appearance/color-variables/{color_variable_item}/delete",
 *     "collection" = "/admin/appearance/color-variables",
 *     "revision" = "/admin/appearance/color-variables/{color_variable_item}/revisions/{color_variable_item_revision}/view",
 *     "version-history" = "/admin/appearance/color-variables/{color_variable_item}/revisions",
 *     "revision-delete-form" = "/admin/appearance/color-variables/{color_variable_item}/revisions/{color_variable_item_revision}/delete",
 *     "revision-revert-form" = "/admin/appearance/color-variables/{color_variable_item}/revisions/{color_variable_item_revision}/revert",
 *   },
 *   field_ui_base_route = "entity.color_variable_item.settings"
 * )
 */
class ColorVariableItem extends RevisionableContentEntityBase implements ColorVariableItemInterface
{

    use EntityChangedTrait;

    public static function load($idOrTheme)
    {
        if(ctype_digit($idOrTheme)) {
            return parent::load($idOrTheme);
        }
        $entity_type_repository = \Drupal::service('entity_type.repository');
        $entity_type_manager = \Drupal::entityTypeManager();
        $storage = $entity_type_manager->getStorage($entity_type_repository->getEntityTypeFromClass(static::class));
        $entities = $storage->loadByProperties([
            'theme' => $idOrTheme
        ]);
        return !empty($entities) ? array_pop($entities) : null;
    }

    public function label()
    {
        $theme = parent::label();
        return "Color variables for $theme";
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

    public function getTheme()
    {
        return $this->get('theme')->value;
    }

    public function getVariables()
    {
        $field = $this->get('variables')->first();
        return !empty($field) ? $field->getValue() ?? [] : [];
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {

        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['theme'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Theme'))
            ->setDescription(t('The theme for the color variables'))
            ->setDisplayOptions('view', [
                'region' => 'hidden'
            ])
            ->setDisplayConfigurable('form', false)
            ->setDisplayConfigurable('view', false)
        ;

        $fields['variables'] = BaseFieldDefinition::create('map')
            ->setLabel(t('Color variables'))
            ->setDescription(t('The overridden color variables'))
            ->setDisplayConfigurable('form', false)
            ->setDisplayConfigurable('view', false)
            ->setRevisionable(true)
            ->setDisplayOptions('view', [
                'region' => 'hidden'
            ]);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the theme color variables was created.'))
            ->setDisplayConfigurable('form', false)
            ->setDisplayConfigurable('view', false);

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the theme color variables was last edited.'))
            ->setDisplayConfigurable('form', false)
            ->setDisplayConfigurable('view', false);

        return $fields;
    }

    protected function urlRouteParameters($rel)
    {
        $params = parent::urlRouteParameters($rel);
        $params['color_variable_theme'] = $this->getTheme();
        return $params;
    }

    public function getSummary() {
        $overrides = $this->getVariables();
        $ul = [
            '#type' => 'html_tag',
            '#tag' => 'ul',
            'theme' => [
                '#type' => 'html_tag',
                '#tag' => 'li',
                '#value' => Markup::create("<strong>theme</strong>: <span>{$this->getTheme()}</span>")
            ]
        ];
        foreach($overrides as $var => $val) {
            $markup = "<strong>$var</strong>: <span>$val</span> <span style='display:inline-block; height:13px; width:13px; background-color: $val'>&nbsp;</span>";
            $ul[$var] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                '#value' => Markup::create($markup)
            ];
        }
        if(empty($overrides)) {
            $ul['message'] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                '#value' => Markup::create('<strong>No color variables set for this theme</strong>')
            ];
        }

        return $ul;
    }

    public function getRevisionLogMessage()
    {
        $message = parent::getRevisionLogMessage();

        $render = [
            'message' => [
                '#type' => 'markup',
                '#markup' => Markup::create('<div>'.$message.'</div>')
            ],
            'summary' => $this->getSummary()
        ];

        return \Drupal::service('renderer')->renderRoot($render);
    }

}
