<?php

namespace Drupal\sprowt_base_install\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Utility\Token;
use Drupal\sprowt_base_install\SprowtBaseInstallUtil;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
final class SprowtBaseInstallCommands extends DrushCommands
{

    /**
     * Constructs a SprowtBaseInstallCommands object.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static();
    }

    /**
     * Get the current progress of the installation process
     */
    #[CLI\Command(name: 'sprowt:install-progress', aliases: ['sip'])]
    public function commandName($options = ['format' => 'json'])
    {
        return SprowtBaseInstallUtil::installStatus();
    }


    /**
     * Get the current progress of the installation process
     */
    #[CLI\Command(name: 'sprowt:install-progress-bar', aliases: ['sipb'])]
    public function installProgressBar()
    {
        $progress = $this->io()->createProgressBar(1000);
        $progress->start();

        $format = [
            '<fg=yellow;options=underscore>%title%</>',
            '[%bar%] %percent:3s%%',
            '<fg=blue>%message%</>'
        ];

        $title = 'Intitiating...';
        $progress->setMessage($title, 'title');
        $progress->setPlaceholderFormatter('title', function ($progressBar) use ($title) {
            return $progressBar->getMessage('title');
        });

        $progress->setFormat(implode("\n", $format));
        $progress->setMessage('initializing install');
        $status = SprowtBaseInstallUtil::installStatus();
        $title = $status['title'] ?? 'Installing...';
        $progress->setMessage($title, 'title');
        $progress->setMessage($status['subtitle'] ?? 'installing...');
        $progressNumber = $status['progress'] * 1000;
        $progress->setProgress(floor($progressNumber));
        $progress->display();
        $progressNum = $progress->getProgress();
        if ($progressNum >= 10000) {
            $progress->finish();
            $finished = true;
        }
        $finished = false;
        while(!$finished) {
            $progressNum = $progress->getProgress();
            if ($progressNum >= 1000) {
                $progress->finish();
                $finished = true;
            }
            else {
                sleep(2);
                $status = $this->installStatusFromDrush();
                $oldStatus = [
                    'title' => $progress->getMessage('title'),
                    'subtitle' => $progress->getMessage(),
                    'progress' => (float) ($progress->getProgress() / 1000)
                ];
                $title = $status['title'] ?? 'Installing...';
                $progress->setMessage($title, 'title');
                $progress->setMessage($status['subtitle'] ?? 'installing...');
                $progressNumber = (float) ($status['progress'] * 1000);
                $progress->setProgress(floor($progressNumber));
                $progress->advance();
            }
        }
    }

    public function installStatusFromDrush() {
        $drush = DRUPAL_ROOT . '/vendor/bin/drush';
        if($_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3') {
            $drush .= ' @' . $_SERVER['SPROWTHQ_SITE_NAME'];
        }
        if($_SERVER['SPROWTHQ_ENVIRONMENT'] != 'local') {
            $drush .= '-' . $_SERVER['SPROWTHQ_ENVIRONMENT'];
        }
        $drushCommand = $drush . ' sprowt:install-progress';
        $process = Process::fromShellCommandline($drushCommand);
        $process->run();
        $output = $process->getOutput();
        $output = json_decode(trim($output), true);
        return $output;
    }

    /**
     * Get the current progress of the installation process
     */
    #[CLI\Command(name: 'sprowt:install-import-log')]
    public function getInstallImportLog($options = ['format' => 'json'])
    {
        $log = \Drupal::state()->get('sprowt_install.import_log', []);
        return $log;
    }

}
