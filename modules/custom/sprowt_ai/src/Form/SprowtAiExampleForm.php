<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_ai\Entity\SprowtAiExample;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Form controller for the example entity edit forms.
 */
final class SprowtAiExampleForm extends ContentEntityForm
{

    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildForm($form, $form_state);

        $tagDescription = $form["tags"]["widget"]['#description'] ?? '';
        $vocab = Vocabulary::load(SprowtAiExample::$vocab);
        $overviewUrl = $vocab->toUrl('overview-form');
        $tagDescription .= Markup::create(' Add more tags by adding them to the <a target="_blank" href="' . $overviewUrl->toString() . '">tag vocabulary</a>.');
        $form["tags"]["widget"]['#description'] = $tagDescription;

        $form["tags"]["widget"]['#attributes']['data-tags'] = 'true';
        $tagOptions = $form["tags"]["widget"]['#options'];
        if(count($tagOptions) === 1
            && !empty($tagOptions['_none'])
            && empty($form["tags"]["widget"]["#multiple"])
        ) {
            // when there are no tags, it makes it a single select for some reason.
            // this undoes it
            $form["tags"]["widget"]["#options"] = [];
            $form["tags"]["widget"]["#multiple"] = true;
        }

        $tagsValidate = $form["tags"]["widget"]["#element_validate"] ?? [];
        $form["tags"]["widget"]["#element_validate"] = array_merge([
            [static::class, 'validateTags']
        ], $tagsValidate);
        return $form;
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
                $this->messenger()->addStatus($this->t('New example %label has been created.', $message_args));
                $this->logger('sprowt_ai')->notice('New example %label has been created.', $logger_args);
                break;

            case SAVED_UPDATED:
                $this->messenger()->addStatus($this->t('The example %label has been updated.', $message_args));
                $this->logger('sprowt_ai')->notice('The example %label has been updated.', $logger_args);
                break;

            default:
                throw new \LogicException('Could not save the entity.');
        }

        $form_state->setRedirectUrl($this->entity->toUrl('collection'));

        return $result;
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
                    'vid' => SprowtAiExample::$vocab
                ]);
                $tag->save();
                unset($value[$tagId]);
                $value[$tag->id()] = $tag->id();
                $options[$tag->id()] = $tag->label();
                $set = true;
            }
            $vid = $tag->get('vid')->target_id ?? '';
            if ($vid != SprowtAiExample::$vocab) {
                $form_state->setError($element, 'You can only add tags from the examples vocabulary.');
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
}
