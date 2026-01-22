<?php

namespace Drupal\chat_codes;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a chat code entity type.
 */
class ChatCodeViewBuilder extends EntityViewBuilder
{

    /**
     * {@inheritdoc}
     */
    protected function getBuildDefaults(EntityInterface $entity, $view_mode)
    {
        $build = parent::getBuildDefaults($entity, $view_mode);
        // The chat code has no entity template itself.
        unset($build['#theme']);
        return $build;
    }

}
