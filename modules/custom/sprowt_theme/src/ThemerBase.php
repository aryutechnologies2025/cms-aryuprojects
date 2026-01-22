<?php

namespace Drupal\sprowt_theme;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_styles\Entity\LayoutBuilderStyle;
use Drupal\layout_builder_styles\LayoutBuilderStyleInterface;
use Drupal\node\Entity\Node;

class ThemerBase
{

    protected string $theme;

    protected $node;

    /**
     * @var SqlContentEntityStorage
     */
    protected $blockContentStorage;

    /**
     * @var SectionStorageManagerInterface
     */
    protected $sectionStorageManager;

    /**
     * @var Uuid
     */
    protected $uuid;


    protected $layoutArray;

    public function __construct($theme) {
        $this->theme = $theme;
    }

    /**
     * @return Uuid
     */
    public function uuid() {
        if(isset($this->uuid)) {
            return $this->uuid;
        }
        $this->uuid = \Drupal::service('uuid');
        return $this->uuid;
    }

    /**
     * @return \Drupal\Core\Entity\EntityStorageInterface|SqlContentEntityStorage
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function blockContentStorage() {
        if(isset($this->blockContentStorage)) {
            return $this->blockContentStorage;
        }
        $this->blockContentStorage = \Drupal::entityTypeManager()->getStorage('block_content');
        return $this->blockContentStorage;
    }

    public function sectionStorageManager() {
        if(isset($this->sectionStorageManager)) {
            return $this->sectionStorageManager;
        }
        $this->sectionStorageManager = \Drupal::service('plugin.manager.layout_builder.section_storage');
        return $this->sectionStorageManager;
    }

    public function updateSection($type, Section $section) {
        return $section;
    }

    public function layoutSections() {
        if(isset($this->layoutArray)) {
            return $this->layoutArray;
        }
        if(!$this->node instanceof Node) {
            return false;
        }

        /** @var LayoutSectionItemList $layout */
        $layout = $this->node->get('layout_builder__layout');
        $layoutArray = $layout->getSections();
        $this->layoutArray = $layoutArray;
        $this->keyMap = [];
        foreach($layoutArray as $key => $section) {
            $mapKey = json_encode($section->toArray());
            $this->keyMap[$mapKey] = $key;
        }
        return $layoutArray;
    }

    public function sectionKey(Section $section) {
        $mapKey = json_encode($section->toArray());
        return $this->keyMap[$mapKey] ?? null;
    }

    public function previousSection(Section $section) {
        $layoutArray = $this->layoutSections();
        $sectionKey = $this->sectionKey($section);
        $prevKey = $sectionKey - 1;
        if($prevKey >= 0) {
            return $layoutArray[$prevKey];
        }
        return null;
    }

    public function nextSection(Section $section) {
        $layoutArray = $this->layoutSections();
        $sectionKey = $this->sectionKey($section);
        $nextKey = $sectionKey + 1;
        if($nextKey < count($layoutArray)) {
            return $layoutArray[$nextKey];
        }
        return null;
    }

    public function sectionHasComponents(Section $section) {
        $components = $section->getComponents();
        return !empty($components);
    }

    public function updateLayout($node) {
        $this->node = $node;
        $this->layoutArray = null;
        $sectionStorage = $this->sectionStorageManager()->load('overrides',[
            'entity' => EntityContext::fromEntity($node),
            'view_mode' => new Context(new ContextDefinition('string'), 'full'),
        ]);
        $layout = $node->get('layout_builder__layout');
        foreach($layout as $key => $item) {
            $section = $item->section;
            $type = $this->sectionType($section);
            $newSection = $this->updateSection($type, $section);
            $item->setValue([
                'section' => $newSection
            ]);
            $layout->set($key, $item);
        }
        $node->set('layout_builder__layout', $layout->getValue());
        return $node;
    }

    public function sectionType(Section $section) {
        $settings = $section->getLayoutSettings();
        $id = $settings['layout_builder_id'] ?? '';
        return $id;
    }

    public function loadInlineBlock($component) {
        if($component instanceof SectionComponent) {
            $component = $component->toArray();
        }
        $config = $component['configuration'];
        if(strpos($config['id'], 'inline_block') !== 0) {
            throw new SprowtThemeException('Component not an inline block!');
        }
        if(!empty($config['block_revision_id'])) {
            $block = $this->blockContentStorage()->loadRevision($config['block_revision_id']);
        }
        else {
            $block = $this->blockContentStorage()->loadByProperties([
                'uuid' => $config['uuid']
            ]) ?? null;
            if(!empty($block)) {
                $block = array_shift($block);
            }
        }
        return $block;
    }

    public function sectionHasDivider(Section $section) {
        $components = $section->getComponents();
        /** @var SectionComponent $component */
        foreach($components as $component) {
            $array = $component->toArray();
            $config = $array['configuration'];
            if($config['id'] == 'inline_block:divider') {
                return $this->loadInlineBlock($component);
            }
        }
        return false;
    }

    public function createDividerBlock($type, $bg = 'light', $accent = 'secondary') {
        $block = BlockContent::create([
            'type' => 'divider',
            'info' => 'section-divider',
            'field_divider_style' => [
                'value' => $type
            ],
            'field_divider_bg_color' => [
                'value' => $bg
            ],
            'field_accent_color' => [
                'value' => $accent
            ]
        ]);
        return $block;
    }

    public function sectionBackground(Section $section) {
        $bg = 'white';
        $settings = $section->getLayoutSettings();
        $styles = $settings['layout_builder_styles_style'] ?? [];
        if(!is_array($styles)) {
            $styles = [$styles];
        }
        $styles = array_filter($styles, function($style) {
            return !empty($style);
        });
        if(!empty($styles)) {
            $styleEntities = LayoutBuilderStyle::loadMultiple($styles);
            /** @var LayoutBuilderStyle $styleEntity */
            foreach ($styleEntities as $styleEntity) {
                $group = $styleEntity->getGroup();
                if($group == 'bgcolor') {
                    $bg = $styleEntity->id();
                }
            }
        }
        switch($bg) {
            case 'primary_color':
            case 'secondary_color':
                $bg = str_replace('_color', '', $bg);
        }
        $fieldInfo = FieldStorageConfig::loadByName('block_content', 'field_divider_bg_color');
        $fieldSettings = $fieldInfo->getSettings();
        //invalid value. Set to white;
        if(!in_array($bg, array_keys($fieldSettings['allowed_values']))) {
            $bg = 'white';
        }
        return $bg;
    }

    public function updateDivider($type, Section $section) {
        if(empty($type)) {
            $section = $this->removeDividerFromSection($section);
        }
        else {
            $section = $this->addDividerBlockToSection($type, $section);
        }
        return $section;
    }

    public function addDividerBlockToSection($type, Section $section) {
        /** @var BlockContent $divider */
        $divider = $this->sectionHasDivider($section);
        $add = false;
        if(empty($divider)) {
            $add = true;
            $divider = $this->createDividerBlock($type, 'white');
        }
        else {
            $divider->set('field_divider_style', [
                'value' => $type
            ]);
        }
        $nextSection = $this->nextSection($section);
        while(!empty($nextSection) && !$this->sectionHasComponents($nextSection)) {
            $nextSection = $this->nextSection($nextSection);
        }
        if(!empty($nextSection)) {
            $bg = $this->sectionBackground($nextSection);
            $divider->set('field_divider_bg_color', [
                'value' => $bg
            ]);
        }
        $section = $this->saveInlineBlock($divider, $section);
        if(!$add) {
            return $section;
        }
        $layout = $section->getLayout();
        $region = $layout->getPluginDefinition()->getDefaultRegion();
        $config = [
            'id' => 'inline_block:divider',
            'label' => $divider->label(),
            'label_display' => false,
            'provider' => 'layout_builder',
            'view_mode' => 'default',
            'block_revision_id' => $divider->getRevisionId(),
            'block_serialized' =>null,
            'context_mapping' => [],
            'type' => 'divider',
            'uuid' => $divider->uuid()
        ];
        $component = new SectionComponent(
            $this->uuid()->generate(),
            $region,
            $config
        );
        $componentArray = $component->toArray();
        $sectionArray = $section->toArray();
        $weight = count($sectionArray['components']);
        foreach ($sectionArray['components'] as $sectionComponent) {
            if($sectionComponent['weight'] >= $weight) {
                $weight = $sectionComponent['weight'] + 1;
            }
        }
        $componentArray['weight'] = $weight;
        $sectionArray['components'][] = $componentArray;
        return Section::fromArray($sectionArray);
    }

    public function removeDividerFromSection(Section $section) {
        $divider = $this->sectionHasDivider($section);
        if(!empty($divider)) {
            if($divider->isDefaultRevision()) {
                $divider->delete();
            }
            else {
                $this->blockContentStorage()->deleteRevision($divider->getRevisionId());
            }
        }
        else {
            return $section;
        }
        $sectionArray = $section->toArray();
        $components = $sectionArray['components'];
        $newComponents = [];
        foreach($components as $component) {
            $config = $component['configuration'] ?? [];
            if($config['id'] != 'inline_block:divider') {
                $newComponents[] = $component;
            }
        }
        $sectionArray['components'] = $newComponents;
        return Section::fromArray($sectionArray);
    }

    public function saveInlineBlock(BlockContent $block, Section $section) {
        $block->setNewRevision(false);
        //workaround for a bug mentioned here: https://www.drupal.org/project/drupal/issues/2859042#comment-13083066
        $block->original = $this->blockContentStorage()->loadRevision($block->getRevisionId());
        $block->save();
        return $section;
    }

}
