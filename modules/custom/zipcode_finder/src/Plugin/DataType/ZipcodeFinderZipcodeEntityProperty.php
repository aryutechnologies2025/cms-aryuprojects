<?php

namespace Drupal\zipcode_finder\Plugin\DataType;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\zipcode_finder\Entity\ZipcodeFinder;

class ZipcodeFinderZipcodeEntityProperty extends EntityAdapter
{
    public function getValue()
    {
        if(isset($this->entity)) {
            return $this->entity;
        }

        $item = $this->getParent();
        $zipcode = $item->zipcode->value;
        if(empty($zipcode)) {
            return null;
        }

        $finder = ZipcodeFinder::findByZipcode($zipcode);
        if($finder instanceof ZipcodeFinder) {
            $this->entity = $finder;
            return $this->entity;
        }

        return null;
    }
}
