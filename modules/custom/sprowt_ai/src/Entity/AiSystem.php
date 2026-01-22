<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\sprowt_ai\AiSystemInterface;

/**
 * Defines the ai system entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_system",
 *   label = @Translation("AI system user"),
 *   label_collection = @Translation("AI system users"),
 *   label_singular = @Translation("ai system user"),
 *   label_plural = @Translation("ai system users"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ai system user",
 *     plural = "@count ai system users",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\sprowt_ai\AiSystemListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sprowt_ai\Form\AiSystemForm",
 *       "edit" = "Drupal\sprowt_ai\Form\AiSystemForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_system",
 *   admin_permission = "administer ai_system",
 *   links = {
 *     "collection" = "/admin/config/services/sprowt-ai/ai-system",
 *     "add-form" = "/admin/config/services/sprowt-ai/ai-system/add",
 *     "edit-form" = "/admin/config/services/sprowt-ai/ai-system/{ai_system}",
 *     "delete-form" = "/admin/config/services/sprowt-ai/ai-system/{ai_system}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "is_default"
 *   },
 * )
 */
final class AiSystem extends ConfigEntityBase implements AiSystemInterface
{

    /**
     * The example ID.
     */
    protected string $id;

    /**
     * The example label.
     */
    protected string $label;

    /**
     * The example description.
     */
    protected string $description;

    protected ?bool $is_default;

    public function isEnabled()
    {
        return $this->status();
    }

    public function isDefault()
    {
        return $this->get('is_default') ?? false;
    }

    public static function loadByUuid(string $uuid): ?AiSystem
    {
        $entity_type_repository = \Drupal::service('entity_type.repository');
        $entity_type_manager = \Drupal::entityTypeManager();
        $storage = $entity_type_manager->getStorage($entity_type_repository->getEntityTypeFromClass(static::class));
        $entities = $storage->loadByProperties(['uuid' => $uuid]);
        if(!empty($entities)) {
            return array_shift($entities);
        }

        return null;
    }

}
