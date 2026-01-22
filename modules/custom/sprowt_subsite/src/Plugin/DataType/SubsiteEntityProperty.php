<?php

namespace Drupal\sprowt_subsite\Plugin\DataType;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\TypedData\TypedData;

class SubsiteEntityProperty extends EntityAdapter
{

    public function getValue()
    {
        if(isset($this->entity)) {
            return $this->entity;
        }

        $item = $this->getParent();
        $uuid = $item->target;
        if($uuid == '_main') {
            return null;
        }

        $storage = \Drupal::entityTypeManager()->getStorage('node');
        try {
            $nodes = $storage->loadByProperties([
                'uuid' => $uuid
            ]);
        }
        catch (\Exception $e) {
            \Drupal::logger('sprowt_subsite')->error('Error loading entity with uuid: ' . $uuid, [
                'exception' => $e
            ]);
            $nodes = [];
        }
        if(!empty($nodes)) {
            $entity = array_shift($nodes);
            $this->entity = $entity;
            return $this->entity;
        }

        return null;
    }
}
