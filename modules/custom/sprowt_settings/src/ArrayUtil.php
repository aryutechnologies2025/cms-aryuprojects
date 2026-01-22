<?php

namespace Drupal\sprowt_settings;

use Drupal\Component\Utility\NestedArray;

class ArrayUtil extends NestedArray
{

    public static function insertAfterKey(&$array, $after, $value, $key = null)
    {
        if(!is_array($after)) {
            $after = [$after];
        }
        $ref =& $array;
        $last = count($after) - 1;
        foreach($after as $i => $afterKey) {
            if($last === $i) {
                if(array_is_list($ref)) {
                    $newList = [];
                    foreach($ref as $delta => $val) {
                        $newList[] = $val;
                        if($delta == $afterKey) {
                            $newList[] = $value;
                        }
                    }
                }
                else {
                    $newList = [];
                    foreach($ref as $delta => $val) {
                        if(!isset($newList[$delta])) {
                            $newList[$delta] = $val; //in case the new key already exists in the array
                        }
                        if($delta == $afterKey) {
                            if(!isset($key)){ //we'll just have to make up a key
                                $existingKeys = array_keys($ref);
                                $i = 0;
                                $test = $delta . '-' . $i;
                                while(in_array($test, $existingKeys)) {
                                    ++$i;
                                    $test = $delta . '-' . $i;
                                }
                                $key = $test;
                            }
                            $newList[$key] = $value;
                        }
                    }
                }
                $ref = $newList;
                break;
            }
            if(isset($ref[$afterKey])) {
                $ref = &$ref[$afterKey];
            }
        }
    }
}
