#!/usr/bin/env php
<?php
$rootDir = dirname(__DIR__);
require_once $rootDir . '/vendor/autoload.php';


$fs = new \Symfony\Component\Filesystem\Filesystem();

$modeFile = $rootDir . '/fileModes.yaml';
$originalContents = '';
if(file_exists($modeFile)) {
  $originalContents = file_get_contents($modeFile);
}

function &staticVar($name, $default = null) {
  static $data = [];
  if (isset($data[$name]) || array_key_exists($name, $data)) {
    return $data[$name];
  }
  $data[$name] = $default;
  return $data[$name];
}

$modes = &staticVar('modes', []);

if($fs->exists($modeFile)) {
  try {
    $modes = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($modeFile));
  }
  catch (\Exception $e) {
    $modes = [];
    $originalContents = '';
  }
}


$ignore = &staticVar('ignore', []);
$rootDir = &staticVar('rootDir', dirname(__DIR__));


function addToIgnore($gitIgnoreFile) {
  $contents = file_get_contents($gitIgnoreFile);
  $dir = dirname($gitIgnoreFile);
  $lines = explode("\n", $contents);
  //always ignore these
  $lines[] = '/vendor';
  $lines[] = '/node_modules';
  $lines[] = '/.idea';
  $lines[] = '/.git';
  $ignore = &staticVar('ignore', []);
  $unignore = [];
  foreach($lines as $line) {
    $line = trim($line);
    if(empty($line) || strpos($line, '#') === 0) {
      continue;
    }
    $ug = false;
    if(strpos($line, '!') === 0) {
      $line = str_replace('!', '', $line);
      $ug = true;
    }
    if(strpos($line, '/') !== 0) {
      $glob = $dir . '/' . $line;
    }
    else {
      $glob = $dir . $line;
    }
    $ignore = array_merge($ignore, glob($glob));
    if($ug) {
      $unignore = array_merge($unignore, glob($glob));
    }
  }
  if(!empty($unignore)) {
    foreach($unignore as $ugFile) {
      if(in_array($ugFile, $ignore)) {
        $idx = array_search($ugFile, $ignore);
        unset($ignore[$idx]);
        $ignore = array_values($ignore);
      }
    }
  }
}

/**
 * @param string $dir
 * @return void
 */
function updateMode($dir)  {
  $fs = new \Symfony\Component\Filesystem\Filesystem();
  $modeFile = __DIR__ . '/../fileModes.yaml';
  $gitIgnore = $dir . '/.gitignore';
  if($fs->exists($gitIgnore)) {
    addToIgnore($gitIgnore);
  }
  $modes = &staticVar('modes', []);
  $ignore = &staticVar('ignore', []);
  $files = array_merge(glob($dir . '/*'), glob($dir . '/.*'));
  $rootDir = &staticVar('rootDir', '');
  foreach($files as $file) {
    $basename = basename($file);
    if($basename == '.' || $basename == '..') {
      continue;
    }
    if(is_link($file)) {
      continue;
    }
    if(is_dir($file) && !in_array($file, $ignore)) {
      updateMode($file);
    }
    if(in_array($file, $ignore)) {
      continue;
    }
    $currentMode = substr(sprintf('%o', fileperms($file)), -4);
    $mKey = str_replace($rootDir, '', $file);
    if(isset($modes[$mKey])) {
      if($currentMode != $modes[$mKey]) {
        $fs->chmod($file, $modes[$mKey]);
      }
    }
    else {
      $modes[$mKey] = $currentMode;
    }
  }
}

updateMode(dirname(__DIR__));

$fs->dumpFile($modeFile, \Symfony\Component\Yaml\Yaml::dump($modes));
$newContents = file_get_contents($modeFile);
if($originalContents != $newContents) {
  chdir($rootDir);
  exec("git add $modeFile");
  exec("git commit -m 'mode file change'");
}
