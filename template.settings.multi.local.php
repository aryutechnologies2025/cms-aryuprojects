<?php

use Drupal\Core\Installer\InstallerKernel;

if (!InstallerKernel::installationAttempted() && extension_loaded('redis')) {
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = '104.154.196.80';
  $settings['redis.connection']['port'] = '6379';
  $settings['cache']['default'] = 'cache.backend.redis';

  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

  // Allow the services to work before the Redis module itself is enabled.
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

  $settings['redis_compress_length'] = 100;
  $dbPrefix = [];
  if (isset($_SERVER['SPROWTHQ_SITE_NAME'])) {
    $dbPrefix[] = $_SERVER['SPROWTHQ_SITE_NAME'];
  }
  if (isset($_SERVER['SPROWTHQ_ENVIRONMENT'])) {
    $dbPrefix[] = $_SERVER['SPROWTHQ_ENVIRONMENT'];
  }
  if (empty($dbPrefix)) {
    $dbPrefix[] = basename($app_root . '/' . $site_path);
  }
  $settings['cache_prefix'] = implode('_', $dbPrefix);
}
