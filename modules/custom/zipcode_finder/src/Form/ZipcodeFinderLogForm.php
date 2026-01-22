<?php

namespace Drupal\zipcode_finder\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the zipcode finder log entity edit forms.
 */
class ZipcodeFinderLogForm extends ContentEntityForm
{

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {
        $result = parent::save($form, $form_state);

        $entity = $this->getEntity();

        $message_arguments = ['%label' => $entity->toLink()->toString()];
        $logger_arguments = [
            '%label' => $entity->label(),
            'link' => $entity->toLink($this->t('View'))->toString(),
        ];

        switch ($result) {
            case SAVED_NEW:
                $this->messenger()->addStatus($this->t('New zipcode finder log %label has been created.', $message_arguments));
                $this->logger('zipcode_finder')->notice('Created new zipcode finder log %label', $logger_arguments);
                break;

            case SAVED_UPDATED:
                $this->messenger()->addStatus($this->t('The zipcode finder log %label has been updated.', $message_arguments));
                $this->logger('zipcode_finder')->notice('Updated zipcode finder log %label.', $logger_arguments);
                break;
        }

        $form_state->setRedirect('entity.zipcode_finder_log.collection');

        return $result;
    }

}
