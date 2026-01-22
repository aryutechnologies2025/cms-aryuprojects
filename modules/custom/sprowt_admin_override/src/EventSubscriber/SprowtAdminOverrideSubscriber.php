<?php declare(strict_types=1);

namespace Drupal\sprowt_admin_override\EventSubscriber;

use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @todo Add description for this subscriber.
 */
class SprowtAdminOverrideSubscriber implements EventSubscriberInterface
{

    /**
     * Kernel request event handler.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // @todo Place your code here.

    }

    /**
     * Kernel response event handler.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        // @todo Place your code here.
        $stop = true;
    }

    public function onControllerArguments(ControllerArgumentsEvent $event)
    {
        $controller = $event->getController();
        $arguments =  $event->getNamedArguments();
        $stop = true;
    }

    public function onView(ViewEvent $event)
    {
        $request = $event->getRequest();
        $path = $request->getRequestUri();
        if($path == '/admin/help/token') {
            //override token help to better render token tree
            $result = $event->getControllerResult();
            $result['top'] = sprowt_admin_override_token_help_override();
            $event->setControllerResult($result);
        }
    }

    public function alterForm(FormAlterEvent $event)
    {
        // Get the form from the event.
        $form = &$event->getForm();
        $formState = $event->getFormState();
        $formId = $event->getFormId();

        if($formId == 'layout_builder_configure_section') {
            $form["layout_settings"]["layout_builder_id"]['#description'] = t('HTML ID that allows the front-end developer and the Drupal System to classify that section for styling purposes and target anchor links.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormHookEvents::FORM_ALTER => 'alterForm',
//            KernelEvents::REQUEST => ['onKernelRequest'],
//            KernelEvents::RESPONSE => ['onKernelResponse'],
//            KernelEvents::CONTROLLER_ARGUMENTS => ['onControllerArguments'],
//            KernelEvents::VIEW => ['onView', 10]
        ];
    }

}
