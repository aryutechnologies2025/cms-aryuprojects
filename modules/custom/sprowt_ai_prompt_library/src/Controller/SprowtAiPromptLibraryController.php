<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\sprowt_ai_prompt_library\AiPromptLibraryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Sprowt AI prompt library routes.
 */class SprowtAiPromptLibraryController extends ControllerBase
{

    protected AiPromptLibraryService $sprowtAiPromptLibraryService;

    /**
     * The controller constructor.
     */
    public function __construct(
        AiPromptLibraryService $sprowtAiPromptLibraryService,
    ){
        $this->sprowtAiPromptLibraryService = $sprowtAiPromptLibraryService;
    }


    protected function getCachedData($cidSuffix)
    {
        $data = null;
        $cid = 'sprowt_ai_prompt_library:' . $cidSuffix;
        if($_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3'
            && $_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3-source'
        ) {
            return $data;
        }
        if ($cache = \Drupal::cache()->get($cid)) {
            $data = $cache->data;
        }
        return $data;
    }

    protected function saveDataToCache($cidSuffix, $data, $tags = [])
    {
        if($_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3'
            && $_SERVER['SPROWTHQ_SITE_NAME'] != 'sprowt3-source'
        ) {
            return $data;
        }
        $cid = 'sprowt_ai_prompt_library:' . $cidSuffix;
        if(empty($data)) {
            \Drupal::cache()->delete($cid);
            return $data;
        }
        \Drupal::cache()->set($cid, $data, CacheBackendInterface::CACHE_PERMANENT, $tags);
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): self
    {
        return new self(
            $container->get('sprowt_ai_prompt_library.service'),
        );
    }

    public function promptMap(Request $request): JsonResponse
    {
        $refresh = $request->query->get('refresh');
        $map = $this->sprowtAiPromptLibraryService->localPromptMap(!empty($refresh));
        return new JsonResponse($map);
    }

    public function aiContentCollectionRequest(Request $request): JsonResponse
    {
        $entityTypeId = $request->query->get('entity_type');
        $cid = 'aiContentCollectionRequest:' . $entityTypeId;
        $cached = $this->getCachedData($cid);
        if (!empty($cached)) {
            return new JsonResponse($cached);
        }
        $collection = $this->sprowtAiPromptLibraryService->aiContentCollectionRequest($entityTypeId);
        $cacheTags = [];
        $cacheTags[] = $entityTypeId . '_list';
        $this->saveDataToCache($cid, $collection, $cacheTags);

        return new JsonResponse($collection);
    }

    public function fetchSourceEntity(Request $request): JsonResponse
    {
        $entityTypeId = $request->query->get('entity_type');
        $uuid = $request->query->get('uuid');
        $cid = 'sourceEntity:' . $entityTypeId . ':' . $uuid;
        $cached = $this->getCachedData($cid);
        if (!empty($cached)) {
            return new JsonResponse($cached);
        }

        $entity = $this->sprowtAiPromptLibraryService->fetchEntity($entityTypeId, $uuid);
        $cacheTags = [];
        if(!empty($entity['_entities'][0]['_exportInfo'])) {
            $exportInfo = $entity['_entities'][0]['_exportInfo'];
            $cacheTags[] = $entityTypeId . ':' . $exportInfo['entity_id'];
        }
        $this->saveDataToCache($cid, $entity, $cacheTags);

        return new JsonResponse($entity);
    }
}
