<?php

namespace Drupal\relative_date_widget\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a DateTimeRelativeFormatConstraint constraint.
 *
 * @Constraint(
 *   id = "RelativeDateWidgetDateTimeRelativeFormatConstraint",
 *   label = @Translation("DateTimeRelativeFormatConstraint", context = "Validation"),
 * )
 *
 * @DCG
 * To apply this constraint on third party field types. Implement
 * hook_field_info_alter().
 */
class DateTimeRelativeFormatConstraintConstraint extends Constraint
{

    /**
     * Message for when the value isn't a string.
     *
     * @var string
     */
    public $badType = "The datetime value must be a string.";

    /**
     * Message for when the value isn't in the proper format.
     *
     * @var string
     */
    public $badFormat = "The datetime value '@value' is invalid for the format '@format'";

    /**
     * Message for when the value did not parse properly.
     *
     * @var string
     */
    public $badValue = "The datetime value '@value' did not parse properly.";

}
