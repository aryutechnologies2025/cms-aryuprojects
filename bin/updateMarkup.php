<?php

$projectDir = dirname(__DIR__);
$oldModuleFile = $projectDir . '/old_modules.json';
$newModuleFile = $projectDir . '/new_modules.json';
$oldModules = json_decode(file_get_contents($oldModuleFile), true);
$newModules = json_decode(file_get_contents($newModuleFile), true);

$newInstalled = array_diff(array_keys($newModules), array_keys($oldModules));

$updates = [];

$nodeOld = $oldModules['node'];
$nodeNew = $newModules['node'];
if ($nodeOld['version'] !== $nodeNew['version']) {
    $updates['drupal'] = [
        'name' => 'Drupal core',
        'machineName' => 'drupal',
        'oldVersion' => $nodeOld['version'],
        'newVersion' => $nodeNew['version'],
    ];
}

foreach ($oldModules as $moduleName => $info) {
    $continue = false;
    $path = $info['path'];
    $type = null;
    if (strpos($path, 'modules/contrib') === 0) {
        $type = 'module';
        $continue = true;
    }
    if (strpos($path, 'themes/contrib') === 0) {
        $type = 'theme';
        $continue = true;
    }

    if (!$continue) {
        continue;
    }


    if (empty($updates[$type])) {
        $updates[$type] = [];
    }

    $newModuleInfo = $newModules[$moduleName] ?? [];

    if ($info['status'] != 'Enabled' && $newModuleInfo['status'] != 'Enabled') {
        continue;
    }

    if (empty($newModuleInfo)
        || $newModuleInfo['status'] != $info['status']
    ) {
        if ($info['status'] == 'Enabled') {
            $updates[$type][$moduleName] = [
                'name' => $info['display_name'],
                'machineName' => $moduleName,
                'oldVersion' => $info['version'],
                'newVersion' => 'uninstalled',
                'project' => $info['project'],
            ];
        } else {
            $updates[$type][$moduleName] = [
                'name' => $info['display_name'],
                'machineName' => $moduleName,
                'oldVersion' => 'uninstalled',
                'newVersion' => $newModuleInfo['version'],
                'project' => $info['project'],
            ];
        }
    } else {
        if ($newModuleInfo['version'] !== $info['version']) {
            $updates[$type][$moduleName] = [
                'name' => $info['display_name'],
                'machineName' => $moduleName,
                'oldVersion' => $info['version'],
                'newVersion' => $newModuleInfo['version'],
                'project' => $info['project'],
            ];
        }
    }
}


$markup = [];
if (!empty($updates['drupal'])) {
    $markup[] = 'Drupal core';
    $markup[] = '';
    $markup[] = '  ' . $updates['drupal']['oldVersion'] . ' => ' . $updates['drupal']['newVersion'];
    $markup[] = '  ' . 'release notes: https://www.drupal.org/project/drupal/releases/' . $updates['drupal']['newVersion'];
}

if (!empty($updates['module'])) {
    $markup[] = '';
    $markup[] = 'Modules';
    $markup[] = '';
    foreach ($updates['module'] as $moduleName => $info) {
        $markup[] = '  ' . $info['name'] . ': ' . $info['oldVersion'] . ' => ' . $info['newVersion'];
        if ($info['newVersion'] == 'uninstalled') {
            $markup[] = '';
        } else {
            $markup[] = '  ' . '  ' . 'release notes: https://www.drupal.org/project/' . $info['project'] . '/releases/' . $info['newVersion'];
        }
        $markup[] = '';
    }
}

if (!empty($updates['theme'])) {
    $markup[] = '';
    $markup[] = 'Themes';
    foreach ($updates['theme'] as $moduleName => $info) {
        $markup[] = '  ' . $info['name'] . ': ' . $info['oldVersion'] . ' => ' . $info['newVersion'];
        $markup[] = '  ' . '  ' . 'release notes: https://www.drupal.org/project/' . $info['project'] . '/releases/' . $info['newVersion'];
    }
}

echo implode("\n", $markup);
