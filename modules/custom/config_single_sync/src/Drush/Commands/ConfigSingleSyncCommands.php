<?php

namespace Drupal\config_single_sync\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\config_single_sync\SingleConfigImporter;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Drush\Commands\DrushCommands;
use Drush\Commands\config\ConfigCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ConfigSingleSyncCommands extends ConfigImportCommands
{


    public function getConfigImporter($storageComparer) {
        return new SingleConfigImporter(
            $storageComparer,
            $this->getEventDispatcher(),
            $this->getConfigManager(),
            $this->getLock(),
            $this->getConfigTyped(),
            $this->getModuleHandler(),
            $this->getModuleInstaller(),
            $this->getThemeHandler(),
            $this->getStringTranslation(),
            $this->getModuleExtensionList()
        );
    }

    /**
     * Granularly import configs from a config directory.
     *
     * @command config-single-sync:import
     *
     * @param string $label A config directory label (i.e. a key in \$config_directories array in settings.php).
     * @param array $options
     *
     * @return bool|void
     * @interact-config-label
     * @option diff Show preview as a diff.
     * @option preview Deprecated. Format for displaying proposed changes. Recognized values: list, diff.
     * @option source An arbitrary directory that holds the configuration files. An alternative to label argument
     * @option partial Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted). No config transformation happens.
     * @aliases cssim,config-single-sync-import
     * @topics docs:deploy
     * @bootstrap full
     *
     * @throws \Drupal\Core\Config\StorageTransformerException
     * @throws \Drush\Exceptions\UserAbortException
     */
    public function import($label = null, $options = ['preview' => 'list', 'source' => self::REQ, 'partial' => false, 'diff' => false])
    {
        // Determine source directory.

        $source_storage_dir = ConfigCommands::getDirectory($label, $options['source']);

        // Prepare the configuration storage for the import.
        if ($source_storage_dir == Path::canonicalize(Settings::get('config_sync_directory'))) {
            $source_storage = $this->getConfigStorageSync();
        } else {
            $source_storage = new FileStorage($source_storage_dir);
        }

        // Determine $source_storage in partial case.
        $active_storage = $this->getConfigStorage();
        if ($options['partial']) {
            $replacement_storage = new StorageReplaceDataWrapper($active_storage);
            foreach ($source_storage->listAll() as $name) {
                $data = $source_storage->read($name);
                $replacement_storage->replaceData($name, $data);
            }
            $source_storage = $replacement_storage;
        } elseif ($this->hasImportTransformer()) {
            // Use the import transformer if it is available. (Drupal ^8.8)
            // Drupal core does not apply transformations for single imports.
            // And in addition the StorageReplaceDataWrapper is not compatible
            // with StorageCopyTrait::replaceStorageContents.
            $source_storage = $this->getImportTransformer()->transform($source_storage);
        }

        $config_manager = $this->getConfigManager();
        $storage_comparer = new StorageComparer($source_storage, $active_storage, $config_manager);


        if (!$storage_comparer->createChangelist()->hasChanges()) {
            $this->logger()->notice(('There are no changes to import.'));
            return;
        }

        if ($options['preview'] == 'list' && !$options['diff']) {
            $change_list = [];
            foreach ($storage_comparer->getAllCollectionNames() as $collection) {
                $change_list[$collection] = $storage_comparer->getChangelist(null, $collection);
            }
            $table = static::configChangesTable($change_list, $this->output());
            $table->render();
        } else {
            $output = ConfigCommands::getDiff($active_storage, $source_storage, $this->output());

            $this->output()->writeln($output);
            $this->io()->warning("List format required for import. Exiting...");
            return;
        }

        if(Drush::affirmative() || Drush::negative()) {
            $this->io()->warning("Command must be interactive. Exiting...");
            return;
        }

        $this->io()->newLine();
        $this->io()->writeln(dt("Selections are comma or space separated numbers or ranges (e.g. 1-3)"));
        $changes = $this->io()->ask(dt("Select changes to import"));
        $idxes = [];
        $changes = str_replace(',', ' ', $changes);
        foreach(preg_split('/[\s]/', $changes) as $change) {
            $change = trim($change);
            if(empty($change)) {
                continue;
            }
            if(strpos($change, '-') !== false) {
                $parts = explode('-', $change);
                $start = $parts[0];
                $end = $parts[1] ?? $start;
                if(is_numeric($start) && is_numeric($end)) {
                    while($start <= $end) {
                        if(!in_array($start, $idxes)) {
                            $idxes[] = $start;
                        }
                        ++$start;
                    }
                }
            }
            else {
                if(is_numeric($change)) {
                    if(!in_array($change, $idxes)) {
                        $idxes[] = $change;
                    }
                }
            }
        }
        if(empty($idxes)) {
            throw new UserAbortException();
        }

        $imports = $this->getImports($idxes, $change_list);
        return drush_op([$this, 'doSingleImport'], $storage_comparer, $imports);
    }


    protected function getImports($idxes, $changeList) {
        $imports = [];
        $map = [];
        $i = 0;
        foreach($changeList as $collection => $ops) {
            foreach($ops as $op => $names) {
                foreach($names as $name) {
                    ++$i;
                    $map[$i] = [
                        'collection' => $collection,
                        'op' => $op,
                        'name' => $name
                    ];
                }
            }
        }
        foreach ($idxes as $idx) {
            $change = $map[$idx] ?? null;
            if(!empty($change)) {
                if(empty($imports[$change['collection']])) {
                    $imports[$change['collection']] = [];
                }
                if(empty($imports[$change['collection']][$change['op']])) {
                    $imports[$change['collection']][$change['op']] = [];
                }
                $imports[$change['collection']][$change['op']][] = $change['name'];
            }
        }

        return $imports;
    }

    public function doSingleImport($storage_comparer, $imports)
    {
        /** @var SingleConfigImporter $configImporter */
        $configImporter = $this->getConfigImporter($storage_comparer);
        $configImporter->addImports($imports);
        if ($configImporter->alreadyImporting()) {
            $this->logger()->warning('Another request may be synchronizing configuration already.');
        } else {
            try {
                // This is the contents of \Drupal\Core\Config\ConfigImporter::import.
                // Copied here so we can log progress.
                if ($configImporter->hasUnprocessedConfigurationChanges()) {
                    $sync_steps = $configImporter->initialize();
                    foreach ($sync_steps as $step) {
                        $context = [];
                        do {
                            $configImporter->doSyncStep($step, $context);
                            if (isset($context['message'])) {
                                $this->logger()->notice(str_replace('Synchronizing', 'Synchronized', (string)$context['message']));
                            }
                        } while ($context['finished'] < 1);
                    }
                    // Clear the cache of the active config storage.
                    $this->getConfigCache()->deleteAll();
                }
                if ($configImporter->getErrors()) {
                    throw new ConfigException('Errors occurred during import');
                } else {
                    $this->logger()->success('The configuration was imported successfully.');
                }
            } catch (ConfigException $e) {
                // Return a negative result for UI purposes. We do not differentiate
                // between an actual synchronization error and a failed lock, because
                // concurrent synchronizations are an edge-case happening only when
                // multiple developers or site builders attempt to do it without
                // coordinating.
                $message = 'The import failed due to the following reasons:' . "\n";
                $message .= implode("\n", $configImporter->getErrors());

                Error::logException(\Drupal::logger('config_import'),  $e);
                throw new \Exception($message);
            }
        }
    }

    /**
     * Build a table of config changes.
     *
     * @param array $config_changes
     *   An array of changes keyed by collection.
     *
     * @return Table A Symfony table object.
     */
    public static function configChangesTable(array $config_changes, OutputInterface $output, $use_color = true)
    {

        $rows = static::getTableRows($config_changes, $use_color);
        $table = new Table($output);
        $table->setHeaders(['Select', 'Collection', 'Config', 'Operation']);
        $table->addRows($rows);
        return $table;
    }


    public static function getTableRows(array $config_changes, $use_color = true) {
        $rows = [];
        $i = 0;
        foreach ($config_changes as $collection => $changes) {
            foreach ($changes as $change => $configs) {
                switch ($change) {
                    case 'delete':
                        $colour = '<fg=white;bg=red>';
                        break;
                    case 'update':
                        $colour = '<fg=black;bg=yellow>';
                        break;
                    case 'create':
                        $colour = '<fg=white;bg=green>';
                        break;
                    default:
                        $colour = "<fg=black;bg=cyan>";
                        break;
                }
                if ($use_color) {
                    $prefix = $colour;
                    $suffix = '</>';
                } else {
                    $prefix = $suffix = '';
                }
                foreach ($configs as $config) {
                    ++$i;
                    $rows[] = [
                        $i,
                        $collection,
                        $config,
                        $prefix . ucfirst($change) . $suffix,
                    ];
                }
            }
        }
        return $rows;
    }
}
