<?php declare(strict_types=1);

namespace Drupal\layout_builder_hide\EventSubscriber;

use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder_hide\LayoutBuilderHideService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @todo Add description for this subscriber.
 */
class LayoutBuilderHideSubscriber implements EventSubscriberInterface
{

    public function __construct(
        protected LayoutBuilderHideService $layoutBuilderHideService
    ){

    }

    public function formAlter(FormAlterEvent $event)
    {
        $form = &$event->getForm();
        $formState = $event->getFormState();
        $formObj = $formState->getFormObject();
        $formId = $formObj->getFormId();
        $configureBlockIds = [
            'layout_builder_update_block',
            'layout_builder_add_block'
        ];

        if(in_array($formId, $configureBlockIds)) {
            $this->layoutBuilderHideService->blockFormAlter($form, $formState);
        }

        if($formId == 'layout_builder_configure_section') {
            $this->layoutBuilderHideService->sectionFormAlter($form, $formState);
        }

    }


    public function onComponentRender(SectionComponentBuildRenderArrayEvent $event)
    {
        $component = $event->getComponent();
        $hidden = !$this->layoutBuilderHideService->isComponentVisible($component);
        if($hidden) {
            $build = $event->getBuild();
            $build = $this->layoutBuilderHideService->hideBlock($build, $event->inPreview());
            $event->setBuild($build);
        }

        if($event->inPreview()) {
            $build = $event->getBuild();
            $stop = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormHookEvents::FORM_ALTER => 'formAlter',
            LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => ['onComponentRender', 10]
        ];
    }

}
