<?php

namespace Drupal\script_inserter\Form;

//sometime this isn't loaded?
require_once DRUPAL_ROOT . '/modules/custom/sprowt_settings/src/EntityVisibilityFormTrait.php';

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Plugin\Condition\EntityBundle;
use Drupal\Core\Form\FormStateInterface;
use Drupal\script_inserter\Entity\Script;
use Drupal\sprowt_settings\EntityVisibilityFormTrait;
use Drupal\system\Plugin\Condition\RequestPath;

/**
 * Form controller for the script entity edit forms.
 */
class ScriptForm extends ContentEntityForm
{

    use EntityVisibilityFormTrait;

    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $form['location'] = [
            '#type' => 'select',
            '#title' => t('Location'),
            '#options' => Script::getLocationOptions(),
            '#default_value' => $this->entity->getLocation(),
            '#weight' => 0
        ];

        $form[$this->visibilityFormItemKey] = $this->buildVisibilityInterface([], $form_state);

        $form['#attached']['library'][] = 'script_inserter/entity_form';

        return parent::buildForm($form, $form_state);
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $this->validateVisibility($form, $form_state);
        return parent::validateForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitForm($form, $form_state);
        $this->submitVisibility($form, $form_state);
    }

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
            $this->messenger()->addStatus($this->t('New script %label has been created.', $message_arguments));
            $this->logger('script_inserter')->notice('Created new script %label', $logger_arguments);
        }
        else {
            $this->messenger()->addStatus($this->t('The script %label has been updated.', $message_arguments));
            $this->logger('script_inserter')->notice('Updated new script %label.', $logger_arguments);
        }

        $form_state->setRedirect('entity.script.collection');
    }

}
