<?php

namespace Drupal\sprowt_admin_override;

use Drupal\gin\GinContentFormHelper;
use Drupal\gin\GinDescriptionToggle;
use Drupal\gin\GinSettings;

class SprowtAdminOverrideDescriptionToggle extends GinDescriptionToggle
{

    public function __construct() {
        $classResolver = \Drupal::service('class_resolver');
        parent::__construct(
            $classResolver->getInstanceFromDefinition(GinSettings::class),
            $classResolver->getInstanceFromDefinition(GinContentFormHelper::class)
        );
    }

    function isEnabled() {
        $toggled = $this->ginSettings->get('show_description_toggle');
        if(empty($toggled)) {
            return false;
        }
        $contentForm = $this->contentFormHelper->isContentForm();
        if(!empty($contentForm)) {
            return true;
        }

        return $this->shouldHaveToggles();
    }

    function preprocess(array &$variables)
    {
        parent::preprocess($variables);
        //turn off toggles if explicitely told to
        if(isset($variables["element"]["#description_toggle"])
            && empty($variables["element"]["#description_toggle"])
            && !empty($variables['description_toggle'])
        ) {
            $variables['description_display'] = $variables["element"]["#description_display"] ?? 'after';
            $variables['description_toggle'] = false;
        }
    }

    function shouldHaveToggles() {
        $routes = [];
        $layoutRoutes = [
            'layout_builder.choose_block',
            'layout_builder.add_block',
            'layout_builder.choose_inline_block',
            'layout_builder.update_block',
        ];
        $routes = array_merge($routes, $layoutRoutes);

        $customRoutes = [
            'sprowt_admin_override.layout_block_update',
            'sprowt_admin_override.metatag_dashboard',
            'sprowt_admin_override.layout_builder_browser.add_block'
        ];

        $routes = array_merge($routes, $customRoutes);

        $ret = false;
        $currentRoute = \Drupal::routeMatch()->getRouteName();
        if(in_array($currentRoute, $routes)) {
            $ret = true;
        }

        \Drupal::service('module_handler')->alter('needs_description_toggle', $ret);

        return $ret;
    }
}
