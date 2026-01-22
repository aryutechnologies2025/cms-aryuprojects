<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\Entity\AiSystem;
use Drupal\sprowt_ai\Entity\Example;

/**
 * AI System form.
 */
final class AiSystemForm extends EntityForm
{

    /**
     * {@inheritdoc}
     */
    public function form(array $form, FormStateInterface $form_state): array
    {

        $form = parent::form($form, $form_state);

        $form['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#maxlength' => 255,
            '#default_value' => $this->entity->label(),
            '#required' => TRUE,
        ];

        $form['id'] = [
            '#type' => 'machine_name',
            '#default_value' => $this->entity->id(),
            '#machine_name' => [
                'exists' => [AiSystem::class, 'load'],
            ],
            '#disabled' => !$this->entity->isNew(),
        ];

        $form['status'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enabled'),
            '#default_value' => $this->entity->status(),
        ];

        $form['is_default'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Is default?'),
            '#default_value' => $this->entity->isDefault()
        ];

        $form['description'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Description'),
            '#default_value' => $this->entity->get('description'),
            '#description' => 'A description the ai api can understand for defining a system user. For Claude see <a href="https://docs.anthropic.com/en/docs/system-prompts" target="_blank">here</a>.',
        ];

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $isDefault = $form_state->getValue('is_default');
        $currentDefaultSystem = sprowt_ai_default_system();
        if($currentDefaultSystem instanceof AiSystem
            && !$currentDefaultSystem->isNew()
            && $isDefault
            && $currentDefaultSystem->id() !== $this->entity->id()
        ) {
            $editLink = $currentDefaultSystem->toUrl('edit-form')->toString();
            $editLabel = $currentDefaultSystem->label();
            $error = 'You can only have one default system user. '
            . 'The current default system user is "<a href="'.$editLink.'" target="_blank">'.$editLabel.'</a>." '
            . 'Please edit the current default system user to make this system user the default.';
            $form_state->setErrorByName('is_default', Markup::create($error));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state): int
    {
        $result = parent::save($form, $form_state);
        $message_args = ['%label' => $this->entity->label()];
        $this->messenger()->addStatus(
            match ($result) {
                \SAVED_NEW => $this->t('Created new ai system %label.', $message_args),
                \SAVED_UPDATED => $this->t('Updated ai system %label.', $message_args),
            }
        );
        $form_state->setRedirectUrl($this->entity->toUrl('collection'));
        return $result;
    }

}
