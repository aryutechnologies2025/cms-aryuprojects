<?php


namespace Drupal\config_single_sync;


use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SingleConfigImporter extends ConfigImporter
{
    use StringTranslationTrait;
    use DependencySerializationTrait;

    protected $toImport = [];


    public function __construct(
        StorageComparerInterface $storage_comparer,
        EventDispatcherInterface $event_dispatcher,
        ConfigManagerInterface $config_manager,
        LockBackendInterface $lock,
        TypedConfigManagerInterface $typed_config,
        ModuleHandlerInterface $module_handler,
        ModuleInstallerInterface $module_installer,
        ThemeHandlerInterface $theme_handler,
        TranslationInterface $string_translation,
        ModuleExtensionList $extension_list_module
    ) {
        parent::__construct(
            $storage_comparer,
            $event_dispatcher,
            $config_manager,
            $lock,
            $typed_config,
            $module_handler,
            $module_installer,
            $theme_handler,
            $string_translation,
            $extension_list_module
        );

        $this->resetImports();
    }

    public function resetImports() {
        $this->toImport = [];
        foreach ($this->storageComparer->getAllCollectionNames() as $collection) {
            $this->toImport[$collection] = [];
            foreach (['delete', 'create', 'rename', 'update'] as $op) {
                $this->toImport[$collection][$op] = [];
            }
        }
    }

    public function addImport($op, $name, $collection = StorageInterface::DEFAULT_COLLECTION) {
        if(!isset($this->toImport[$collection])) {
            $this->toImport[$collection] = [];
        }
        if(!isset($this->toImport[$collection][$op])) {
            $this->toImport[$collection][$op] = [];
        }
        if(!in_array($name, $this->toImport[$collection][$op])) {
            $this->toImport[$collection][$op][] = $name;
        }
    }

    public function addImports($imports) {
        foreach($imports as $collection => $ops) {
            foreach($ops as $op => $configs) {
                foreach($configs as $name) {
                    $this->addImport($op, $name, $collection);
                }
            }
        }
    }

    public function getToImport() {
        return $this->toImport;
    }

    protected function shouldBeImported($name, $op = null, $collection = null) {
        $ops = [];
        $configs = [];
        if(!isset($collection)) {
            $collections = array_keys($this->toImport);
        }
        else {
            $collections = [$collection];
        }

        if(!isset($op)) {
            foreach($collections as $c) {
                $os = is_array($this->toImport[$c]) ? array_keys($this->toImport[$c]) : [];
                $ops = array_merge($ops, $os);
            }
            $ops = array_unique($ops);
        }
        else {
            $ops = [$op];
        }
        foreach($collections as $c) {
            foreach($ops as $o) {
                if(!empty($this->toImport[$c][$o])) {
                    $configs = array_merge($configs, $this->toImport[$c][$o]);
                }
            }
        }


        return in_array($name, $configs);
    }

    protected function createExtensionChangelist()
    {
        // Create an empty changelist.
        $this->extensionChangelist = $this->getEmptyExtensionsProcessedList();

        if($this->shouldBeImported('core.extension')) {
            parent::createExtensionChangelist();
        }
    }

    public function getUnprocessedConfiguration($op, $collection = StorageInterface::DEFAULT_COLLECTION)
    {
        $processed = $this->processedConfiguration[$collection][$op] ?? [];
        $toImport = $this->toImport[$collection][$op] ?? [];
        $diff = array_diff($toImport, $processed);
        return $diff;
    }

    public function import($imports = [])
    {
        $this->resetImports();
        $this->addImports($imports);
        $return = parent::import();
        $this->resetImports();
        return $return;
    }

}
