<?php

namespace Drupal\sprowt_views\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("sprowt_views_dynamic_view_mode")
 */
class DynamicViewMode extends ArgumentPluginBase
{
    public function query($group_by = FALSE)
    {
        $viewMode = $this->argument;
        if(empty($viewMode)) {
            // don't limit at all
            return;
        }

        $view = &$this->view;


        $rowPlugin = &$view->rowPlugin;
        if($rowPlugin instanceof \Drupal\views\Plugin\views\row\EntityRow) {
            $options = &$rowPlugin->options;
            $options['view_mode'] = $viewMode;
        }
        $displayHandler = $view->getDisplay();
        $rowOptions = $displayHandler->getOption('row');
        if(isset($rowOptions['options']['view_mode'])) {
            $rowOptions['options']['view_mode'] = $viewMode;
            $displayHandler->setOption('row', $rowOptions);
        }
    }
}
