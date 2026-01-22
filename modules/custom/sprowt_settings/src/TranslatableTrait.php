<?php

namespace Drupal\sprowt_settings;

/**
 * This trait can be used to implement the TranslatableInterface.
 *
 * A class with this trait will be able to use
 *  $this->t(...);
 * to translate a string (if a translator is available).
 *
 * @package Drupal\backup_migrate\Core\Translation
 */
trait TranslatableTrait {

    /**
     * Wrapper for the t function
     * @param $string
     * @param array $args
     * @param array $options
     * @return \Drupal\Core\StringTranslation\TranslatableMarkup
     */
    public function t($string, $args = [], $options = []) {
        return t($string, $args, $options);
    }

}
