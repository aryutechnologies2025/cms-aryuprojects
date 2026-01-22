<?php


namespace Drupal\inline_block_content\EventSubscriber;




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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigImportSubscriber implements EventSubscriberInterface
{

    /** @var EntityTypeManagerInterface  */
    protected $entityTypeManager;

    /** @var LayoutTempstoreRepositoryInterface  */
    protected $layoutTempstoreRepository;

    /** @var SectionStorageManagerInterface  */
    protected $sectionStorageManager;

    /** @var InlineBlockContentService  */
    protected $inlineBlockContentService;

    /** @var ConfigManagerInterface  */
    protected $configManager;

    /** @var StorageInterface  */
    protected $syncStorage;

    protected $isImporting = false;

    protected $exportSites = [
        'sprowt3-core',
        'sprowt3dev'
    ];

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
        SectionStorageManagerInterface $sectionStorageManager,
        InlineBlockContentService $inlineBlockContentService,
        ConfigManagerInterface $configManager,
        StorageInterface $syncStorage
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->layoutTempstoreRepository = $layoutTempstoreRepository;
        $this->sectionStorageManager = $sectionStorageManager;
        $this->inlineBlockContentService = $inlineBlockContentService;
        $this->configManager = $configManager;
        $this->syncStorage = $syncStorage;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() {
        return [
            ConfigEvents::SAVE => 'onSave',
            ConfigEvents::STORAGE_TRANSFORM_EXPORT => 'onExport',
            ConfigEvents::IMPORT => 'onConfigImport'
        ];
    }

    public function onSave(ConfigCrudEvent $event) {
        $config = $event->getConfig();
        $name = $config->getName();
        if(preg_match('/^core\.entity_view_display\.([^\.]+)\.([^\.]+)\.([^\.]+)/', $name, $matches)) {
            $settings = $config->get('third_party_settings') ?? [];
            if(!is_array($settings)) {
                $settings = [];
            }
            if(empty($settings['layout_builder'])) {
                $testSettings = $settings;
                if(!empty($testSettings['inline_block_content'])) {
                    unset($testSettings['inline_block_content']);
                }
                if(isset($settings['sprowt_inline_content_revision'])) {
                    unset($settings['sprowt_inline_content_revision']);
                }
                if(empty($testSettings)) {
                    $data = $config->getRawData();
                    unset($data['third_party_settings']);
                    $config->setData($data);
                    $data = $config->getRawData();
                    $config->getStorage()->write($name, $data);
                }
                $dependencies = $config->get('dependencies');
                if(!empty($dependencies['module']) && in_array('inline_block_content', $dependencies['module'])) {
                    $idx = array_search('inline_block_content', $dependencies['module']);
                    unset($dependencies['module'][$idx]);
                    $dependencies['module'] = array_values($dependencies['module']);
                    $config->set('dependencies', $dependencies);
                    $data = $config->getRawData();
                    $config->getStorage()->write($name, $data);
                }
            }
            else {
                if(!empty($settings['layout_builder']['enabled'])
                    || !empty($settings['sprowt_inline_content_revision'])
                    || !empty($settings['inline_block_content'])
                ) {
                    if (isset($settings['sprowt_inline_content_revision'])) {
                        unset($settings['sprowt_inline_content_revision']);
                    }
                    $moduleSettings = $settings['inline_block_content'] ?? [];
                    $revision = $moduleSettings['sprowt_inline_content_revision'] ?? 0;
                    ++$revision;
                    $moduleSettings['sprowt_inline_content_revision'] = $revision;
                    $settings['inline_block_content'] = $moduleSettings;
                    if (empty($settings['layout_builder']['enabled'])) {
                        unset($settings['inline_block_content']);
                    }
                    if(!isset($settings['inline_block_content'])) {
                        $dependencies = $config->get('dependencies');
                        if(!empty($dependencies['module']) && in_array('inline_block_content', $dependencies['module'])) {
                            $idx = array_search('inline_block_content', $dependencies['module']);
                            unset($dependencies['module'][$idx]);
                            $dependencies['module'] = array_values($dependencies['module']);
                            $config->set('dependencies', $dependencies);
                        }
                    }

                    $config->set('third_party_settings', $settings);
                    $data = $config->getRawData();
                    $config->getStorage()->write($name, $data);
                }
            }
        }
    }

    public function onExport(StorageTransformEvent $event) {
        $comparer = new StorageComparer($this->syncStorage, $event->getStorage());
        $changeList = $comparer->createChangelist()->getChangelist();
        $changes = [];
        foreach($changeList as $op => $names) {
            if($op == 'create' || $op == 'update') {
                $changes = array_merge($changes, $names);
            }
        }

        foreach($changes as $name) {
            if(preg_match('/^core\.entity_view_display\.([^\.]+)\.([^\.]+)\.([^\.]+)/', $name, $matches)) {
                $entityId = $matches[1];
                $bundle = $matches[2];
                $viewMode = $matches[3];
                $entity = $this->entityTypeManager->getStorage('entity_view_display')->load(implode('.', [
                    $entityId,
                    $bundle,
                    $viewMode
                ]));
                if($entity instanceof LayoutBuilderEntityViewDisplay && $entity->isLayoutBuilderEnabled()) {
                    if(isset($_SERVER['SPROWTHQ_SITE_NAME']) && in_array($_SERVER['SPROWTHQ_SITE_NAME'], $this->exportSites)) {
                        $this->inlineBlockContentService->exportInlineBlockContentToFile($entity);
                    }
                }
            }
        }
    }

    public function onConfigImport(ConfigImporterEvent $event) {
        $changeList = $event->getChangelist();
        foreach($changeList as $op => $configs) {
            if($op == 'create' || $op == 'update') {
                foreach($configs as $config) {
                    $matches = [];
                    if(preg_match('/^core\.entity_view_display\.([^\.]+)\.([^\.]+)\.([^\.]+)/', $config, $matches)) {
                        $entityType = $matches[1];
                        $bundle = $matches[2];
                        $viewMode = $matches[3];
                        $this->postEntityViewModeImport($entityType, $bundle, $viewMode);
                    }
                }
            }
        }
        $this->isImporting = false;
    }

    public function postEntityViewModeImport($entityType, $bundle, $viewMode) {
        $id = implode('.', [
            $entityType, $bundle, $viewMode
        ]);
        $entity = $this->entityTypeManager->getStorage('entity_view_display')->load($id);
        if($entity instanceof LayoutBuilderEntityViewDisplay) {
            if($entity->isLayoutBuilderEnabled()) {
                $this->inlineBlockContentService->importInlineBlockContentFromFile($entity);
                $contexts = [];
                $contexts['display'] = EntityContext::fromEntity($entity);
                $storage = $this->sectionStorageManager->load('defaults', $contexts);
                $this->layoutTempstoreRepository->delete($storage);
            }
        }
    }
}
