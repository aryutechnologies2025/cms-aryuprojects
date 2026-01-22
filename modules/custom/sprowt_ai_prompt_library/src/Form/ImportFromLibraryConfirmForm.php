<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\sprowt_ai_prompt_library\AiPromptLibraryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @todo Add a description for the form.
 */
class ImportFromLibraryConfirmForm extends ConfirmFormBase
{


    protected $entityTypeId;

    protected $entityType;

    protected $collection;

    /**
     * @var \Drupal\sprowt_ai_prompt_library\AiPromptLibraryService
     */
    protected $aiPromptLibraryService;


    public static function create(ContainerInterface $container)
    {
        $static = parent::create($container);
        $static->aiPromptLibraryService = $container->get('sprowt_ai_prompt_library.service');
        return $static;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_prompt_library_import_from_library_confirm';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuestion(): TranslatableMarkup
    {
        return $this->t('Are you sure you want to do this?');
    }

    public function getDescription(): TranslatableMarkup
    {
        return $this->t('This will import the following items:');
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl(): Url
    {
        $session = \Drupal::service('session');
        $cache = $session->get('sprowt_ai_prompt_library.importCache');
        $entityTypeId = $cache['entityType'];
        return new Url('sprowt_ai_prompt_library.import_from_library', [
            'entityType' => $entityTypeId,
        ]);
    }

    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildForm($form, $form_state);

        $session = \Drupal::service('session');
        $cache = $session->get('sprowt_ai_prompt_library.importCache');
        $entityTypeId = $cache['entityType'];
        $uuids = $cache['uuids'];
        $this->entityType = \Drupal::entityTypeManager()->getDefinition($entityTypeId);
        $this->collection = $this->aiPromptLibraryService->aiContentCollectionFromSource($entityTypeId);

        $lis = [];
        foreach ($this->collection as $item) {
            if(in_array($item['uuid'], $uuids)) {
                $lis[$item['uuid']] = [
                    '#type' => 'html_tag',
                    '#tag' => 'li',
                    '#value' => $item['title'],
                ];
            }
        }

        $form['description'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['description-wrap'],
            ],
            'intro' => [
                '#type' => 'markup',
                '#markup' => Markup::create('<p>' . (string) $this->getDescription() . '</p>'),
            ],
            'list' => [
                '#type' => 'html_tag',
                '#tag' => 'ul',
            ] + $lis
        ];

        $form['actions']['cancel'] = [
            '#type' => 'submit',
            '#value' => 'Cancel',
            '#attributes' => [
                'class' => ['button', 'button--danger'],
            ],
            '#submit' => [[$this, 'cancel']],
        ];

        return $form;
    }

    public function cancel(array &$form, FormStateInterface $form_state)
    {
        $session = \Drupal::service('session');
        $cache = $session->get('sprowt_ai_prompt_library.importCache') ?? [];
        $entityTypeId = $cache['entityType'] ?? 'ai_prompt';

        $session->delete('sprowt_ai_prompt_library.importCache');
        $form_state->setRedirectUrl(new Url('sprowt_ai_prompt_library.import_from_library', [
            'entityType' => $entityTypeId,
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $batchBuilder = new BatchBuilder();
        $collectionName = $this->entityType->getCollectionLabel();
        $collectionName = strtolower((string) $collectionName);
        $session = \Drupal::service('session');
        $cache = $session->get('sprowt_ai_prompt_library.importCache') ?? [];
        $entityTypeId = $cache['entityType'] ?? 'ai_prompt';
        $uuids = $cache['uuids'] ?? [];

        $batchBuilder->setTitle("Importing ". count($uuids) ." {$collectionName} from source");
        $batchBuilder->setFinishCallback([static::class, 'batchImportFinishedCallback']);
        foreach($uuids as $uuid) {
            $batchBuilder->addOperation([static::class, 'batchImportOneItem'], [$entityTypeId, $uuid]);
        }
        batch_set($batchBuilder->toArray());
    }

    public static function batchImportOneItem($entityTypeId, $uuid, &$context)
    {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $sandbox['processing'] = true;
        /** @var AiPromptLibraryService $service */
        $service = \Drupal::service('sprowt_ai_prompt_library.service');
        $entity = $service->importEntityFromSource($entityTypeId, $uuid);
        if(empty($context['results']['entities'])) {
            $context['results']['entities'] = [];
        };
        if(empty($context['results']['entityTypeId'])) {
            $context['results']['entityTypeId'] = $entityTypeId;
        }
        $context['results']['entities'][] = [
            'id' => $entity->id(),
            'label' => $entity->label(),
            'link' => $entity->toUrl()->toString(),
        ];
        $sandbox['processing'] = false;
        $context['finished'] = 1;
    }

    public static function batchImportFinishedCallback($success, $results, $operations)
    {
        if ($success) {
            $message = t('@count items imported.', ['@count' => count($results['entities'] ?? [])]);
        }
        else {
            $message = t('Finished with an error.');
        }
        \Drupal::messenger()->addStatus($message);

        $entityTypeId = $results['entityTypeId'] ?? 'ai_prompt';
        /** @var Session $session */
        $session = \Drupal::service('session');
        $session->remove('sprowt_ai_prompt_library.importCache');
        $entityType = \Drupal::entityTypeManager()->getDefinition($entityTypeId);
        $url = $entityType->getLinkTemplate('collection');
        $response = new RedirectResponse($url);
        return $response;
    }

}
