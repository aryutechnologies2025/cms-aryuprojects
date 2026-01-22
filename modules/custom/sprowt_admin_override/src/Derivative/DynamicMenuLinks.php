<?php

namespace Drupal\sprowt_admin_override\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

class DynamicMenuLinks extends DeriverBase
{


    public function getDerivativeDefinitions($base_plugin_definition)
    {
        //Bulk add terms to taxonomy vocabulary
        $baseDefinition = [
            'title' => 'Bulk add terms',
            'route_name' => 'entity.taxonomy_term.bulk_add_form',
            'weight' => 1
        ];
        $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
        foreach ($vocabularies as $vocabulary) {
            $vid = $vocabulary->id();
            $menuLinkId = "$vid.entity.taxonomy_term.bulk_add_form";
            $parentLinkId = "admin_toolbar_tools.extra_links:entity.taxonomy_vocabulary.overview_form.$vid";
            $this->derivatives[$menuLinkId] = $baseDefinition;
            $this->derivatives[$menuLinkId]['parent'] = $parentLinkId;
            $this->derivatives[$menuLinkId]['route_parameters'] = [
                'taxonomy_vocabulary' => $vid,
            ];
        }

        return parent::getDerivativeDefinitions($base_plugin_definition);
    }

}
