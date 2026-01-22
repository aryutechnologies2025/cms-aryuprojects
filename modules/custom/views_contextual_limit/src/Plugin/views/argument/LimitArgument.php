<?php


namespace Drupal\views_contextual_limit\Plugin\views\argument;


use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Annotation\ViewsArgument;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\Plugin\views\argument\NumericArgument;

/**
 * Class LimitArgument
 * @package Drupal\views_contextual_limit\Plugin\views\argument
 * @ViewsArgument("limit")
 */
class LimitArgument extends NumericArgument
{

    public function buildOptionsForm(&$form, FormStateInterface $form_state)
    {
        parent::buildOptionsForm($form, $form_state);

        //remove the ability to have multiple arguments and reverse arguments
        $form['break_phrase']['#access'] = false;
        $form['not']['#access'] = false;
    }

    public function query($group_by = FALSE)
    {
        if(isset($this->argument)) {
            $limit = $this->argument;
            if($this->isException()) {
                $limit = null;
            }
            else {
                if (!is_numeric($limit)) {
                    if (!ctype_digit($limit)) {
                        $limit = 0;
                    }
                    else {
                        $limit = (int)$limit;
                    }
                }
            }
            $limit = (int)$limit;
            if($limit === 0) {
                $this->query->addWhereExpression(0, "1 = 0");
            }
            $this->query->setLimit($limit);
        }
    }
}
