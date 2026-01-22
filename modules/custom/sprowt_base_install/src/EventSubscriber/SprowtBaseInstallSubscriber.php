<?php

namespace Drupal\sprowt_base_install\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\sprowt_base_install\SprowtBaseInstallUtil;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sprowt Base Install event subscriber.
 */
class SprowtBaseInstallSubscriber implements EventSubscriberInterface
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
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['onException'],
            KernelEvents::REQUEST => ['onRequest']
        ];
    }

    public function onRequest(RequestEvent $requestEvent) {
        $installed = \Drupal::state()->get('sprowt_site_installed', false);
        $request = $requestEvent->getRequest();
        $method = $request->getMethod();
        $finalized = \Drupal::state()->get('sprowt.site_install_finalized', false);
        $routeMatch = \Drupal::routeMatch();
        $routeName = $routeMatch->getRouteName();
        $isSprowtRoute = strpos($routeName, 'sprowt.') === 0;
        $installerRunning =  \Drupal::state()->get('sprowt_install_process_running', false);
        $isAsset = strpos($routeName, '.js_asset') !== false || strpos($routeName, '.css_asset') !== false;
        if (!InstallerKernel::installationAttempted()
            && PHP_SAPI !== 'cli'
            && empty($installed)
            && $method != 'POST'
            && !$isSprowtRoute
            && !$isAsset
        ) {
            $response = new RedirectResponse($request->getBasePath() . '/core/install.php', 302, ['Cache-Control' => 'no-cache']);
            $requestEvent->setResponse($response);
        }
        if(!empty($installerRunning)
            && empty($finalized)
            && $method != 'POST'
            && !$isSprowtRoute
            && !$isAsset
        ) {
            $response = new RedirectResponse($request->getBasePath() . '/sprowt/install/run', 302, ['Cache-Control' => 'no-cache']);
            $requestEvent->setResponse($response);
        }
    }

    public function onException(ExceptionEvent $event) {
        $e = $event->getThrowable();
        $installed = \Drupal::state()->get('sprowt_site_installed', false);
        if($e instanceof NotFoundHttpException && empty($installed)) {
            $event->setResponse(new RedirectResponse('/core/install.php'));
        }
    }

}
