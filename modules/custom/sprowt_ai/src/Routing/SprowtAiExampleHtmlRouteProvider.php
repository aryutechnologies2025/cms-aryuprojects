<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for entities with administrative pages.
 */
final class SprowtAiExampleHtmlRouteProvider extends AdminHtmlRouteProvider
{

    /**
     * {@inheritdoc}
     */
    protected function getCanonicalRoute(EntityTypeInterface $entity_type): ?Route
    {
        return $this->getEditFormRoute($entity_type);
    }

    protected function getCollectionRoute(EntityTypeInterface $entity_type)
    {
        if ($route = parent::getCollectionRoute($entity_type)) {
            $route->setOption('_admin_route', TRUE);
            return $route;
        }
    }
}
