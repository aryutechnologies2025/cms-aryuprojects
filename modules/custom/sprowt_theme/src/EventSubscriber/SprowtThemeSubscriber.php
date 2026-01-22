<?php

namespace Drupal\sprowt_theme\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\sprowt2migrate\Plugin\migrate\source\Sprowt2Node;
use Drupal\sprowt_theme\SprowtThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sprowt Theme event subscriber.
 */
class SprowtThemeSubscriber implements EventSubscriberInterface
{

    /**
     * The sprowt_theme.service service.
     *
     * @var \Drupal\sprowt_theme\SprowtThemeService
     */
    protected $service;

    /**
     * Constructs a SprowtThemeSubscriber object.
     *
     * @param \Drupal\sprowt_theme\SprowtThemeService $service
     *   The sprowt_theme.service service.
     */
    public function __construct(SprowtThemeService $service)
    {
        $this->service = $service;
    }


    public function onMigrate(MigrateImportEvent $event) {
        $currentTheme = $this->service->currentTheme();
        if(empty($currentTheme)) {
            return;
        }

        $migration = $event->getMigration();
        $status = $migration->getStatus();
        if($status == MigrationInterface::RESULT_COMPLETED) {
            $destinationPlugin = $migration->getDestinationPlugin();
            if($destinationPlugin instanceof Sprowt2Node) {
                $nids = $migration->getDestinationIds();
                $nodes = Node::loadMultiple($nids);
                foreach($nodes as $node) {
                    $this->service->themeNode($node);
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
            MigrateEvents::POST_IMPORT => ['onMigrate']
        ];
    }

}
