<?php

namespace Drupal\sprowt_subsite\Plugin\views\filter;

use Drupal\node\Entity\Node;
use Drupal\views\Plugin\views\filter\ManyToOne;

/**
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("sprowt_subsite_views_filter")
 */
class SubsiteReferenceViewsFilter extends ManyToOne
{

    public function getValueOptions()
    {
        if (isset($this->valueOptions)) {
            return $this->valueOptions;
        }

        $baseOptions = [
            '_main' => 'Main site'
        ];
        $options = [];
        $subsites = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => 'subsite'
        ]);
        /** @var Node $subsite */
        foreach($subsites as $subsite) {
            $label = $subsite->label();
            $options[$subsite->uuid()] = $label;
        }
        asort($options);
        $options = array_merge($baseOptions, $options);
        $this->valueOptions = $options;

        return $this->valueOptions;
    }

}
