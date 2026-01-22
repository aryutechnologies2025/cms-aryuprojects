<?php

namespace Drupal\sprowt_subsite\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\node\Entity\Node;


/**
 *
 * @EntityReferenceSelection(
 *   id = "subsite_menu_reference",
 *   label = @Translation("Subsite menu selection"),
 *   entity_types = {"menu"},
 *   group = "subsite_menu_reference",
 *   weight = 5
 * )
 */
class SubsiteMenuSelection extends DefaultSelection
{
    protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS')
    {
        $configuration = $this->getConfiguration();
        $target_type = $configuration['target_type'];
        $entity_type = $this->entityTypeManager->getDefinition($target_type);
        $query = parent::buildEntityQuery($match, $match_operator);
        $query->accessCheck(TRUE);
        $unAddedmenus = sprowt_get_filtered_menus([
            'referenced_node' => '<none>'
        ]);
        $menuIds = [];
        foreach($unAddedmenus as $menu) {
            $menuIds[] = $menu->id();
        }
        if(!empty($configuration['entity']) && $configuration['entity'] instanceof Node) {
            $addedmenus = sprowt_get_filtered_menus([
                'referenced_node' => $configuration['entity']->id()
            ]);
        }
        foreach($addedmenus as $menu) {
            $menuIds[] = $menu->id();
        }

        $query->condition($entity_type->getKey('id'), $menuIds, 'IN');
        return $query;
    }

    public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0)
    {
        $options = parent::getReferenceableEntities($match, $match_operator, $limit);
        foreach($options as &$optSet) {
            foreach($optSet as $entityId => $label) {
                $optSet[$entityId] = "$label [$entityId]";
            }
        }
        return $options;
    }
}
