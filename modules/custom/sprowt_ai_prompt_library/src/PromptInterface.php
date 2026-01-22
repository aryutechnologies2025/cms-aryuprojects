<?php declare(strict_types=1);

namespace Drupal\sprowt_ai_prompt_library;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a prompt entity type.
 */
interface PromptInterface extends ContentEntityInterface, EntityChangedInterface
{

}
