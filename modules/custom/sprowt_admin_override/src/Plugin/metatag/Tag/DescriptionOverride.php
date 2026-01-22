<?php

namespace Drupal\sprowt_admin_override\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\Description;
use Drupal\node\Entity\Node;

class DescriptionOverride extends Description
{

    public function form(array $element = []): array
    {
        $form = parent::form($element);
        $routeMatch = \Drupal::routeMatch();
        $node = $routeMatch->getParameter('node');
        if(!empty($node) && !$node instanceof Node) {
            $node = Node::load($node);
        }
        if(!empty($node) && $node->hasField('field_meta_description')) {
            $form['#description'] = 'This tag has been overridden by a drupal field. Edit that instead';
            $form['#disabled'] = TRUE;
        }

        return $form;
    }
}
