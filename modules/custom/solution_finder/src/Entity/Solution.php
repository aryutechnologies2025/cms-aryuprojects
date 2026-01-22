<?php

namespace Drupal\solution_finder\Entity;

use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\solution_finder\SolutionInterface;

/**
 * Defines the solution entity class.
 *
 * @ContentEntityType(
 *   id = "solution",
 *   label = @Translation("Solution"),
 *   label_collection = @Translation("Solutions"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\solution_finder\SolutionListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\solution_finder\Form\SolutionForm",
 *       "edit" = "Drupal\solution_finder\Form\SolutionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "solution",
 *   admin_permission = "administer solution finder",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/solution/add",
 *     "canonical" = "/admin/solution/{solution}",
 *     "edit-form" = "/admin/structure/solution/{solution}/edit",
 *     "delete-form" = "/admin/structure/solution/{solution}/delete",
 *     "collection" = "/admin/structure/solution"
 *   },
 * )
 */
class Solution extends ContentEntityBase implements SolutionInterface
{

    use EntityChangedTrait;

    public static function sort(Solution $a, Solution $b) {
        $aWeight = $a->getWeight() ?? 0;
        $bWeight = $b->getWeight() ?? 0;
        if($aWeight == $bWeight) {
            $aLabel = $a->label();
            $bLabel = $b->label();
            if($aLabel == $bLabel) {
                $aVal = $a->getChangedTime();
                $bVal = $b->getChangedTime();
                if($aVal == $bVal) {
                    return 0;
                }
                return $aVal < $bVal ? -1 : 1;
            }
            return strcmp($aLabel, $bLabel);
        }

        return $aWeight < $bWeight ? -1 : 1;
    }

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

    public function getConcerns(): array
    {
        $return = [];
        /** @var FieldItemListInterface $values */
        $values = $this->get('concerns');
        /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $value */
        foreach($values as $value) {
            $return[] = $value->entity;
        }
        return $return;
    }

    public function getConcernUuids(): array
    {
        $return = [];
        $concerns = $this->getConcerns();
        foreach($concerns as $concern) {
            if($concern instanceof Node) {
                $return[] = $concern->uuid();
            }
        }
        return $return;
    }

    public function getDestinations() {
        $list = $this->get('solution_pages');
        if($list->isEmpty()) {
            return [];
        }
        return $list->first()->getValue();
    }

    public function getSolutionPage($sourceNode = null): ?Node
    {
        $destinations = $this->getDestinations() ?? [];
        $subsiteKey = '_main';
        if($sourceNode instanceof Node) {
            $subsite = sprowt_subsite_get_subsite($sourceNode);
            if($subsite instanceof Node) {
                $subsiteKey = $subsite->uuid();
            }
        }

        $nid = $destinations[$subsiteKey] ?? null;
        if(!empty($nid)) {
            return Node::load($nid);
        }
        return null;
    }

    public function getSolutionPageUuid(): ?string
    {
        $solutionPage = $this->getSolutionPage();
        if($solutionPage instanceof Node) {
            return $solutionPage->uuid();
        }
        return null;
    }

    public function setConcerns(?array $concerns): SolutionInterface
    {
        $concerns = isset($concerns) ? $concerns : [];
        $values = [];
        foreach($concerns as $concern) {
            $values[] = [
                'target_id' => $concern->id()
            ];
        }
        if(!empty($values)) {
            $this->set('concerns', $values);
        }
        else {
            $this->set('concerns', null);
        }
        return $this;
    }

    public function setSolutionPage(?Node $solution): SolutionInterface
    {
        if(isset($solution)) {
            $this->set('solution_page', [
                'target_id' => $solution->id()
            ]);
        }
        else {
            $this->set('solution_page', null);
        }
        return $this;
    }

    public function getWeight() {
        return $this->get('weight')->value;
    }

    public function setWeight($weight) {
        $this->set('weight', $weight);
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
            ->setDescription(t('The label of the solution entity.'))
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
            ->setDescription(t('A boolean indicating whether the solution is enabled.'))
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
            ->setLabel(t('Description'))
            ->setDescription(t('A description of the solution.'))
            ->setDisplayOptions('form', [
                'type' => 'text_textarea',
                'weight' => 0,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'type' => 'text_default',
                'label' => 'above',
                'weight' => 0,
            ])
            ->setDisplayConfigurable('view', TRUE);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the solution was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the solution was last edited.'));

        $fields['weight'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Weight'))
            ->setDescription(t('The weight of the solution.'));

        $fields['concerns'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('Concerns'))
            ->setDescription(t('Concerns attached to this solution'))
            ->setSetting('target_type', 'node')
            ->setSetting('handler', 'default')
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'entity_reference_label',
                'weight' => 0,
            ])
            ->setDisplayOptions('form', [
                'type' => 'options_buttons',
                'weight' => 0,
            ])
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
        $fields['solution_page'] = BaseFieldDefinition::create('entity_reference')
            ->setLabel(t('Solution Page'))
            ->setDescription(t('Solution Page attached to this solution'))
            ->setSetting('target_type', 'node')
            ->setSetting('handler', 'default')
            ->setCardinality(1);

        $fields['solution_pages'] = BaseFieldDefinition::create('map')
            ->setLabel(t('Solution Pages'))
            ->setDescription(t('Array of solution pages mapped to subsites attached to this solution'))
            ->setDisplayConfigurable('form', false)
            ->setDisplayConfigurable('view', false)
            ->setCardinality(1);

        return $fields;
    }

}
