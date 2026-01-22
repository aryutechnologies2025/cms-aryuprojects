<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Drupal\Core\Installer\InstallerKernel;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env', 'SPROWTHQ_ENVIRONMENT');
if($_SERVER['SPROWTHQ_ENVIRONMENT'] == 'local') {
  $clearEnv = [
    'SPROWT_REDIS_HOST',
    'SPROWT_REDIS_PORT',
    'SPROWT_REDIS_PREFIX',
    'SPROWT_REVERSE_PROXY_IPS'
  ];
  foreach($clearEnv as $env) {
    if(isset($_ENV[$env])) {
      unset($_ENV[$env]);
    }
  }
};


if (!InstallerKernel::installationAttempted() && extension_loaded('redis')) {
  $redisHost = $_ENV['SPROWT_REDIS_HOST'] ?? null;
  $redisPort = $_ENV['SPROWT_REDIS_PORT'] ?? '6379';
  $dbAdd = $_ENV['SPROWT_REDIS_PREFIX'] ?? null;

  $connected = false;
  if(!empty($redisHost) && !empty($redisPort)) {
    $testRedis = new Redis();
    try {
      $connected = $testRedis->connect($redisHost, $redisPort, 5);
      if ($connected) {
        $connected &= $testRedis->ping();
        $testRedis->discard();
      }
    } catch (RedisException $e) {
      $connected = false;
    }
  }

  if($connected) {
    $settings['redis.connection']['interface'] = 'PhpRedis';
    $settings['redis.connection']['host'] = $redisHost;
    $settings['redis.connection']['port'] = $redisPort;
    $settings['cache']['default'] = 'cache.backend.redis';

    $settings['cache']['bins']['config'] = 'cache.backend.database';
    $settings['cache']['bins']['entity'] = 'cache.backend.database';
    $settings['cache']['bins']['discovery'] = 'cache.backend.database';

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
    if(!empty($dbAdd)) {
      $dbPrefix[] = $dbAdd;
    }
    $settings['cache_prefix'] = implode('_', $dbPrefix);
  }
}

$reverseProxyAddresses = $_ENV['SPROWT_REVERSE_PROXY_IPS'] ?? null;
if(!empty($reverseProxyAddresses)) {
  $reverseProxyAddresses = json_decode($reverseProxyAddresses, true);
  if(!empty($reverseProxyAddresses)) {
    $settings['reverse_proxy'] = TRUE;
    $settings['reverse_proxy_addresses'] = $reverseProxyAddresses;
    $settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
      | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
      | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
      | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO
      | \Symfony\Component\HttpFoundation\Request::HEADER_FORWARDED;
  }
}

$databases['shared']['default'] = [
  'database' => 'sprowt_shared',
  'username' => $_ENV['SPROWT_SHARED_DB_USER'] ?? 'root',
  'password' => $_ENV['SPROWT_SHARED_DB_PASSWORD'] ?? 'root',
  'prefix' => '',
  'host' => $_ENV['SPROWT_SHARED_DB_HOST'] ?? 'localhost',
  'port' => '',
  'driver' => 'mysql',
];

if($_SERVER['SPROWTHQ_ENVIRONMENT'] == 'local') {
  $databases['shared']['default']['username'] = $databases['default']['default']['username'] ?? 'root';
  $databases['shared']['default']['password'] = $databases['default']['default']['password'] ?? 'root';
  $databases['shared']['default']['host'] = $databases['default']['default']['host'] ?? 'localhost';
}

$localMulti = __DIR__ . '/settings.multi.local.php';
if(file_exists($localMulti)) {
  require_once $localMulti;
}
