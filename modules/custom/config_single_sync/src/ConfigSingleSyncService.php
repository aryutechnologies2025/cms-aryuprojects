<?php

namespace Drupal\config_single_sync;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Importer\ConfigImporterBatch;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\example\ExampleInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ConfigSingleSyncService service.
 */
class ConfigSingleSyncService
{
    use DependencySerializationTrait;
    use StringTranslationTrait;
    use MessengerTrait;

    /**
     * The config.storage.sync service.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $configStorageSync;

    /**
     * The active configuration storage.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $storage;

    /**
     * The config.storage.snapshot service.
     *
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $configStorageSnapshot;

    /**
     * The lock.persistent service.
     *
     * @var \Drupal\Core\Lock\LockBackendInterface
     */
    protected $lockPersistent;

    /**
     * The event dispatcher.
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * The configuration manager.
     *
     * @var \Drupal\Core\Config\ConfigManagerInterface
     */
    protected $configManager;

    /**
     * The config.types service.
     *
     * @var \Drupal\Core\Config\TypedConfigManagerInterface
     */
    protected $configTyped;

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * The module_installer service.
     *
     * @var \Drupal\Core\Extension\ModuleInstallerInterface
     */
    protected $moduleInstaller;

    /**
     * The theme handler.
     *
     * @var \Drupal\Core\Extension\ThemeHandlerInterface
     */
    protected $themeHandler;

    /**
     * The renderer.
     *
     * @var \Drupal\Core\Render\RendererInterface
     */
    protected $renderer;

    /**
     * The module extension list.
     *
     * @var \Drupal\Core\Extension\ModuleExtensionList
     */
    protected $extensionListModule;

    /**
     * The config.import_transformer service.
     *
     * @var \Drupal\Core\Config\ImportStorageTransformer
     */
    protected $configImportTransformer;

    /**
     * Constructs a ConfigSingleSyncService object.
     *
     * @param \Drupal\Core\Config\StorageInterface $config_storage_sync
     *   The config.storage.sync service.
     * @param \Drupal\Core\Config\StorageInterface $storage
     *   The active configuration storage.
     * @param \Drupal\Core\Config\StorageInterface $config_storage_snapshot
     *   The config.storage.snapshot service.
     * @param \Drupal\Core\Lock\LockBackendInterface $lock_persistent
     *   The lock.persistent service.
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
     *   The event dispatcher.
     * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
     *   The configuration manager.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $config_typed
     *   The config.types service.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler.
     * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
     *   The module_installer service.
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
     *   The theme handler.
     * @param \Drupal\Core\Render\RendererInterface $renderer
     *   The renderer.
     * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
     *   The module extension list.
     * @param \Drupal\Core\Config\ImportStorageTransformer $config_import_transformer
     *   The config.import_transformer service.
     */
    public function __construct(
        StorageInterface $config_storage_sync,
        StorageInterface $storage,
        StorageInterface $config_storage_snapshot,
        LockBackendInterface $lock_persistent,
        EventDispatcherInterface $event_dispatcher,
        ConfigManagerInterface $config_manager,
        TypedConfigManagerInterface $config_typed,
        ModuleHandlerInterface $module_handler,
        ModuleInstallerInterface $module_installer,
        ThemeHandlerInterface $theme_handler,
        RendererInterface $renderer,
        ModuleExtensionList $extension_list_module,
        ImportStorageTransformer $config_import_transformer
    )
    {
        $this->configStorageSync = $config_storage_sync;
        $this->storage = $storage;
        $this->configStorageSnapshot = $config_storage_snapshot;
        $this->lockPersistent = $lock_persistent;
        $this->eventDispatcher = $event_dispatcher;
        $this->configManager = $config_manager;
        $this->configTyped = $config_typed;
        $this->moduleHandler = $module_handler;
        $this->moduleInstaller = $module_installer;
        $this->themeHandler = $theme_handler;
        $this->renderer = $renderer;
        $this->extensionListModule = $extension_list_module;
        $this->configImportTransformer = $config_import_transformer;
    }

    /**
     * Method description.
     */
    public function getConfigImporter($storageComparer)
    {
        return new SingleConfigImporter(
            $storageComparer,
            $this->eventDispatcher,
            $this->configManager,
            $this->lockPersistent,
            $this->configTyped,
            $this->moduleHandler,
            $this->moduleInstaller,
            $this->themeHandler,
            $this->getStringTranslation(),
            $this->extensionListModule
        );
    }

    public function syncFormAlter(&$form, FormStateInterface $formState) {
        $storageComparer = $formState->get('storage_comparer');
        if(isset($storageComparer)) {
            foreach ($storageComparer->getAllCollectionNames() as $collection) {
                foreach ($storageComparer->getChangelist(NULL, $collection) as $config_change_type => $config_names) {
                    if (isset($form[$collection][$config_change_type]['list'])) {
                        $table = &$form[$collection][$config_change_type]['list'];
                        $table['#tableselect'] = true;
                        $rows = $table['#rows'] ?? [];
                        $newRows = [];
                        foreach ($rows as $row) {
                            $key = implode('::', [
                                'singleImport',
                                $collection,
                                $config_change_type,
                                $row['name']
                            ]);
                            $newRows[] = array_merge([
                                'select' => [
                                    'data' => [
                                        '#type' => 'checkbox',
                                        '#id' => Html::getUniqueId('edit-' . implode('-', [
                                                $collection, $config_change_type, 'list'
                                            ])),
                                        '#return_value' => $key,
                                        '#name' => 'singleImport[]',
                                        '#wrapper_attributes' => [
                                            'class' => [
                                                'table-select',
                                            ],
                                        ]
                                    ]
                                ]
                            ], $row);
                        }
                        if (!empty($newRows)) {
                            $table['#rows'] = $newRows;
                        }
                    }
                }
            }

            //switch submit function to import button
            $formObj = $formState->getFormObject();
            $form['actions']['submit']['#submit'] = [
                [$formObj, 'submitForm']
            ];
            $form['#submit'] = [];

            $form['actions']['submitIndividual'] = [
                '#type' => 'submit',
                '#value' => $this->t('Import selected'),
                '#submit' => [
                    [$this, 'submitSync']
                ]
            ];
        }
    }

    public function submitSync($form, FormStateInterface $formState) {
        $storageComparer = $formState->get('storage_comparer');
        $configImporter = $this->getConfigImporter($storageComparer);

        $input = $formState->getUserInput();
        $singleImports = $input['singleImport'] ?? [];
        foreach($singleImports as $singleImport) {
            $parts = explode('::', $singleImport);
            $collection = $parts[1];
            $op = $parts[2];
            $name = $parts[3];
            $configImporter->addImport($op, $name, $collection);
        }

        $stop = true;

        if ($configImporter->alreadyImporting()) {
            $this->messenger()->addStatus($this->t('Another request may be synchronizing configuration already.'));
        }
        else {
            try {
                $sync_steps = $configImporter->initialize();
                $batch = [
                    'operations' => [],
                    'finished' => [SingleConfigImporterBatch::class, 'finish'],
                    'title' => $this->t('Synchronizing configuration'),
                    'init_message' => $this->t('Starting configuration synchronization.'),
                    'progress_message' => $this->t('Completed step @current of @total.'),
                    'error_message' => $this->t('Configuration synchronization has encountered an error.'),
                ];
                foreach ($sync_steps as $sync_step) {
                    $batch['operations'][] = [[SingleConfigImporterBatch::class, 'process'], [$configImporter, $sync_step]];
                }

                batch_set($batch);
            }
            catch (ConfigImporterException $e) {
                // There are validation errors.
                $this->messenger()->addError($this->t('The configuration cannot be imported because it failed validation for the following reasons:'));
                foreach ($configImporter->getErrors() as $message) {
                    $this->messenger()->addError($message);
                }
            }
        }
    }
}
