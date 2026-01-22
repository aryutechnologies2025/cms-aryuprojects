<?php

namespace Drupal\lawnbot\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the servicebot entity edit forms.
 */
class ServicebotForm extends ContentEntityForm
{

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {

        $entity = $this->getEntity();
        $result = $entity->save();
        $link = $entity->toLink($this->t('View'))->toRenderable();

        $message_arguments = ['%label' => $this->entity->label()];
        $logger_arguments = $message_arguments + ['link' => \Drupal::service('renderer')->render($link)];

        if ($result == SAVED_NEW) {
            $this->messenger()->addStatus($this->t('New servicebot %label has been created.', $message_arguments));
            $this->logger('lawnbot')->notice('Created new servicebot %label', $logger_arguments);
        } else {
            $this->messenger()->addStatus($this->t('The servicebot %label has been updated.', $message_arguments));
            $this->logger('lawnbot')->notice('Updated new servicebot %label.', $logger_arguments);
        }

        $form_state->setRedirect('lawnbot.settings_form');
    }

}
