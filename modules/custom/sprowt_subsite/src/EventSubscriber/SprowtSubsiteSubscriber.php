<?php

namespace Drupal\sprowt_subsite\EventSubscriber;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sprowt Subsite event subscriber.
 */
class SprowtSubsiteSubscriber implements EventSubscriberInterface
{
    /**
     * Kernel response event handler.
     *
     * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
     *   Response event.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $routeMatch = \Drupal::routeMatch();
        if($routeMatch->getRouteName() == 'entity.node.canonical') {
            $node = $routeMatch->getParameter('node');
            if(!$node instanceof Node) {
                $node = Node::load($node);
            }
            if($node->bundle() == 'subsite') {
                $responseUrl = Url::fromRoute('sprowt_subsite.subsite_homepage', [
                    'node' => $node->id()
                ]);
                $response = new RedirectResponse($responseUrl->toString());
                $event->setResponse($response);
                if(\Drupal::currentUser()->isAuthenticated()) {
                    \Drupal::messenger()->addStatus(t('Redirected to subsite homepage for subsite, "@link"', [
                        '@link' => Markup::create("<a href=\"/node/{$node->id()}/edit\" target=\"_blank\">{$node->label()}</a>")
                    ]));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

}
