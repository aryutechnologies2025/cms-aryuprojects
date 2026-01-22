<?php

namespace Drupal\sprowt_subsite\EventSubscriber;

use Drupal\Core\Render\Markup;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sprowt Subsite event subscriber.
 */
class SprowtSubsiteLayoutBuilderSubscriber implements EventSubscriberInterface
{


    public function onComponentBuild(SectionComponentBuildRenderArrayEvent $event) {
        $build = $event->getBuild();
        if($event->inPreview()) {
            if($build["#base_plugin_id"] == 'subsite_menu_block') {
                $block = $event->getPlugin();
                $build['content'] = [
                    '#type' => 'markup',
                    '#markup' => Markup::create($block->previewBuild())
                ];
                $event->setBuild($build);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => ['onComponentBuild', 50]
        ];
    }

}
