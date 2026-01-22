<?php

namespace Drupal\zipcode_finder\Plugin\Action;

use Drupal\content_import_export\Exporter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\views\ViewExecutable;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\zipcode_finder\Entity\ZipcodeFinder;
use Drupal\zipcode_finder\Entity\ZipcodeFinderLog;
use Drupal\zipcode_finder\Plugin\Field\FieldType\ZipcodeFinderComputedItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a an Export as CSV action.
 *
 * @Action(
 *   id = "zipcode_finder_export_as_csv",
 *   label = @Translation("Export logs as CSV"),
 *   type = "zipcode_finder_log",
 * )
 *
 * @DCG
 * For a simple updating entity fields consider extending FieldUpdateActionBase.
 */
class ExportAsCsv extends ViewsBulkOperationsActionBase
{


    protected $fileNameBase = 'log_export';


    /**
     * {@inheritdoc}
     */
    public function access($entity, AccountInterface $account = NULL, $return_as_object = FALSE)
    {
        $access = AccessResult::allowedIf($account->hasPermission('administer zipcode finder log'));
        return $return_as_object ? $access : $access->isAllowed();
    }

    /**
     * {@inheritdoc}
     */
    public function execute($entity = NULL)
    {
        return $this->executeMultiple([$entity]);
    }

    protected function dateFormatter() {
        if(!isset($this->dateFormatter)) {
            $this->dateFormatter = \Drupal::service('date.formatter');
        }
        return $this->dateFormatter;
    }

    protected function streamWrapperManager() {
        if(!isset($this->streamWrapperManager)) {
            $this->streamWrapperManager = \Drupal::service('stream_wrapper_manager');
        }
        return $this->streamWrapperManager;
    }

    public function defaultEntityExport($entity) {
        if($entity instanceof ZipcodeFinderLog) {
            $row = [];
            /** @var ZipcodeFinderComputedItemList $finderList */
            $finderList = $entity->zipcode_finder;
            $finder = $finderList->entity;
            $finderLink = '--';
            if($finder instanceof ZipcodeFinder) {
                if(!$finder->link->isEmpty()) {
                    /** @var Url $url */
                    $url = $finder->link->first()->getUrl();
                    $url->setAbsolute(true);
                    $finderLink = $url->toString();
                }
            }
            $row['zipcode'] = $entity->id();
            $row['count'] = $entity->count->value;
            $row['destination'] = $finderLink;
            $row['created'] = $this->dateFormatter()->format($entity->get('created')->value);
            $row['changed'] = $this->dateFormatter()->format($entity->getChangedTime());
            return $row;
        }
    }

    public function executeMultipleDefault(array $objects)
    {
        $results = [];
        foreach ($objects as $entity) {
            $results[] = $this->defaultEntityExport($entity);
        }
        $headers = array_keys($results[0]);

        $this->sendToFile($headers, $results);
    }

    public function executeMultiple(array $objects)
    {
        $this->executeMultipleDefault($objects);
    }


    protected function sendToFile($headers, $rows)
    {
        if (!empty($headers) && !empty($rows)) {

            $now = new \DateTime();
            $filename = $this->fileNameBase . '--' . $now->format('YmdHis') . '.csv';

            $wrappers = $this->streamWrapperManager()->getWrappers();
            if (isset($wrappers['private'])) {
                $wrapper = 'private';
            }
            else {
                $wrapper = 'public';
            }

            $destination = $wrapper . '://' . $filename;
            /** @var \Drupal\file\FileRepository $fileRepository */
            $fileRepository = \Drupal::service('file.repository');
            /** @var FileSystem $fileSystem */
            $fileSystem = \Drupal::service('file_system');
            $tmpFile = $fileSystem->tempnam('temporary://', 'file');
            $fp = fopen($tmpFile, 'w');
            fputcsv($fp, $headers);
            foreach ($rows as $row) {
                $csvRow = [];
                foreach($headers as $header) {
                    if(isset($row[$header])) {
                        $csvRow[] = $row[$header];
                    }
                    else {
                        $csvRow[] = '';
                    }
                }
                fputcsv($fp, $csvRow);
            }
            fclose($fp);
            $fileSystem->move($tmpFile, $destination, FileSystemInterface::EXISTS_REPLACE);
            $file = $fileRepository->loadByUri($destination);
            if(!isset($file)) {
                $file = File::create(['uri' => $destination]);
                $file->setOwnerId(\Drupal::currentUser()->id());
            }
            $file->setTemporary();
            $file->save();
            $file_uri_str = $file->createFileUrl();
            if(strpos($file_uri_str, '/') === 0) {
                $file_uri_str = 'internal:/' . ltrim($file_uri_str, '/');
            }
            $file_url = Url::fromUri($file_uri_str);
            $attributes = [
                'target' => '_blank',
                'download' => $filename
            ];
            $file_url->setOption('attributes', $attributes);
            $link = Link::fromTextAndUrl($this->t('Click here'), $file_url);
            $this->messenger()->addStatus($this->t('CSV file created, @link to download.', ['@link' => $link->toString()]));
        }
    }

}
