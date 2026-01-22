<?php

declare(strict_types=1);

namespace Drupal\sprowt_settings\Controller;


use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\sprowt_settings\Element\ScheduleElement;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Sprowt Settings routes.
 */
class SprowtSettingsController extends ControllerBase
{

    /**
     * Builds the response.
     */
    public function scheduleText(Request $request)
    {
        //ini_set('error_reporting', 'E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT');
        $body = $request->query->get('schedule');
        $schedule = json_decode($body, true);
        $str = ScheduleElement::scheduleTextSummary($schedule);
        return new JsonResponse([
            'text' => $str
        ]);
    }

}
