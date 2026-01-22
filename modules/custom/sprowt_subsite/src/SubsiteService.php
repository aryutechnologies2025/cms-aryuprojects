<?php

namespace Drupal\sprowt_subsite;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\node\Entity\Node;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\sprowt_subsite\Plugin\Block\SubsiteMenuBlock;
use Drupal\system\Entity\Menu;

class SubsiteService
{

    /**
     * @var SettingsManager
     */
    protected $settingsManager;

    public function __construct(
        SettingsManager $settingsManager
    ){
        $this->settingsManager = $settingsManager;
    }


    public function getSubsiteHomePageFromSubsite(Node $subsite) {
        /** @var EntityReferenceFieldItemList $itemList */
        $itemList = $subsite->get('field_basic_page');
        $pages = $itemList->referencedEntities();
        /** @var Node $page */
        foreach($pages as $page) {
            $pageType = sprowt_get_field_value($page, 'field_page_type');
            if($pageType == 'home') {
                return $page;
            }
        }

        return null;
    }

    public function getSubsiteHomePageFromNode(Node $node) {
        $subsite = SettingsManager::getSubsiteFromNode($node);
        if(!empty($subsite)) {
            return $this->getSubsiteHomePageFromSubsite($subsite);
        }
        return null;
    }

    public function getSubsiteNodesByPageType($pageType, ?Node $node = null) {
        if(empty($node)) {
            $subsite = SettingsManager::getCurrentNodeSubsite();
        }
        else {
            $subsite = SettingsManager::getSubsiteFromNode($node);
        }
        if(empty($subsite)) {
            return [];
        }
        $nodes = $this->getSubsiteNodes($subsite, [
            'bundles' => ['page']
        ]);
        if(empty($nodes)) {
            return [];
        }
        return array_filter($nodes, function($node) use ($pageType){
           /** @var Node $node */
           $type = $node->field_page_type->value;
           if(!empty($type) && $type == $pageType) {
               return true;
           }
           return false;
        });
    }

    public function getSubsiteNodes(Node $subsite, $filters = []) {
        $where = [
            '(node__field_subsite_multiple.field_subsite_multiple_target = :subsiteUuid
                OR node__field_subsite.field_subsite_target = :subsiteUuid
            )'
        ];
        $params = [
            ':subsiteUuid' => $subsite->uuid()
        ];
        if(!empty($filters['bundles'])) {
            $where[] = "node.type IN (:bundles[])";
            $params[':bundles[]'] = $filters['bundles'];
        }

        $sql = "
            SELECT DISTINCT node.nid
            FROM node
            LEFT JOIN node__field_subsite_multiple on node__field_subsite_multiple.entity_id = node.nid
            LEFT JOIN node__field_subsite on node__field_subsite.entity_id = node.nid
        ";
        $sql .= "\n WHERE " . implode("\nAND\n", $where);
        $nids = \Drupal::database()->query($sql, $params)->fetchCol();
        if(empty($nids)) {
            return [];
        }

        return Node::loadMultiple($nids);
    }

    /**
     * @param PathautoPattern $pattern
     * @param $context
     * @return void
     */
    public function pathAutoPatternAlter(&$pattern, $context) {
        if($context['module'] != 'node') {
            return;
        }
        $patternString = $pattern->getPattern();
        /** @var Node $node */
        $node = $context['data']['node'];
        $homepage = $this->getSubsiteHomePageFromNode($node);
        if(empty($homepage)) {
            return;
        }
        if($homepage->uuid() == $node->uuid()) {
            return;
        }
        if(strpos($patternString, '/') !== 0) {
            $patternString = '/' . $patternString;
        }

        $newPatternStr = $homepage->toUrl()->toString() . $patternString;
        $pattern->setPattern($newPatternStr);
    }

    public function getSubsiteMenuName($originalMenuName, $node = null)
    {
        $fieldDefs = SubsiteMenuBlock::$menuFields;
        $fieldName = null;
        foreach ($fieldDefs as $fName => $def) {
            if(isset($fieldName)) {
                break;
            }
            if($def['main'] == $originalMenuName) {
                $fieldName = $fName;
            }
        }

        $subsite = sprowt_subsite_get_subsite($node);
        if(empty($subsite) || empty($fieldName)) {
            return $originalMenuName;
        }
        /** @var EntityReferenceFieldItemList $list */
        $list = $subsite->get($fieldName);
        if($list->isEmpty()) {
            return $originalMenuName;
        }

        $menus = $list->referencedEntities();
        /** @var Menu $menu */
        $menu = array_shift($menus);
        return $menu->id();
    }

}
