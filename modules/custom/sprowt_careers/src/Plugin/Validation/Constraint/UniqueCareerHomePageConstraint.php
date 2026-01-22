<?php

namespace Drupal\sprowt_careers\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides an UniqueCareerHomePageConstraint constraint.
 *
 * @Constraint(
 *   id = "UniqueCareerHomePageConstraint",
 *   label = @Translation("UniqueCareerHomePageConstraint", context = "Validation"),
 * )
 *
 * @DCG
 * To apply this constraint, see https://www.drupal.org/docs/drupal-apis/entity-api/entity-validation-api/providing-a-custom-validation-constraint.
 */
class UniqueCareerHomePageConstraint extends Constraint
{

    public $errorMessage = 'There can be only one career home page on your site.';

}
