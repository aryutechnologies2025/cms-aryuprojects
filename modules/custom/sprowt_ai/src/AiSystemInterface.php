<?php declare(strict_types=1);

namespace Drupal\sprowt_ai;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\sprowt_ai\Entity\AiSystem;

/**
 * Provides an interface defining an ai system entity type.
 */
interface AiSystemInterface extends ConfigEntityInterface
{

    public function isEnabled();

    public function isDefault();

    public static function loadByUuid(string $uuid): ?AiSystem;
}
