<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;

/**
 * Plugin implementation of the 'System User Formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "sprowt_ai_system_user_formatter",
 *   label = @Translation("System User Formatter"),
 *   field_types = {"sprowt_ai_ai_system_select"},
 * )
 */
class SystemUserFormatter extends EntityReferenceLabelFormatter {


}
