<?php

namespace Drupal\sprowt_theme;

use Drupal\node\Entity\Node;
use Drupal\sprowt_settings\SprowtSettings;

class SprowtThemeService
{
    public static $themes = [
        'diagonal' => 'Diagonal',
        'round' => 'Round',
        'wave' => 'Wave',
        'fun' => 'Fun',
        'generic' => 'Generic'
    ];

    protected SprowtSettings $sprowtSettings;

    public function __construct(SprowtSettings $sprowtSettings) {
        $this->sprowtSettings = $sprowtSettings;
    }

    public function currentTheme() {
        $theme = $this->sprowtSettings->getSetting('sprowt_theme');
        if(empty($theme) || !in_array($theme, array_keys(static::$themes))) {
            return null;
        }
        return $theme;
    }

    public function themeNode(Node $node, $theme = null) {
        if(empty($theme)) {
            $theme = $this->currentTheme();
        }
        if(empty($theme)) {
            return $node;
        }
        $bundle = $node->bundle();
        if($bundle == 'page') {
            $pageType = $node->field_page_type->value;
            if(!empty($pageType) && $pageType == 'home') {
                $themer = new HomePageThemer($theme);
            }
        }
        if($bundle == 'city_page') {
            $themer = new CityPageThemer($theme);
        }
        if($bundle == 'service') {
            $themer = new ServiceThemer($theme);
        }

        if($bundle == 'landing_page') {
            $themer = new LandingThemer($theme);
        }

        if(!empty($themer)) {
            $node = $themer->updateLayout($node);
            $node->save();
        }
        return $node;
    }

}
