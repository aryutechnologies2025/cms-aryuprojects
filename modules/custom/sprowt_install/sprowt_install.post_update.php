<?php

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\LayoutTempstoreRepository;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorage\SectionStorageManager;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\node\Entity\Node;


/**
 * Remove field_industry from location nodes
 */
function sprowt_install_post_update_remove_location_field_industry(&$sandbox)
{
    $locations = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'branch',
    ]);

    /** @var Node $location */
    foreach($locations as $location) {
        if($location->hasField('field_industry')
            && !$location->get('field_industry')->isEmpty()
        ) {
            $location->set('field_industry', null);
            $location->save();
        }
    }
}


/**
 * Remove extra blocks from blog layouts
 */

function sprowt_install_post_update_remove_blog_blocks(&$sandbox)
{
    $blogs = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'blog',
    ]);

    $removeIds = [
        'field_block:node:blog:field_meta_tags',
        'field_block:node:blog:field_meta_description',
        'field_block:node:blog:field_outline',
        'field_block:node:blog:field_pre_prompt',
        'field_block:node:blog:field_ai_system_user',
    ];

    /** @var LayoutTempstoreRepository $tmpStoreRepository */
    $tmpStoreRepository = \Drupal::service('layout_builder.tempstore_repository');

    /** @var SectionStorageManager $sectionStorageManager */
    $sectionStorageManager = \Drupal::service('plugin.manager.layout_builder.section_storage');

    /** @var Node $blog */
    foreach($blogs as $blog) {
        $saveBlog = false;
        /** @var LayoutSectionItemList $sections */
        $sections = $blog->get(OverridesSectionStorage::FIELD_NAME);
        if ($sections->isEmpty()) {
            continue;
        }
        $newValue = [];
        /**
         * @var int $sectionDelta
         * @var Section $section
         */
        foreach($sections->getSections() as $sectionDelta => $section) {
            $components = $section->getComponents();
            foreach($components as $component) {
                $configuration = $component->get('configuration');
                if(in_array($configuration['id'], $removeIds)) {
                    $section->removeComponent($component->getUuid());
                    $saveBlog = true;
                }
            }
            $newValue[$sectionDelta] = [
                'section' => $section,
            ];
        }
        if($saveBlog) {
            $blog->set(OverridesSectionStorage::FIELD_NAME, $newValue);
            $blog->save();

            $sectionStorage = $sectionStorageManager->load('overrides',[
                'entity' => EntityContext::fromEntity($blog),
                'view_mode' => new Context(new ContextDefinition('string'), 'default'),
            ]);
            $tmpStoreRepository->delete($sectionStorage);
        }
    }
}
