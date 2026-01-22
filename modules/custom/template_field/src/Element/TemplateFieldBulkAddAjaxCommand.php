<?php

namespace Drupal\template_field\Element;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;

class TemplateFieldBulkAddAjaxCommand extends InvokeCommand
{

    public function __construct($selector, $objects)
    {
        parent::__construct($selector, 'trigger', [
            'bulkAdd',
            [$objects]
        ]);
    }

    public static function response($selector, $objects) {
        $response = new AjaxResponse();
        $response->addCommand(new static($selector, $objects));
        $response->addCommand(new \Drupal\Core\Ajax\CloseDialogCommand());
        return $response;
    }

}
