<?php


namespace Drupal\config_single_sync;


use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\Importer\ConfigImporterBatch;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Installer\InstallerKernel;

class SingleConfigImporterBatch extends ConfigImporterBatch
{

    public static function process(ConfigImporter $config_importer, $sync_step, &$context)
    {
        parent::process($config_importer, $sync_step, $context);
        if(!isset($context['results']['config_importer'])) {
            $context['results']['config_importer'] = $config_importer;
        }
    }

    public static function finish($success, $results, $operations)
    {
        $messenger = \Drupal::messenger();
        parent::finish($success, $results, $operations);
        if ($success) {
            if (empty($results['errors'])) {
                /** @var SingleConfigImporter $configImporter */
                $configImporter = $results['config_importer'];
                $imported = [];
                foreach($configImporter->getToImport() as $collection => $ops) {
                    foreach($ops as $op => $configs) {
                        foreach($configs as $name) {
                            if ($collection == StorageInterface::DEFAULT_COLLECTION) {
                                $imported[] = t('@op @name', ['@op' => $op, '@name' => $name]);
                            }
                            else {
                                $imported[] = t('@op @name in @collection', ['@op' => $op, '@name' => $name, '@collection' => $collection]);
                            }
                        }
                    }
                }
                if(!empty($imported)) {
                    $message = [
                        '#type' => 'html_tag',
                        '#tag' => 'ul',
                        '#prefix' => '<p>' . t('The following configuration(s) were imported:') . '</p>'
                    ];
                    foreach($imported as $key => $string) {
                        $message[$key] = [
                            '#type' => 'html_tag',
                            '#tag' => 'li',
                            '#value' => $string
                        ];
                    }
                    $messenger->addStatus($message);
                }
            }
        }
    }

}
