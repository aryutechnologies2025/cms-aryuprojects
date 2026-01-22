<?php

namespace Drupal\sprowt_settings\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkbox;
use Drupal\sprowt_settings\SprowtSettings;
/**
 * Provides a render element for this checkbox element.
 * @\Drupal\Core\Render\Annotation\FormElement("sprowt_settings_checkbox")
 */
class SprowtSettingsCheckbox extends Checkbox
{

    public function getInfo()
    {
        $info = parent::getInfo();
        $info['#sprowt_setting'] = null;
        $info['#negate'] = false;
        return $info;
    }

    public static function sprowtSettingEnabled($element)
    {
        // Check if the element has a sprowt setting defined.
        $settingKey = $element['#sprowt_setting'] ?? null;
        // if the setting key is empty, return true. And act like a normal checkbox.
        if(empty($settingKey)) {
            return true;
        }

        /** @var SprowtSettings $service */
        $service = \Drupal::service('sprowt_settings.manager');
        // Check if the setting is enabled.
        $isEnabled = $service->getSetting($settingKey);
        $negate = $element['#negate'] ?? false;
        if(!empty($negate)) {
            $isEnabled = empty($isEnabled);
        }
        return !empty($isEnabled);
    }

    public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
        if(static::sprowtSettingEnabled($element)) {
            return parent::valueCallback($element, $input, $form_state);
        }
        return null;
    }

    public static function processCheckbox(&$element, FormStateInterface $form_state, &$complete_form)
    {
        $element = parent::processCheckbox($element, $form_state, $complete_form);
        if(!static::sprowtSettingEnabled($element)) {
            $element['#access'] = FALSE;
        }
        return $element;
    }
}
