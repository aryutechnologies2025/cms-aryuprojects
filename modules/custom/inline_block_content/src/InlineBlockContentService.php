<?php

namespace Drupal\inline_block_content;

use Drupal\block_content\Access\RefinableDependentAccessInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\content_import_export\Exporter;
use Drupal\content_import_export\Importer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\InlineBlockUsage;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionComponent;

/**
 * InlineBlockContentService service.
 */
class InlineBlockContentService
{

    use LayoutEntityHelperTrait;

    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var Exporter
     */
    protected $exporter;

    /**
     * @var Importer
     */
    protected $importer;

    /**
     * @var FileSystemInterface
     */
    protected $fileSystem;

    /**
     * @var InlineBlockUsage
     */
    protected $inlineBlockUsage;

    protected $contentDirectory = DRUPAL_ROOT . '/content/inline_block_content';

    /**
     * Constructs an InlineBlockContentService object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     */
    public function __construct(
        EntityTypeManagerInterface $entity_type_manager,
        Exporter $exporter,
        Importer $importer,
        FileSystemInterface $fileSystem,
        InlineBlockUsage $inlineBlockUsage
    ) {
        $this->entityTypeManager = $entity_type_manager;
        $this->exporter = $exporter;
        $this->importer = $importer;
        $this->fileSystem = $fileSystem;
        $this->inlineBlockUsage = $inlineBlockUsage;
    }

    /**
     * Method description.
     */
    public function getEntityViewDisplayExportConfigs(LayoutBuilderEntityViewDisplay $entityViewDisplay)
    {
        if(!$entityViewDisplay->isLayoutBuilderEnabled()) {
            return [];
        }

        $exports = [];

        /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
        $entityTypeManager = $this->entityTypeManager;
        $sections = $entityViewDisplay->getThirdPartySetting('layout_builder', 'sections');

        /** @var \Drupal\layout_builder\Section $section */
        foreach($sections as $section) {
            $components = $section->getComponents();
            /** @var \Drupal\layout_builder\SectionComponent $component */
            foreach($components as $component) {
                $config = $component->get('configuration');
                if(strpos($config['id'], 'inline_block:') === 0) {
                    $block = null;
                    if(!empty($config['block_serialized'])) {
                        $block = unserialize($config['block_serialized']);
                    }
                    if(empty($block) && !empty($config['block_revision_id'])) {
                        $block = $entityTypeManager->getStorage('block_content')->loadRevision($config['block_revision_id']);
                    }
                    if(empty($block) && !empty($config['uuid'])) {
                        $entities = $entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $config['uuid']]);
                        $block = is_array($entities) ? array_pop($entities) : $entities;
                    }
                    if(!empty($block)) {
                        $exports[] = [
                            'entity' => $block
                        ];
                    }
                }
            }
        }

        return $exports;
    }


    public function exportInlineBlockContentToFile(LayoutBuilderEntityViewDisplay $entityViewDisplay) {
        $this->fileSystem->prepareDirectory($this->contentDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $exports = $this->getEntityViewDisplayExportConfigs($entityViewDisplay);
        if(!empty($exports)) {
            $yml = $this->exporter->export($exports, 'yaml');
            $filename = $entityViewDisplay->id() . '.inline_block_content.yml';
            $fileLocation = $this->contentDirectory . '/' . $filename;
            $this->fileSystem->saveData($yml, $fileLocation, FileSystemInterface::EXISTS_REPLACE);
        }
    }

    public function importInlineBlockContentFromFile(LayoutBuilderEntityViewDisplay $entityViewDisplay) {
        $filename = $entityViewDisplay->id() . '.inline_block_content.yml';
        $fileLocation = $this->contentDirectory . '/' . $filename;
        if(file_exists($fileLocation)) {
            $yml = file_get_contents($fileLocation);
            $entities = $this->importer->import($yml, false, true);
            $entityMap = [];
            foreach($entities as $entity) {
                $entityMap[$entity->uuid()] = $entity;
                $usage = $this->inlineBlockUsage->getUsage($entity->id());
                if(empty($usage)) {
                    $this->inlineBlockUsage->addUsage($entity->id(), $entityViewDisplay);
                }
            }
        }
    }

    public function loadInlineBlockFromComponent(SectionComponent $component)
    {
        $configuration = $component->get('configuration');
        $pluginId = $configuration['id'];
        if(strpos($pluginId, 'inline_block:') !== 0) {
            return null;
        }
        if (!empty($configuration['block_serialized'])) {
            return  unserialize($configuration['block_serialized']);
        }
        elseif (!empty($configuration['block_revision_id'])) {
            $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id']);
            if(isset($entity) && isset($configuration['uuid']) && $entity->uuid() == $configuration['uuid']) {
                return $entity;
            }
        }
        if (isset($configuration['type']) && isset($configuration['uuid'])) {
            $entity = $this->entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $configuration['uuid']]);
            return is_array($entity) ? array_pop($entity) : $entity;
        }

        return null;
    }

    public function getEntityFromInlineBlockContent(BlockContent $blockContent) {
        $layout_entity_info = $this->inlineBlockUsage->getUsage($blockContent->id());
        if (empty($layout_entity_info)) {
            // If the block does not have usage information then we cannot set a
            // dependency. It may be used by another module besides layout builder.
            return NULL;
        }
        if(empty($layout_entity_info->layout_entity_type)) {
            return null;
        }
        $layout_entity_storage = $this->entityTypeManager->getStorage($layout_entity_info->layout_entity_type);
        /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $layout_entity */
        $layout_entity = $layout_entity_storage->load($layout_entity_info->layout_entity_id);
        if ($this->isLayoutCompatibleEntity($layout_entity)) {
            $blockExists = false;
            if($layout_entity instanceof LayoutBuilderEntityViewDisplay) {
                $sections = $layout_entity->getSections();
            }
            else {
                $layout = $layout_entity->get('layout_builder__layout');
                $sections = $layout->getSections();
            }
            /** @var \Drupal\layout_builder\Section $section */
            foreach($sections as $section) {
                if($blockExists) {
                    break;
                }
                $components = $section->getComponents();
                foreach ($components as $component) {
                    if($blockExists) {
                        break;
                    }
                    $config = $component->get('configuration');
                    if(strpos($config['id'], 'inline_block:') === 0) {
                        if(isset($config['uuid']) && $config['uuid'] == $blockContent->uuid()) {
                            $blockExists = true;
                        }
                    }
                }
            }
            if($blockExists) {
                return $layout_entity;
            }
        }
        return NULL;
    }
}
