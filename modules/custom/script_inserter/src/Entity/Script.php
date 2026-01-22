<?php

namespace Drupal\script_inserter\Entity;

//sometime this isn't loaded?
require_once DRUPAL_ROOT . '/modules/custom/sprowt_settings/src/EntityVisibilityTrait.php';

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Plugin\Condition\EntityBundle;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\script_inserter\ScriptInterface;
use Drupal\sprowt_settings\EntityVisibilityTrait;

/**
 * Defines the script entity class.
 *
 * @ContentEntityType(
 *   id = "script",
 *   label = @Translation("Script"),
 *   label_collection = @Translation("Scripts"),
 *   handlers = {
 *     "view_builder" = "Drupal\script_inserter\ScriptViewBuilder",
 *     "list_builder" = "Drupal\script_inserter\ScriptListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\script_inserter\Form\ScriptForm",
 *       "edit" = "Drupal\script_inserter\Form\ScriptForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "script",
 *   admin_permission = "administer scripts",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/script/add",
 *     "canonical" = "/admin/script/{script}",
 *     "edit-form" = "/admin/structure/script/{script}/edit",
 *     "delete-form" = "/admin/structure/script/{script}/delete",
 *     "collection" = "/admin/structure/script"
 *   },
 * )
 */
class Script extends ContentEntityBase implements ScriptInterface
{

    use EntityVisibilityTrait;
    use EntityChangedTrait;

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return $this->get('label')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setLabel($title)
    {
        $this->set('label', $title);
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
    public function getLocation()
    {
        return $this->get('location')->value;
    }

    /**
     * {@inheritdoc}
     */
    public static function getLocationOptions()
    {
        return [
            'head' => t('Head'),
            'page_top' => t('Page top'),
            'page_bottom' => t('Page bottom')
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getLocationLabel()
    {
        $options = static::getLocationOptions();
        $location = $this->getLocation();
        return !empty($location) ? $options[$location] ?? null : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setLocation($location)
    {
        $this->set('location', $location);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCode()
    {
        return $this->get('code')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setCode($code)
    {
        $this->set('code', $code);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {

        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['label'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Label'))
            ->setDescription(t('The label of the script entity.'))
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
            ->setLabel(t('Status'))
            ->setDescription(t('A boolean indicating whether the script is enabled.'))
            ->setDefaultValue(TRUE)
            ->setSetting('on_label', 'Enabled')
            ->setDisplayOptions('form', [
                'type' => 'boolean_checkbox',
                'settings' => [
                    'display_label' => FALSE,
                ],
                'weight' => -1,
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

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the script was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the script was last edited.'));

        $fields['location'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Location'))
            ->setDescription(t('The location of the script on the page.'))
            ->setRequired(TRUE)
            ->setSetting('max_length', 255);

        $fields['code'] = BaseFieldDefinition::create('string_long')
            ->setLabel(t('Code'))
            ->setDescription(t('Inserted code'))
            ->setRequired(TRUE)
            ->setDisplayOptions('form', [
                'type' => 'string_textarea',
                'label' => 'above',
                'weight' => 0,
            ]);

        $fields['page_restriction_negate'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Page restriction type'))
            ->setDescription(t('Page restriction type'));

        $fields['page_restrictions'] = BaseFieldDefinition::create('string_long')
            ->setLabel(t('Page restriction type'))
            ->setDescription(t('Page restriction type'));

        $fields['node_type_restrictions'] = BaseFieldDefinition::create('map')
            ->setLabel(t('Node type restriction'))
            ->setDescription(t('Node type restriction'));

        $fields['visibility'] = BaseFieldDefinition::create('map')
            ->setLabel(t('Visibility'))
            ->setDescription(t('Chat code visibility'));

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function render() {
        $label = $this->getLabel();
        $code = $this->getCode();
        if(empty($code)) {
            return [];
        }
        return [
            'commentStart' => [
                '#type' => 'markup',
                '#markup' => Markup::create("\n<!-- START script: $label -->\n")
            ],
            'code' => [
                '#type' => 'markup',
                '#markup' => Markup::create($code)
            ],
            'commentEnd' => [
                '#type' => 'markup',
                '#markup' => Markup::create("\n<!-- END script: $label -->\n")
            ]
        ];
    }

    /**
     * @param $pages
     * @param $negate
     * @return \Drupal\system\Plugin\Condition\RequestPath
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    public function getPageRestriction($pages = null, $negate = null)
    {
        /** @var ConditionManager $conditionManager */
        $conditionManager = \Drupal::service('plugin.manager.condition');
        $pages = isset($pages) ?  $pages : $this->get('page_restrictions')->value ?? '';
        $negate = isset($negate) ? $negate : $this->get('page_restriction_negate')->value ?? true;
        $negate = is_string($negate) ? (int) $negate : $negate;
        return $conditionManager->createInstance('request_path', [
            'pages' => $pages,
            'negate' => !empty($negate)
        ]);
    }

    public function restrictedByPage()
    {
        $pages = $this->get('page_restrictions')->value ?? '';
        if(empty(trim($pages))) {
            $hide = $this->get('page_restriction_negate')->value ?? true;
            $hide = is_string($hide) ? (int) $hide : $hide;
            return !$hide;
        }
        $condition = $this->getPageRestriction();
        $show = $condition->execute();
        return !$show;
    }

    public function getNodeTypeRestrictions() {
        $items = $this->get('node_type_restrictions');
        if($items->isEmpty()) {
            return [];
        }
        $types = $items->first()->getValue();
        return $types;
    }

    /**
     * @param $types
     * @return EntityBundle
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    public function getNodeTypeRestriction($types = null) {
        /** @var ConditionManager $conditionManager */
        $conditionManager = \Drupal::service('plugin.manager.condition');
        $types = $this->getNodeTypeRestrictions();
        return $conditionManager->createInstance('entity_bundle:node', [
            'bundles' => $types,
            'negate' => false
        ]);
    }

    public function restrictedByNode(Node $node) {
        $types = $this->getNodeTypeRestrictions();
        if(empty($types)) {
            return false;
        }
        $condition = $this->getNodeTypeRestriction();
        $condition->setContextValue('node', $node);
        $show = $condition->execute();
        return !$show;
    }
}
