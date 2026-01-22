<?php

namespace Drupal\anchor_list_block\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Anchor List Block event subscriber.
 */
class AnchorListBlockSubscriber implements EventSubscriberInterface
{

    /**
     * The messenger.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * Constructs event subscriber.
     *
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger.
     */
    public function __construct(MessengerInterface $messenger)
    {
        $this->messenger = $messenger;
    }

    /**
     * Alter form.
     *
     * @param \Drupal\core_event_dispatcher\Event\Form\FormAlterEvent $event
     *   The event.
     */
    public static function alterForm(FormAlterEvent $event): void {
        $form = &$event->getForm();
        if ($form['#form_id'] == 'layout_builder_configure_section') {
            $form["layout_settings"]["label"]['#required'] = true;
            if(!empty($form["layout_settings"]["layout_builder_id"])) {
                $form["layout_settings"]["layout_builder_id"]['#required'] = true;
                /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $description */
                $description = $form["layout_settings"]["layout_builder_id"]['#description'];
                $string = $description->getUntranslatedString();
                $form["layout_settings"]["layout_builder_id"]['#description'] = t(str_replace('an optional', 'a required', $string));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormHookEvents::FORM_ALTER => 'alterForm'
        ];
    }

}
