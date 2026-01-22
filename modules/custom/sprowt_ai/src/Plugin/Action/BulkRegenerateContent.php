<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Plugin\Action;

use Drupal;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\sprowt_ai\AiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Bulk Regenerate Content action.
 *
 * @Action(
 *   id = "sprowt_ai_bulk_regenerate_content",
 *   label = @Translation("Bulk Regenerate Content"),
 *   type = "node",
 *   category = @Translation("Sprowt AI"),
 *   confirm_form_route_name = "sprowt_ai.bulk_regenerate_content"
 * )
 *
 * @DCG
 * For updating entity fields consider extending FieldUpdateActionBase.
 * @see \Drupal\Core\Field\FieldUpdateActionBase
 *
 * @DCG
 * In order to set up the action through admin interface the plugin has to be
 * configurable.
 * @see https://www.drupal.org/project/drupal/issues/2815301
 * @see https://www.drupal.org/project/drupal/issues/2815297
 *
 * @DCG
 * The whole action API is subject of change.
 * @see https://www.drupal.org/project/drupal/issues/2011038
 */
class BulkRegenerateContent extends ActionBase implements ContainerFactoryPluginInterface
{

    /**
     * {@inheritdoc}
     */
    public function __construct(
        array                      $configuration,
                                   $plugin_id,
                                   $plugin_definition,
        private readonly AiService $sprowtAiService,
    )
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self
    {
        return new self(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('sprowt_ai.service'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function access($entity, AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool
    {
        $access = AiService::accessAi($account);
        if($return_as_object) {
            return $access;
        }
        return $access->isAllowed();
    }

    public function executeMultiple(array $entities) {
        $ids = [];
        foreach ($entities as $entity) {
            if($this->sprowtAiService->entityHasPrompts($entity)) {
                $ids[] = $entity->id();
            }
        }
        $currentUser = \Drupal::currentUser();
        \Drupal::service('tempstore.private')->get('bulk_regenerate_nodes')->set('bulk_regenerate_nodes.' . $currentUser->id(), $ids);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ContentEntityInterface $entity = NULL): void
    {
        $this->executeMultiple([$entity]);
    }

}
