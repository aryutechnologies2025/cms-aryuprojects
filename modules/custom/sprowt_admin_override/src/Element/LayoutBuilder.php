<?php

namespace Drupal\sprowt_admin_override\Element;

use Drupal\Core\Security\TrustedCallbackInterface;

class LayoutBuilder implements TrustedCallbackInterface
{

    public static function alterLayoutBuilderSections($element)
    {
        $sectionStorage = $element['#section_storage'] ?? null;
        foreach ($element["layout_builder"] as $idx => $layoutBuilderPart) {
            if(isset($layoutBuilderPart['layout-builder__section'])) {
                $sectionBuild = $layoutBuilderPart['layout-builder__section'];
                $delta = $sectionBuild['#attributes']['data-layout-delta'];
                $context = [
                    'section_storage' => $sectionStorage,
                    'delta' => $delta,
                ];
                \Drupal::service('module_handler')->alter('sprowt_admin_override_layout_builder_section', $layoutBuilderPart, $context);
                $element["layout_builder"][$idx] = $layoutBuilderPart;
            }
        }
        return $element;
    }

    public static function trustedCallbacks()
    {
        return [
            'alterLayoutBuilderSections',
        ];
    }

}
