<?php declare(strict_types=1);

namespace Drupal\sprowt_admin_override\EventSubscriber;

use Drupal\block_content\Access\RefinableDependentAccessInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\SectionComponent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @todo Add description for this subscriber.
 */
class LayoutBuilderBlockRenderSubscriber implements EventSubscriberInterface
{


    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        $events = [];
        $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 99];
        return $events;
    }


    /**
     * Builds render arrays for block plugins and sets it on the event.
     *
     * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
     *   The section component render event.
     */
    public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
        $block = $event->getPlugin();
        if (!$block instanceof BlockPluginInterface) {
            return;
        }

        $build = $event->getBuild();
        $content = $block->build();
        $contexts = $event->getContexts();

        if (isset($content['#attributes'])) {
            $build['#attributes'] = $content['#attributes'];
        }

        if ($block instanceof InlineBlock) {
            /** @var Context $viewModeContext */
            $viewModeContext = $contexts['view_mode'] ?? null;
            /** @var EntityContext $entityContext */
            $entityContext = $contexts['entity'] ?? null;
            if(!empty($entityContext) && !empty($viewModeContext)) {
                $entity = $entityContext->getContextValue();
                /** @var SectionComponent $component */
                $component = $event->getComponent();
                if (empty($build['#contextual_links'])) {
                    $build['#contextual_links'] = [];
                }
                $build['#contextual_links']['sprowt_admin_override_layout_builder'] = [
                    'route_parameters' => [
                        'entityType' => $entity->getEntityTypeId(),
                        'entityId' => $entity->id(),
                        'viewMode' => $viewModeContext->getContextValue(),
                        'componentUuid' => $component->get('uuid')
                    ]
                ];
//                $build['#attributes']['data-fake-contextual-link'] = json_encode([
//                    'text' => 'Edit layout block',
//                    'url' => Url::fromRoute('sprowt_admin_override.layout_block_update', [
//                        'entityType' => $entity->getEntityTypeId(),
//                        'entityId' => $entity->id(),
//                        'viewMode' => $viewModeContext->getContextValue(),
//                        'componentUuid' => $component->get('uuid')
//                    ])->toString()
//                ]);
            }
        }

        if($event->inPreview() && empty($build["#configuration"]["label_display"]) && !empty($build["#configuration"]["label"]))
        {
            //always display label in preview
            $build["#configuration"]["label_display"] = true;
        }

        $event->setBuild($build);
    }

}
