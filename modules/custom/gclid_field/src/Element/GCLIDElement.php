<?php
namespace Drupal\gclid_field\Element;

use Drupal\Core\Render\Annotation\FormElement;
use Drupal\Core\Render\Element\Hidden;
use Drupal\Core\Render\Element;

/**
 * @FormElement("gclid_element")
 */
class GCLIDElement extends Hidden
{
    public static function preRenderHidden($element) {
        $element = parent::preRenderHidden($element);
        $element['#attributes']['data-gclid'] = 'gclid';
        $element['#attached']['library'][] = 'gclid_field/field';
        return $element;
    }
}
