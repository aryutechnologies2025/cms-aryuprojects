<?php

namespace Drupal\sprowt_base_install;


use Drupal\Core\Database\Database;
use http\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SprowtBaseInstallUtil
{
    public static function isProcessRunning() {
        $state = \Drupal::state();
        return $state->get('sprowt_install_process_running', false);
    }

    public static function drushProcess($command) {
        $env = $_SERVER['SPROWTHQ_ENVIRONMENT'];
        $alias = $_SERVER['SPROWTHQ_SITE_NAME'];
        if($env != 'local') {
            $alias .= "-$env";
        }
        $drush = DRUPAL_ROOT . '/vendor/bin/drush';
        $cmd = [$drush];
        if($_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3') {
            $cmd[] = '@' . $alias;
        }
        if(is_array($command)) {
            $cmd = array_merge($cmd, $command);
            $process = new Process($cmd);
        }
        elseif (is_string($command)) {
            $process = Process::fromShellCommandline(implode(' ', $cmd) . ' ' . $command);
        }
        if(!empty($_GET) && !empty($_GET['XDEBUG_SESSION_START'])) {
            $env = $process->getEnv();
            $env['XDEBUG_SESSION'] = 'PHPSTORM';
            $process->setEnv($env);
        }
        if(!empty($_ENV) && !empty($_ENV['XDEBUG_SESSION'])) {
            $env = $process->getEnv();
            $env['XDEBUG_SESSION'] = $_ENV['XDEBUG_SESSION'];
            $process->setEnv($env);
        }
        $process->setTimeout(0);
        $process->setWorkingDirectory(DRUPAL_ROOT);
        return $process;
    }

    public static function drushCommandExists($cmdName) {
        $process = SprowtBaseInstallUtil::drushProcess([
            'list',
            '--format=json'
        ]);
        $process->run();
        $json = trim($process->getOutput());
        $list = json_decode($json, true);
        $commands = $list['commands'];
        $found = null;
        foreach($commands as $command) {
            if($command['name'] == $cmdName && empty($found)) {
                $found = $command;
                break;
            }
        }
        return !empty($found);
    }

    public static function cacheClear() {
        $process = SprowtBaseInstallUtil::drushProcess(['cr']);
        $process->run();
    }

    public static function startInstaller() {
        set_time_limit(0);
        $installed = \Drupal::state()->get('sprowt_site_installed', false);
        if(!empty($installed)) {
            throw new RuntimeException('site already installed');
        }
        $state = \Drupal::state();
        $running = static::isProcessRunning();
        if(!empty($running)) {
            throw new RuntimeException('Installer already running');
        }

        if(!static::drushCommandExists('sprowt:install')) {
            static::cacheClear();
        }

        $process = SprowtBaseInstallUtil::drushProcess([
            'sprowt:install',
            '-v',
            '--format=json'
        ]);
        $process->start(function ($type, $data) use ($state) {
            if ($type == \Symfony\Component\Process\Process::OUT) {
                $output = $state->get('sprowt_install_process_output', []);
                $output[] = $data;
                $state->set('sprowt_install_process_output', $output);
            } else {
                if (strpos($data, '[notice]') === false
                    && strpos($data, '[info]') === false
                ) {
                    $output = $state->get('sprowt_install_process_error_output', []);
                    $output[] = $data;
                    $state->set('sprowt_install_process_error_output', $output);
                }
            }
        });
        $state->set('sprowt_install_process_running', true);
        $state->set('sprowt_install_process_started', true);
        $process->wait();
        $state->delete('sprowt_install_process_running');
        return $process;
    }

    public static function getStoredBatch() {
        $data = \Drupal::state()->get('sprowt.current_batch', null);
        if(!isset($data)) {
            return static::getBatchFromDb();
        }
        return $data;
    }

    public static function getBatchFromDb($stateKey = 'sprowt_install_batch_id')
    {
        if(!Database::getConnection()->schema()->tableExists('batch')) {
            return [];
        }
        $batchId = \Drupal::state()->get($stateKey, null);
        /** @var \Drupal\Core\Batch\BatchStorage $batch_storage */
        $batch_storage = \Drupal::service('batch.storage');
        if(!isset($batchId)) {
            $batches = Database::getConnection()->select('batch', 'b')
                ->fields('b', ['batch'])
                ->execute()
                ->fetchCol();
            foreach($batches as $serialized) {
                $batch = unserialize($serialized);
                if(!empty($batch['sprowt_install_batch'])) {
                    $data = $serialized;
                    \Drupal::state()->set('sprowt_install_batch_id', $batch['id']);
                    break;
                }
            }
        }
        else {
            $data = Database::getConnection()->select('batch', 'b')
                ->fields('b', ['batch'])
                ->condition('bid', $batchId)
                ->execute()
                ->fetchField();
        }

        return unserialize($data);
    }

    public static function getBatchInfo($batch)
    {
        require_once DRUPAL_ROOT . '/core/includes/batch.inc';
        $currentSet = $batch['sets'][$batch['current_set']];
        $remaining = $currentSet['count'];
        $total = $currentSet['total'];
        $taskMessage = $currentSet['task_message'] ?? '';
        $finished = $currentSet['task_finished'] ?? 0;
        $current    = $total - $remaining + $finished;
        if(!empty($total) && !empty($finished)) {
            $setPercentage = _batch_api_percentage($total, $current);
            while($setPercentage > 0.1) {
                $setPercentage = $setPercentage / 10;
            }
        }
        else {
            $setPercentage = 0;
        }

        $setCount = count($batch['sets']);
        $completedSets = $batch['current_set'];
        $percentage = 0;
        if(!empty($setCount) && !empty($completedSets)) {
            $percentage = _batch_api_percentage($setCount, $completedSets);
        }
        $percentage += $setPercentage;

        return [
            'success' => true,
            'title' => $currentSet['title'],
            'label' => $taskMessage,
            'percentage' => round($percentage, 2),
            'setCount' => count($batch['sets']),
            'currentSetIdx' => $batch['current_set'],
            'batch' => $batch
        ];
    }

    public static function batchInfo()
    {
        $data = static::getStoredBatch();
        if(empty($data)) {
            return [
                'success' => false,
                'error' => "Batch doesn't exist"
            ];
        }

        return static::getBatchInfo($data);
    }


    public static function installStatus()
    {
        $storedInstallState = \Drupal::state()->get('sprowt.install_state_store', []);
        if(empty($storedInstallState)) {
            return [
                'title' => 'Initializing',
                'subtitle' => 'Starting install...',
                'progress' => 0
            ];
        }

        $installState = $storedInstallState['installState'];
        $tasks = $storedInstallState['tasks'];
        $taskKeys = array_keys($tasks);
        $activeTask = $storedInstallState['activeTask'] ?? $taskKeys[0];
        $progress = $storedInstallState['taskProgress'];
        $title = 'Running install task';
        $subtitle = 'install task: ' . $activeTask;
        if($activeTask == '_sprowt_install_batch') {
            $installProgress = SprowtBaseInstallUtil::batchInfo();
            if(!empty($installProgress) && !empty($installProgress['success'])) {
                $progressPart = $installProgress['percentage'] / 1000;
                $progress = $progress + $progressPart;
                $title = $installProgress['title'];
                $subtitle = $installProgress['label'];
            }
        }

        if($activeTask == '_sprowt_initial_config_install_batch') {
            $batch = \Drupal::state()->get('sprowt.config_batch');
            if(!empty($batch)) {
                $installProgress = SprowtBaseInstallUtil::getBatchInfo($batch);
                if (!empty($installProgress) && !empty($installProgress['success'])) {
                    $progressPart = $installProgress['percentage'] / 10;
                    $progress = $progress + $progressPart;
                    $title = $installProgress['title'];
                    $subtitle = $installProgress['label'];
                }
                $return = [
                    'progress' => $progress,
                    'title' => $title,
                    'subtitle' => $subtitle
                ];
                \Drupal::state()->set('sprowt.base_install_progress.last_return', $return);
            }
            else {
                $lastReturn = \Drupal::state()->get('sprowt.base_install_progress.last_return');
                if(!empty($lastReturn)) {
                    return $lastReturn;
                }
            }
        }

        return [
            'progress' => $progress,
            'title' => $title,
            'subtitle' => $subtitle
        ];
    }
}
