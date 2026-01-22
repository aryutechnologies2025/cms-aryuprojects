<?php

namespace Drupal\sprowt_careers\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a confirmation form before clearing out the examples.
 */
class InstallerConfirmForm extends ConfirmFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_careers_installer_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion()
    {
        return $this->t('Are you sure you want to create a new careers section?');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl()
    {
        return new Url('sprowt_careers.installer');
    }

    public static function batchFinished($success, $results, $operations) {
        $message = [
            '#type' => 'markup',
            '#markup' => Markup::create('<p>' . t('The following nodes have been created:') . '</p>'),
            'list' => [
                '#type' => 'html_tag',
                '#tag' => 'ul'
            ]
        ];
        foreach($results['nodesAdded'] as $result) {
            $message['list'][$result['uuid']] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                'result' => [
                    '#type' => 'link',
                    '#title' => $result['label'],
                    '#url' => Url::fromUri('internal:' . $result['url']),
                    '#attributes' => [
                        'target' => '_blank'
                    ]
                ]
            ];
        }
        \Drupal::messenger()->addStatus($message);
        \Drupal::state()->delete('sprowt_careers.batch');
        $homePageNode = sprowt_careers_get_homepage();
        return new RedirectResponse($homePageNode->toUrl()->toString());
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $batch = \Drupal::state()->get('sprowt_careers.batch');
        if(!empty($batch)) {
            $batch['finished'] = [$this, 'batchFinished'];
            batch_set($batch);
        }
    }

}
