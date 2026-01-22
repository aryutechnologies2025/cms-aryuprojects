<?php


namespace Drupal\sprowt_install\EventSubscriber;




use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\inline_block_content\InlineBlockContentService;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\webform\Plugin\WebformElement\DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ConfigImportSubscriber implements EventSubscriberInterface
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() {
        return [
            ConfigEvents::IMPORT_VALIDATE => 'onConfigImportValidate'
        ];
    }

    /**
     * back up database before config import
     * @param ConfigImporterEvent $event
     */
    public function onConfigImportValidate(ConfigImporterEvent $event) {
        $importer = $event->getConfigImporter();
        if(empty($importer->getErrors())) {
            $backupScript = '/home/www/bin/backupDb.sh';
            $now = new \DateTime();
            $seconds = $now->format('s');
            $parts = str_split((string) $seconds);
            $tenth = array_pop($parts);
            $filename = 'beforeConfigImport--' . $tenth;
            if(is_file($backupScript)) {
                $cmd = [
                    $backupScript,
                    $_SERVER['SPROWTHQ_SITE_NAME'],
                    $_SERVER['SPROWTHQ_ENVIRONMENT'],
                    $filename
                ];

                $process = new Process($cmd);
                $process->setTimeout(0);
                $process->run();
                if($process->isSuccessful()) {
                    $this->logger->info(t("Database backed up to @location", [
                        '@location' => 'gs://sprowthq/backups/' . "{$_SERVER['SPROWTHQ_SITE_NAME']}-{$_SERVER['SPROWTHQ_ENVIRONMENT']}/{$filename}-db.sql.gz"
                    ]));
                }
                else {
                    $this->logger->error(t('Database not backed up: @info', [
                        '@info' => "\n\n" . $process->getOutput() . "\n\n" . $process->getErrorOutput()
                    ]), [
                        'exception' => new ProcessFailedException($process)
                    ]);
                    $importer->logError(t('Database not backed up'));
                }
            }
        }
    }
}
