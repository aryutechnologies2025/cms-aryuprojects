<?php

namespace Drupal\sprowt_subsite\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemList;

class SubsiteReferenceItemList extends FieldItemList implements EntityReferenceFieldItemListInterface
{
    public function referencedEntities()
    {
        if ($this->isEmpty()) {
            return [];
        }

        $entities = [];
        $uuids = [];
        foreach ($this->list as $delta => $item) {
            if ($item->target !== NULL) {
                if($item->target == '_main') {
                    $entities[$delta] = $item->target;
                }
                else {
                    $uuids[$delta] = $item->target;
                }
            }
        }
        if(!empty($uuids)) {
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
                'uuid' => $uuids
            ]);
            foreach($nodes as $node) {
                $uuid = $node->uuid();
                $delta = array_search($uuid, $uuids);
                $entities[$delta] = $node;
            }
        }

        return $entities;
    }
}
