<?php
namespace Drupal\gclid_field\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\Hidden;

/**
 *
 * @WebformElement(
 *   id = "gclid_element",
 *   label = @Translation("GCLID element"),
 *   description = @Translation("Provides a GCLID element."),
 *   category = @Translation("Advanced elements"),
 * )
 *
 * @see \Drupal\webform_example_element\Element\WebformExampleElement
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */

class GCLIDWebformElement extends Hidden
{

    public function form(array $form, FormStateInterface $form_state) {
        $form = parent::form($form, $form_state);
        $form['element']['default_value']['#access'] = false;
        $form["form"]["prepopulate"]['#access'] = false;
        return $form;
    }
}
