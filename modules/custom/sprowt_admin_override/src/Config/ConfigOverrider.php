<?php

namespace Drupal\sprowt_admin_override\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class ConfigOverrider implements ConfigFactoryOverrideInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadOverrides($names)
    {
        $overrides = [];
        if(in_array('system.performance', $names)) {
            $is_admin = false;
            try {
                //have to do all this manually because this occurs before routeMatching
                $request = \Drupal::requestStack();
                $path = $request->getMainRequest()->getUri();
                $router = \Drupal::service('router.no_access_checks');
                $routeMatch = $router->matchRequest($request->getMainRequest());
            }
            catch (\Exception $e) {
                if(!($e instanceof ResourceNotFoundException || $e instanceof ParamNotConvertedException)) {
                    \Drupal::logger('configOverrider')->error($e->getMessage(), [
                        'exception' => $e
                    ]);
                }
                $routeMatch = [];
            }
            if(!empty($routeMatch) && !empty($routeMatch['_route_object'])) {
                $route = $routeMatch['_route_object'];
                $is_admin = \Drupal::service('router.admin_context')->isAdminRoute($route);
            }
            if($is_admin) {
                $overrides['system.performance'] = [
                    'css' => [
                        'preprocess' => false
                    ],
                    'js' => [
                        'preprocess' => false
                    ]
                ];
            }
        }
        if(in_array('advagg.settings', $names)) {
            $route = \Drupal::routeMatch()->getRouteObject();
            $is_admin = \Drupal::service('router.admin_context')->isAdminRoute($route);
            if($is_admin) {
                $overrides['advagg.settings'] = [
                    'enabled' => false
                ];
            }
        }
        return $overrides;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheSuffix()
    {
        return 'SprowtConfigOverrider';
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheableMetadata($name)
    {
        return new CacheableMetadata();
    }

    /**
     * {@inheritdoc}
     */
    public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION)
    {
        return null;
    }

}
