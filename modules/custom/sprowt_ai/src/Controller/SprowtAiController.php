<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\BaseCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\sprowt_ai\Form\WidgetPromptForm;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Sprowt AI routes.
 */
class SprowtAiController extends ControllerBase
{

    /**
     * Builds the response.
     */
    public function widgetTempStore(Request $request)
    {
        $tempStore = \Drupal::service('tempstore.private')->get('sprowt_ai');
        $data = $request->getPayload()->all();
        $widgetKey = $data['widgetKey'];
        $tempStore->set('prompt_data.' . $widgetKey, $data);
        $response = new AjaxResponse();
        return $response;
    }

}
