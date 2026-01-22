<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Form controller for the prompt entity edit forms.
 */
class PromptForm extends ContentEntityForm
{
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildForm($form, $form_state);

        $description = $form["prompt"]["widget"][0]["value"]['#description'] ?? '';

        $description = (string) $description;
        $token_tree = [
            '#theme' => 'token_tree_link',
            '#token_types' => [],
        ];
        $rendered_token_tree = \Drupal::service('renderer')->render($token_tree);

        $tokenTreeMarkup = \Drupal\Core\Render\Markup::create('<div class="form-item__description">' . t('This field supports tokens. @browse_tokens_link', [
                '@browse_tokens_link' => $rendered_token_tree,
            ]) . '</div>');
        $description .= "\n" . $tokenTreeMarkup;
        $form["prompt"]["widget"][0]["value"]['#description'] = $description;
        $form['revision_information']['#weight'] = 100;



        $tagDescription = $form["tags"]["widget"]['#description'] ?? '';
        $vocab = Vocabulary::load(Prompt::$vocab);
        $overviewUrl = $vocab->toUrl('overview-form');
        $tagDescription .= Markup::create(' Add more tags by adding them to the <a target="_blank" href="' . $overviewUrl->toString() . '">tag vocabulary</a>.');
        $form["tags"]["widget"]['#description'] = $tagDescription;

        $form["tags"]["widget"]['#attributes']['data-tags'] = 'true';

        $tagsValidate = $form["tags"]["widget"]["#element_validate"] ?? [];
        $form["tags"]["widget"]["#element_validate"] = array_merge([
            [static::class, 'validateTags']
        ], $tagsValidate);

        return $form;
    }

    public function actions(array $form, FormStateInterface $form_state)
    {
        $actions = parent::actions($form, $form_state);

        /** @var Prompt $prompt */
        $prompt = $this->entity;
        if(!$prompt->isNew()) {
            $actions['clone'] = [
                '#type' => 'link',
                '#title' => t('Clone'),
                '#url' => Url::fromRoute('sprowt_ai_prompt_library.prompt_clone', ['prompt' => $prompt->id()]),
                '#attributes' => [
                    'class' => ['button', 'button--primary'],
                    'target' => '_blank'
                ]
            ];
        }


        return $actions;

    }

    public static function validateTags(array &$element, FormStateInterface $form_state)
    {
        $value = $element["#value"] ?? [];
        if(is_string($value)) {
            $value = trim($value);
            if(!empty($value) && $value != '_none') {
                $value = [$value => $value];
            }
            else {
                $value = [];
            }
        }
        $options = $element["#options"] ?? [];
        $set = false;
        foreach(array_keys($value) as $tagId) {
            if(empty($tagId) || $tagId == '_none') {
                continue;
            }
            $tag = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tagId);
            if (empty($tag)) {
                $tag = Term::create([
                    'name' => $tagId,
                    'vid' => Prompt::$vocab
                ]);
                $tag->save();
                unset($value[$tagId]);
                $value[$tag->id()] = $tag->id();
                $options[$tag->id()] = $tag->label();
                $set = true;
            }
            $vid = $tag->get('vid')->target_id ?? '';
            if ($vid != Prompt::$vocab) {
                $form_state->setError($element, 'You can only add tags from the prompt vocabulary.');
            }
        }

        if(!empty($set)) {
            $element['#value'] = $value;
            $element['#options'] = $options;
            $form_state->setValueForElement($element, $value);
            $error = $form_state->getError($element);
            if(!empty($error)) {
                $errorStr = (string) $error;
                if(strpos($errorStr, 'The submitted value') === 0
                    && strpos($errorStr, 'element is not allowed.') !== false
                ) {
                    $errors = $form_state->getErrors();
                    $form_state->clearErrors();
                    foreach ($errors as $key => $arrayError) {
                        if ($error == $arrayError) {
                            unset($errors[$key]);
                        }
                        else {
                            $form_state->setErrorByName($key, $arrayError);
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state): int
    {
        $result = parent::save($form, $form_state);

        $message_args = ['%label' => $this->entity->toLink()->toString()];
        $logger_args = [
            '%label' => $this->entity->label(),
            'link' => $this->entity->toLink($this->t('View'))->toString(),
        ];

        switch ($result) {
            case SAVED_NEW:
                $this->messenger()->addStatus($this->t('New prompt %label has been created.', $message_args));
                $this->logger('sprowt_ai_prompt_library')->notice('New prompt %label has been created.', $logger_args);
                break;

            case SAVED_UPDATED:
                $this->messenger()->addStatus($this->t('The prompt %label has been updated.', $message_args));
                $this->logger('sprowt_ai_prompt_library')->notice('The prompt %label has been updated.', $logger_args);
                break;

            default:
                throw new \LogicException('Could not save the entity.');
        }

        $form_state->setRedirectUrl($this->entity->toUrl('collection'));

        return $result;
    }

}
