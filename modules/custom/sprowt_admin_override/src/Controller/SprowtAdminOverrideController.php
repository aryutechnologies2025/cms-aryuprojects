<?php

declare(strict_types=1);

namespace Drupal\sprowt_admin_override\Controller;

use Drupal;
use Drupal\Component\Diff\Diff;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for Sprowt Admin Override routes.
 */
class SprowtAdminOverrideController extends ControllerBase {


    public function splitDiff($splitName, $configName)
    {
        $configSplitConfig = \Drupal::config('config_split.config_split.' . $splitName);
        $splitFolder = $configSplitConfig->get('folder');
        $syncfolder = DRUPAL_ROOT . '/config/sync';
        $fileName = $configName . '.yml';
        $splitFile = $splitFolder . '/' . $fileName;
        $syncFile = $syncfolder . '/' . $fileName;
        if(!file_exists($splitFile)) {
            $splitLines = ['No split'];
        }
        else {
            $splitLines = explode("\n", file_get_contents($splitFile));
            array_filter($splitLines);
        }
        if(!file_exists($syncFile)) {
            $syncLines = ['Split added'];
        }
        else {
            $syncLines = explode("\n", file_get_contents($syncFile));
            array_filter($syncLines);
        }
        $diff = new Diff($splitLines, $syncLines);
        /** @var Drupal\Core\Diff\DiffFormatter $diffFormatter */
        $diffFormatter = Drupal::service('diff.formatter');

        $build = [];

        $build['#title'] = $this->t('Split for config: @config and split: @split', ['@config' => $configName, '@split' => $splitName]);
        // Add the CSS for the inline diff.
        $build['#attached']['library'][] = 'system/diff';

        $build['diff'] = [
            '#type' => 'table',
            '#attributes' => [
                'class' => ['diff'],
            ],
            '#header' => [
                ['data' => $this->t('Split'), 'colspan' => '2'],
                ['data' => $this->t('Sync'), 'colspan' => '2'],
            ],
            '#rows' => $diffFormatter->format($diff),
        ];

        return $build;
    }

    public function webformSubmissionsDownloadCsv() {
        $listBuilder = static::getListBuilderForWebformSubmissions();
        $submissions = $listBuilder->load();
        $headers = $listBuilder->buildHeader();

        $batchBuilder = new \Drupal\Core\Batch\BatchBuilder();
        $batchBuilder->setTitle($this->t('Creating CSV file'));
        $batchBuilder->setFinishCallback([static::class, 'csvBatchEnd']);
        $submissionIds = [];
        foreach ($submissions as $submission) {
            $submissionIds[] = $submission->id();
        }
        $chunks = array_chunk($submissionIds, 100);
        foreach ($chunks as $chunk) {
            $batchBuilder->addOperation([static::class, 'saveCsvRows'], [$chunk, $headers]);
        }

        $batchBuilder->setProgressive(false);

        batch_set($batchBuilder->toArray());
        return batch_process();
    }

    public static function getListBuilderForWebformSubmissions() {
        /** @var \Drupal\sprowt_antispam\AntispamWebformSubmissionListBuilder $listBuilder */
        $listBuilder = \Drupal::entityTypeManager()->getListBuilder('webform_submission');
        $storage = $listBuilder->getStorage();
        $reflection = new \ReflectionClass($listBuilder);
        $limitProperty = $reflection->getProperty('limit');
        $limitProperty->setAccessible(true);
        $limitProperty->setValue($listBuilder, 0);

        $columns = $storage->getSubmissionsColumns();

        $columnProperty = $reflection->getProperty('columns');
        $columnProperty->setAccessible(true);
        $columnProperty->setValue($listBuilder, $columns);

        $headerProperty = $reflection->getProperty('header');
        $headerProperty->setAccessible(true);
        $headerProperty->setValue($listBuilder, null);
        return $listBuilder;
    }

    public static function arrayToCsvRow($array) {
        $encode = function($value) {
            $value = str_replace('\\"','"',$value);
            $value = str_replace('"','\"',$value);
            return '"'.$value.'"';
        };
        return implode(',', array_map($encode, $array));
    }

    public static function saveCsvRows($submissionIds, $headers, &$context) {
        $results = &$context['results'];
        if(empty($results)) {
            $results = [];
        };
        if(empty($results['headerAdded'])) {
            $headerRow = [];
            foreach ($headers as $headerKey => $header) {
                if($headerKey == 'operations') {
                    continue;
                }
                $headerRow[] = (string) $header['data'];
            }
            $headerRow[] = 'Link';
            $results['csv'] = [$headerRow];
            $results['headerAdded'] = true;
        }
        $listBuilder = static::getListBuilderForWebformSubmissions();
        $storage = $listBuilder->getStorage();
        $columns = $storage->getSubmissionsColumns();
        foreach ($submissionIds as $submissionId) {
            $submission = $storage->load($submissionId);
            /** @var \Drupal\sprowt_antispam\AntispamWebformSubmissionListBuilder $listBuilder */
            $tableRow = [];
            foreach ($columns as $column_name => $column) {
                $tableRow[$column_name] = $listBuilder->buildRowColumn($column, $submission);
            }
            /** @var Url $viewUrl */
            $viewUrl = $tableRow["operations"]["data"]["#links"]["view"]["url"];
            $viewUrl = $viewUrl->setOption('query', [])->setAbsolute()->toString();

            $row = [];
            foreach ($headers as $headerKey => $header) {
                if ($headerKey == 'operations') {
                    continue;
                }
                $value = $tableRow[$headerKey];
                if ($value instanceof Link) {
                    $value = $value->getText();
                }
                $row[] = (string)$value;
            }
            $row[] = $viewUrl;
            $results['csv'][] = $row;
        }
    }

    public static function csvBatchEnd($success, $results, $operations) {
        if ($success) {
            $data = $results['csv'];
            $str = '';
            foreach($data as $row) {
                $str .= static::arrayToCsvRow($row) . "\n";
            }

            $message = 'CSV file created.';
            $file = 'public://webform_submissions.csv';
            $fs = \Drupal::service('file_system');
            $fs->saveData($str, $file, FileExists::Replace);
            $url = \Drupal::service('file_url_generator')->generate($file);
            $message .= '<br><a href="' . $url->toString() . '" download>Download CSV</a>';
        }
        else {
            $message = 'There was an error creating the CSV file.';
        }
        \Drupal::messenger()->addMessage(Markup::create('<span>' . $message . '</span>'));
    }

}
