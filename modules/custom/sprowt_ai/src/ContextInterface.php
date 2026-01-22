<?php declare(strict_types = 1);

namespace Drupal\sprowt_ai;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a context entity type.
 */
interface ContextInterface extends ContentEntityInterface, EntityChangedInterface {

}
