<?php

namespace Drupal\sprowt_views\Plugin\views\argument;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument\NumericArgument;


/**
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("sprowt_views_dynamic_limit")
 */
class DynamicLimit extends NumericArgument
{

    public function buildOptionsForm(&$form, FormStateInterface $form_state)
    {
        $form = parent::buildOptionsForm($form, $form_state);

        //remove the ability to have multiple arguments and reverse arguments
        $form['break_phrase']['#access'] = false;
        $form['not']['#access'] = false;

        return $form;
    }

    public function query($group_by = FALSE)
    {
        $limit = (int) $this->argument;
        if(!isset($limit) || $limit < 0) {
            // don't limit at all
            return;
        }

        $this->view->query->setLimit($limit);
        $this->view->query->setOffset(0);
    }

}
