<?php

namespace Drupal\sprowt_subsite\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

class ConfigOverrides implements ConfigFactoryOverrideInterface
{
    protected $mainMenuNames;

    /**
     * {@inheritdoc}
     */
    public function loadOverrides($names) {
        $overrides = [];
        foreach($names as $name) {
            if (strpos($name, 'node.type.') === 0) {
                //dynamically load all main menus available including subsite main menus
                //this is so that the menu link in the subsite menus gets attached to the node
                //see menu_ui_get_menu_link_defaults()
                $overrides[$name]['third_party_settings']['menu_ui']['available_menus'] = $this->loadMainMenuNames();
            }
        }
        return $overrides;
    }


    public function loadMainMenuNames() {
        if (isset($this->mainMenuNames)) {
            return $this->mainMenuNames;
        }

        $db = \Drupal::database();
        $mainMenus = [];
        
        // Check if the table exists before querying
        if ($db->schema()->tableExists('node__field_main_menu')) {
            try {
                $mainMenus = $db->query("
                    SELECT field_main_menu_target_id
                    FROM node__field_main_menu
                ")->fetchCol() ?? [];
            } catch (\Exception $e) {
                // If query fails, just continue with empty array
                $mainMenus = [];
            }
        }

        $mainMenus[] = 'main';
        $mainMenus = array_unique(array_filter($mainMenus));
        $this->mainMenuNames = $mainMenus;
        return $this->mainMenuNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheSuffix() {
        return 'sprowt_subsite';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata($name) {
        return new CacheableMetadata();
    }

    /**
     * {@inheritdoc}
     */
    public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
        return NULL;
    }
}
