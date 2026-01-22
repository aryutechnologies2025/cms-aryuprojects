<?php

namespace Drupal\sprowt_install\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\State\State;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ErrorSubscriber implements EventSubscriberInterface
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $state;

    public function __construct(
        LoggerInterface $logger,
        State $state
    ) {
        $this->logger = $logger;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() {
        return [
            KernelEvents::EXCEPTION => 'onException',
            KernelEvents::TERMINATE => 'onTerminate'
        ];
    }


    public function onException(ExceptionEvent $event) {
        $exception = $event
            ->getThrowable();
        $error = Error::decodeException($exception);
        if($exception instanceof DatabaseException) {
            // can't do anything about that
            return;
        }
        $request = $event->getRequest();
        $toLoop = $request->query->get('flush_cache_loop');

        if(!empty($toLoop)) {
            $redirectResponse = new RedirectResponse($request->getUri(), 302, $request->headers->all());
            $count = $this->state->get('sprowt_error_flush_counter', 0);
            if($count > 5) { // 5 is the limit
                return;
            }

            drupal_flush_all_caches();
            ++$count;
            $this->state->set('sprowt_error_flush_counter', $count);
            $event->setResponse($redirectResponse);
        }
        else {
            $this->state->set('sprowt_error_flush_counter', 0);
        }
    }

    public function onTerminate(TerminateEvent $event) {
        $response = $event->getResponse();
        $code = $response->getStatusCode();
        if($code == 200) {
            $this->state->set('sprowt_error_flush_counter', 0);
        }
    }
}
