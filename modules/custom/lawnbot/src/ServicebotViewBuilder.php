<?php

namespace Drupal\lawnbot;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a servicebot entity type.
 */
class ServicebotViewBuilder extends EntityViewBuilder
{

    /**
     * {@inheritdoc}
     */
    protected function getBuildDefaults(EntityInterface $entity, $view_mode)
    {
        $build = parent::getBuildDefaults($entity, $view_mode);
        // The servicebot has no entity template itself.
        unset($build['#theme']);
        return $build;
    }

}
