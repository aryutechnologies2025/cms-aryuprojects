<?php

namespace Drupal\cookie_banner;

use Drupal\Core\Render\Markup;

/**
 * Service description.
 */
class CookieBannerService
{

    protected $config;

    protected $cookieName = 'cookie_banner_accept';

    public function getConfig() {
        if(isset($this->config)) {
            return $this->config;
        }
        $this->config = \Drupal\cookie_banner\Form\SettingsForm::getConfig();
        return $this->config;
    }

    public function isEnabled() {
        $config = $this->getConfig();
        return !empty($config['enabled']);
    }

    public function isAccepted() {
        if(!$this->isEnabled()) {
            return true;
        }
        $request = \Drupal::request();
        $cookie = $request->cookies->get($this->cookieName);
        return !empty($cookie);
    }

    public function getBuild() {
        if($this->isAccepted()) {
            return [];
        }
        $route = \Drupal::routeMatch()->getRouteObject();
        $isAdmin = \Drupal::service('router.admin_context')->isAdminRoute($route);
        $isLoggedIn = \Drupal::currentUser()->isAuthenticated();
        if($isAdmin || $isLoggedIn) {
            return [];
        }
        $config = $this->getConfig();
        return [
            'banner' => [
                '#theme' => 'cookie_banner',
                '#bannerText' => Markup::create($config['bannerText']),
                '#acceptButtonText' => $config['acceptButtonText']
            ],
            '#attached' => [
                'library' => [
                    'cookie_banner/cookie_banner'
                ],
                'drupalSettings' => [
                    'cookie_banner' => [
                        'cookieName' => $this->cookieName,
                        'expires' => $config['expires']
                    ]
                ]
            ]
        ];
    }

}
