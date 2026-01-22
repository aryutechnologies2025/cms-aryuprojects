<?php

namespace Drupal\lawnbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\lawnbot\Entity\Servicebot;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Lawnbot routes.
 */
class LawnbotController extends ControllerBase
{

    /**
     * Builds the response.
     */
    public function instantquote()
    {

        \Drupal::service('page_cache_kill_switch')->trigger();
        $build['content'] = [
            '#type' => 'item',
            '#markup' => Markup::create('It works'),
        ];

        return $build;
    }

    public function servicebot($servicebot) {
        if($servicebot instanceof Servicebot) {
            $bot = $servicebot;
        }
        else {
            $bot = Servicebot::load($servicebot);
        }
        $uuid = $bot instanceof Servicebot ? $bot->uuid() : '';
        $url = Url::fromRoute('lawnbot.instantquote', [], [
            'query' => [
                'serviceBotId' => $uuid
            ]
        ]);
        return new RedirectResponse($url->toString());

    }

}
