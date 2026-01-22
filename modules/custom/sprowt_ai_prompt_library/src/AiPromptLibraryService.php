<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library;

use Drupal\content_import_export\Exporter;
use Drupal\content_import_export\Importer;
use Drupal\content_library\ContentLibraryApiService;
use Drupal\content_library\ContentLibraryException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\sprowt_ai_prompt_library\Entity\Prompt;
use Drupal\sprowt_content\SprowtContentService;
use Drupal\taxonomy\Entity\Term;

/**
 * @todo Add class description.
 */
class AiPromptLibraryService
{

    protected EntityTypeManagerInterface $entityTypeManager;

    protected ContentLibraryApiService $contentLibraryApiService;

    protected Exporter $exporter;

    protected Importer $importer;

    /**
     * Constructs an AiPromptLibraryService object.
     */
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        ContentLibraryApiService $contentLibraryApiService,
        Exporter $exporter,
        Importer $importer
    )
    {
        $this->entityTypeManager = $entityTypeManager;
        $this->contentLibraryApiService = $contentLibraryApiService;
        $this->exporter = $exporter;
        $this->importer = $importer;
    }


    public function localPromptMap($refresh = false) {
        $state = \Drupal::state();
        $cache = $state->get('sprowt_ai.prompt_library_map_cache') ?? null;
        if(!empty($cache) && !$refresh) {
            $now = new \DateTime();
            $expires = new \DateTime($cache['expires']);
            if ($now < $expires) {
                return $cache['data'];
            }
        }

        $prompts = Prompt::loadMultiple();
        $promptMap = [];
        /** @var Prompt $prompt */
        foreach($prompts as $prompt) {
            if(!$prompt->isEnabled()) {
                continue;
            }
            $item = [
                'id' => $prompt->id(),
                'uuid' => $prompt->uuid(),
                'label' => $prompt->label(),
                'description' => $prompt->get('description')->value ?? '',
                'source' => 'local'
            ];
            $tagsList = $prompt->get('tags');
            $tags = [];
            foreach($tagsList as $tagItem) {
                $tag = $tagItem->entity;
                if($tag instanceof Term) {
                    $tagName = $tag->label();
                    if (!in_array($tagName, $tags)) {
                        $tags[] = $tagName;
                    }
                }
            }
            $item['tags'] = $tags;
            $promptMap[$prompt->uuid()] = $item;
        }

        $cache = [
            'data' => $promptMap,
            'expires' => (new \DateTime('+1 hour'))->format('c')
        ];
        $state->set('sprowt_ai.prompt_library_map_cache', $cache);

        return $promptMap;
    }

    public function sourcePromptMap($sourceUrl, $refresh = false)
    {
        $url = $sourceUrl . '/sprowt-ai-prompt-library/prompt-map';
        $options = [];
        if(!empty($refresh)) {
            $options = [
                'query' => [
                    'refresh' => $refresh
                ]
            ];
        }
        $res = $this->contentLibraryApiService->get($url, $options);
        if(!empty($res['error'])) {
            throw new ContentLibraryException($res['message']);
        }
        $return = [];
        foreach ($res as $item) {
            $item['source'] = $sourceUrl;
            $return[$item['uuid']] = $item;
        }

        return $return;
    }

    public function promptMap($refresh = false)
    {
        $state = \Drupal::state();
        $cache = $state->get('sprowt_ai.prompt_library_all_map_cache') ?? null;
        if(!empty($cache) && !$refresh) {
            $now = new \DateTime();
            $expires = new \DateTime($cache['expires']);
            if ($now < $expires) {
                return $cache['data'];
            }
        }

        $localMap = $this->localPromptMap($refresh);
        if($_SERVER['SPROWTHQ_SITE_NAME'] == 'sprowt3-source') {
            $url = Url::fromRoute('<front>');
            $url->setAbsolute(true);
            $url = $url->toString();
            $url = rtrim($url, '/');
            foreach($localMap as $uuid => $item) {
                $localMap[$uuid]['source'] = $url;
            }
        }
        $sources = \Drupal::config('content_library.settings')->get('source_sites') ?? [];
        $promptMap = [];
        foreach($sources as $source) {
            try {
                $sourceMap = $this->sourcePromptMap($source, $refresh);
            }
            catch (ContentLibraryException $e) {
                Error::logException(\Drupal::logger('sprowt_ai_prompt_library'), $e);
                $sourceMap = [];
            }
            $promptMap = array_merge($promptMap, $sourceMap);
        }
        $promptMap = array_merge($promptMap, $localMap);
        uasort($promptMap, function ($a, $b) {
            if($a['source'] == $b['source']) {
                return strcmp($a['label'], $b['label']);
            }
            if($a['source'] == 'local') {
                return -1;
            }
            return strcmp($a['source'], $b['source']);
        });

        $cache = [
            'data' => $promptMap,
            'expires' => (new \DateTime('+15 minutes'))->format('c')
        ];
        $state->set('sprowt_ai.prompt_library_all_map_cache', $cache);

        return $promptMap;
    }

    public function loadPrompt($source, $promptUuid)
    {
        if($source == 'local') {
            return Prompt::loadByUuid($promptUuid);
        }

        $uri = '/sprowt-content/entities-by-uuid';
        $q = [
            'entityType' => 'ai_prompt',
            'uuids' => [$promptUuid]
        ];
        $res = $this->contentLibraryApiService->get($source . $uri, [
            'query' => $q
        ]);
        if(!empty($res['error'])) {
            $e = new ContentLibraryException($res['message']);
            Error::logException(\Drupal::logger('sprowt_ai_prompt_library'), $e);
            return null;
        }

        $topEntityArrays = $res['_entities'] ?? [];
        if(empty($topEntityArrays)) {
            $prompt = Prompt::create([
                'label' => 'No prompt found',
                'uuid' => $promptUuid,
                'enabled' => true,
                'description' => 'Prompt missing from library.',
                'id' => rand(9999, 999999999999)
            ]);
            \Drupal::messenger()->addWarning("Prompt missing from library on $source");
        }
        else {
            $content = $res['_content'] ?? [];
            $promptArray = array_shift($topEntityArrays);
            $prompt = Prompt::create([
                'uuid' => $promptUuid,
                'label' => $promptArray['label'],
                'description' => $promptArray['description'],
                'enabled' => true,
                'created' => (int) $promptArray['created'],
                'changed' => (int) $promptArray['changed'],
                'id' => $promptArray['id']
            ]);
        }

        $prompt->setSource($source);

        $examples = [];
        $documents = [];
        $contexts = [];

        $prompt->set('prompt', [
            'value' => $promptArray['prompt'][0]['value'] ?? ''
        ]);

        $requests = [
            'sprowt_ai_example' => [],
            'media' => [],
            'sprowt_ai_context' => []
        ];

        $loadByUuid = function($entityType, $uuid) {
            $entity = $this->entityTypeManager->getStorage($entityType)->loadByProperties(['uuid' => $uuid]);
            if (empty($entity)) {
                return null;
            }
            return array_shift($entity);
        };

        $attachedEntities = $promptArray['prompt'][0]['_attachedEntities'] ?? [];
        foreach($attachedEntities as $contentKey) {
            $contentArray = $content[$contentKey] ?? [];
            if(empty($contentArray)) {
                continue;
            }
            $exportInfo = $contentArray['_exportInfo'];
            $entityType = $exportInfo['entity_type'];
            $entityUuid = $exportInfo['uuid'];
            $entityId = $exportInfo['entity_id'];
            $entity = $loadByUuid($entityType, $entityUuid);
            if(!empty($entity)) {
                switch($entityType) {
                    case 'sprowt_ai_example':
                        $examples[] = $entity;
                        break;
                    case 'media':
                        $documents[] = $entity;
                        break;
                    case 'sprowt_ai_context':
                        $contexts[] = $entity;
                        break;
                }
                continue;
            }

            $requests[$entityType][] = $entityId;
        }
        $import = function($requestIds, $entityType) use (&$examples, &$documents, &$contexts, $source) {
            if (empty($requestIds)) {
                return;
            }
            try {
                $importArray = $this->contentLibraryApiService->requestEntities($source, $entityType, $requestIds);
            }
            catch (ContentLibraryException $e) {
                Error::logException(\Drupal::logger('sprowt_ai_prompt_library'), $e);
                return;
            }
            if(empty($importArray)) {
                return;
            }
            $entities = $this->importer->import($importArray);
            if(!empty($entities)) {
                switch ($entityType) {
                    case 'sprowt_ai_example':
                        $examples = array_merge($examples, $entities);
                        break;
                    case 'media':
                        $documents = array_merge($documents, $entities);
                        break;
                    case 'sprowt_ai_context':
                        $contexts = array_merge($contexts, $entities);
                        break;
                }

            }
        };
        foreach($requests as $entityType => $requestIds) {
            if(!empty($requestIds)) {
                $import($requestIds, $entityType);
            }
        }
        $addToPrompt = function($entities) use (&$prompt) {
            $value = [];
            $field = null;
            foreach($entities as $entity) {
                if(empty($field)) {
                    $entityType = $entity->getEntityTypeId();
                    switch($entityType) {
                        case 'sprowt_ai_example':
                            $field = 'connected_examples';
                            break;
                        case 'media':
                            $field = 'connected_documents';
                            break;
                        case 'sprowt_ai_context':
                            $field = 'connected_contexts';
                            break;
                    }
                }
                $value[] = [
                    'target_id' => $entity->id(),
                ];
            }
            if(!empty($value)) {
                $prompt->set($field, $value);
            }
        };
        $addToPrompt($examples);
        $addToPrompt($documents);
        $addToPrompt($contexts);

        return $prompt;
    }

    public function aiContentCollectionFromSource($entityTypeId) {
        $sources = \Drupal::config('content_library.settings')->get('source_sites') ?? [];
        if(empty($sources)) {
            return [];
        }
        $sourceUrl = array_shift($sources);
        $url = $sourceUrl . '/sprowt-ai-prompt-library/ai_content_collection_request';
        $options = [
            'query' => [
                'entity_type' => $entityTypeId,
            ]
        ];
        $res = $this->contentLibraryApiService->get($url, $options);
        if(!empty($res['error'])) {
            throw new ContentLibraryException($res['message']);
        }

        $localCollection = $this->aiContentCollectionRequest($entityTypeId);
        $localUuids = [];
        foreach ($localCollection as $collection) {
            $localUuids[] = $collection['uuid'];
        }
        $filtered = array_filter($res, function ($item) use ($localUuids) {
            return !in_array($item['uuid'], $localUuids);
        });

        return $filtered;
    }

    public function aiContentCollectionRequest($entityTypeId) {
        $return = [];
        $entities = \Drupal::entityTypeManager()->getStorage($entityTypeId)->loadMultiple();
        foreach ($entities as $entity) {
            $tags = [];
            if($entity->hasField('tags')) {
                $list = $entity->get('tags');
                foreach($list as $tagItem) {
                    $term = $tagItem->entity;
                    if($term instanceof Term) {
                        $tagName = $term->label();
                        if (!in_array($tagName, $tags)) {
                            $tags[] = $tagName;
                        }
                    }
                }
            }
            $return[] = [
                'id' => $entity->id(),
                'title' => $entity->label(),
                'uuid' => $entity->uuid(),
                'link' => $entity->toUrl('edit-form', ['absolute' => true])->toString(),
                'tags' => $tags
            ];
        }

        return $return;
    }

    public function fetchEntityFromSource($entityTypeId, $uuid) {
        $sources = \Drupal::config('content_library.settings')->get('source_sites') ?? [];
        if(empty($sources)) {
            return [];
        }
        $sourceUrl = array_shift($sources);
        $options = [
            'query' => [
                'entity_type' => $entityTypeId,
                'uuid' => $uuid,
            ]
        ];
        $url = $sourceUrl . '/sprowt-ai-prompt-library/fetch_source_entity';
        $res = $this->contentLibraryApiService->get($url, $options);
        if(!empty($res['error'])) {
            throw new ContentLibraryException($res['message']);
        }

        return $res;
    }

    public function fetchEntity($entityTypeId, $uuid)
    {
        $entities = \Drupal::entityTypeManager()->getStorage($entityTypeId)->loadByProperties(['uuid' => $uuid]);
        if(empty($entities)) {
            $return = [];
        }
        else {
            $entity = array_shift($entities);
            $return = $this->exporter->export([[
                'entity' => $entity
            ]]);
        }

        return $return;
    }

    public function importEntityFromSource($entityTypeId, $uuid)
    {
        $entities = \Drupal::entityTypeManager()->getStorage($entityTypeId)->loadByProperties(['uuid' => $uuid]);
        if(!empty($entities)) {
            //don't import if already exists
            $entity = array_shift($entities);
            return $entity;
        }
        $export = $this->fetchEntityFromSource($entityTypeId, $uuid);
        if (empty($export)) {
            return null;
        }

        $entities = $this->importer->import($export);
        if (empty($entities)) {
            return null;
        }
        $entity = array_shift($entities);
        return $entity;
    }

    public function deLocalizePrompt($promptText) {
        $tokenService = \Drupal::token();
        $tokenInfo = $tokenService->scan($promptText);
        $url = Url::fromRoute('<front>');
        $url->setAbsolute(true);
        $url = $url->toString();
        $url = rtrim($url, '/');
        foreach($tokenInfo as $type => $tokens) {
            foreach ($tokens as $token) {
                if (strpos($token, '[sprowt_ai:prompt:local:') !== false) {
                    $newToken = str_replace(':local:', ':' . $url . ':', $token);
                    $promptText = str_replace($token, $newToken, $promptText);
                }
            }
        }
        return $promptText;
    }

}
