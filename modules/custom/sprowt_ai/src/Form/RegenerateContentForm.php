<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sprowt_ai\AiService;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a Sprowt AI form.
 */
class RegenerateContentForm extends ConfirmFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_regenerate_content';
    }


    public function getQuestion() {
        return $this->t('Are you sure?');
    }

    public function getDescription()
    {
        return $this->t('This will re-generate content for all prompted fields for the entity and any inline blocks attached to the content. This action cannot be undone.');
    }

    public function getEntity($formState = null) {
        if($formState instanceof FormStateInterface) {
            $entityType = $formState->get('entity_type');
            $entityId = $formState->get('entity_id');
        }
        if(empty($entityType) || empty($entityId)) {
            $routeMatch = \Drupal::routeMatch();
            $entityType = $routeMatch->getParameter('entity_type');
            $entityId = $routeMatch->getParameter('entity_id');
        }
        return static::loadEntity($entityType, $entityId);
    }

    public static function loadEntity($entityType, $entityId)
    {
        if (strpos($entityId, 'rvid:') === 0) {
            $rvid = str_replace('rvid:', '', $entityId);
            return \Drupal::entityTypeManager()->getStorage($entityType)->loadRevision($rvid);
        }
        return \Drupal::entityTypeManager()->getStorage($entityType)->load($entityId);
    }

    /**
     * Returns the route to go to if the user cancels the action.
     *
     * @return \Drupal\Core\Url
     *   A URL object.
     */
    public function getCancelUrl() {
        $entity = $this->getEntity();
        if(empty($entity)) {
            return Url::fromRoute('<front>');
        }

        return $entity->toUrl('edit-form');
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $entity = $this->getEntity();
        if (empty($entity)) {
            \Drupal::messenger()->addError('Entity not found!');
        }

        $form_state->set('entity_type', $entity->getEntityTypeId());
        $form_state->set('entity_id', $entity->id());

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // @todo Validate the form here.
        // Example:
        // @code
        //   if (mb_strlen($form_state->getValue('message')) < 10) {
        //     $form_state->setErrorByName(
        //       'message',
        //       $this->t('Message should be at least 10 characters.'),
        //     );
        //   }
        // @endcode
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $entity = $this->getEntity($form_state);
        if(empty($entity)) {
            \Drupal::messenger()->addError('Entity not found!');
            return;
        }
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle('Regenerating content for entity: "' . $entity->label() . '"');
        $batchBuilder->setInitMessage('Processing...');
        $entityId = $entity->id();
        if($entity->getEntityType()->isRevisionable()) {
            $entityId = 'rvid:' . $entity->getRevisionId();
        }
        $batchBuilder->addOperation([static::class, 'regenerateContent'], [
            $entity->getEntityTypeId(),
            $entityId
        ]);
        $batchBuilder->setFinishCallback([static::class, 'batchFinished']);
        $batchBuilder->setProgressive(true);
        batch_set($batchBuilder->toArray());
    }


    public static function regenerateContent($entityType, $entityId, &$context)
    {
        /** @var AiService $service */
        $service = \Drupal::service('sprowt_ai.service');
        $entity = static::loadEntity($entityType, $entityId);
        $updated = $service->generateContentForEntity($entity);
        $context['results']['entityType'] = $entityType;
        $context['results']['entityId'] = $entityId;
        $context['results']['updated'] = $updated;

    }

    public static function batchFinished($success, $results, $operations) {

        $entity = static::loadEntity($results['entityType'], $results['entityId']);
        if($entity->getEntityTypeId() == 'node') {
            $url = $entity->toUrl();
        }
        else {
            $url = $entity->toUrl('edit-form');
        }
        $updated = !empty($results['updated']);
        $message = $updated ? 'Content regenerated successfully!' : 'Content was not regenerated.';
        \Drupal::messenger()->addStatus($message);

        return new RedirectResponse($url->toString());
    }
}
