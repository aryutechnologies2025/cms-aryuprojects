<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\EventSubscriber;

use Drupal\Core\Utility\Error;
use Drupal\sprowt_ai\Event\Claude3ErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @todo Add description for this subscriber.
 */
class SprowtAiSubscriber implements EventSubscriberInterface
{


    public function onClaude3Error(Claude3ErrorEvent $event)
    {
        $exception = $event->getError();
        $message = $exception->getMessage();
        $response = $exception->getResponse();
        $body = $response->getBody()->getContents();
        $body = json_decode($body, true);
        if(!empty($body)) {
            $error = ' <pre>' . json_encode($body, JSON_PRETTY_PRINT) . '</pre>';
            Error::logException(\Drupal::logger('sprowt_ai'), $exception, 'Claude 3 Api error: ' . $message . "\n $error" . "\n @backtrace_string");
        }
        else {
            Error::logException(\Drupal::logger('sprowt_ai'), $exception, 'Claude 3 Api error: ' . $message . "\n @backtrace_string");
        }

        \Drupal::messenger()->addError('Claude 3 Api error: ' . $message);
    }


    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Claude3ErrorEvent::EVENT_NAME => ['onClaude3Error'],
        ];
    }

}
