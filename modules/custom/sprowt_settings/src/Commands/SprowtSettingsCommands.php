<?php

namespace Drupal\sprowt_settings\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Drupal\sprowt_settings\SprowtSettings;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class SprowtSettingsCommands extends DrushCommands
{

    /**
     * Get a sprowt setting value by key.
     *
     * @param $key
     *   Sprowt setting key.
     * @param array $options
     *   An associative array of options whose values come from cli, aliases, config, etc.
     *
     * @usage sprowt:setting foo
     *   returns the setting value in json format
     *
     * @command sprowt:setting
     * @aliases ss
     */
    public function getSprowtSetting($key, $options = ['format' => 'json'])
    {
        /** @var SprowtSettings $service */
        $service = \Drupal::service('sprowt_settings.manager');
        $value = $service->getSetting($key);
        $return = [$key => $value];
        return new UnstructuredListData($return);
    }
}
