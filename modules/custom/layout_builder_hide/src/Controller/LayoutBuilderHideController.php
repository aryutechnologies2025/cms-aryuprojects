<?php

declare(strict_types=1);

namespace Drupal\layout_builder_hide\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_hide\LayoutBuilderHideService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Layout Builder Hide routes.
 */
class LayoutBuilderHideController extends ControllerBase
{

    use LayoutRebuildTrait;

    /**
     * The Layout Tempstore.
     *
     * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
     */
    protected $layoutTempstore;

    /**
     *
     * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
     *   The layout tempstore.
     */
    public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
        $this->layoutTempstore = $layout_tempstore_repository;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('layout_builder.tempstore_repository')
        );
    }

    public function hideBlock(?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL)
    {
        /** @var LayoutBuilderHideService $service */
        $service = \Drupal::service('layout_builder_hide.service');
        $component = $section_storage->getSection($delta)->getComponent($uuid);
        $service->hideBlockComponent($component, true);
        $this->layoutTempstore->set($section_storage);
        return $this->rebuildLayout($section_storage);
    }

    public function showBlock(?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL)
    {
        /** @var LayoutBuilderHideService $service */
        $service = \Drupal::service('layout_builder_hide.service');
        $component = $section_storage->getSection($delta)->getComponent($uuid);
        $service->hideBlockComponent($component, false);
        $this->layoutTempstore->set($section_storage);
        return $this->rebuildLayout($section_storage);
    }

}
