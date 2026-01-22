<?php

namespace Drupal\relative_date_widget\Plugin\Validation\Constraint;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DateTimeRelativeFormatConstraint constraint.
 */
class DateTimeRelativeFormatConstraintConstraintValidator extends ConstraintValidator
{

    /**
     * {@inheritdoc}
     */
    public function validate($item, Constraint $constraint)
    {
        /* @var $item \Drupal\relative_date_widget\Plugin\Field\FieldType\DateTimeItem */
        if (isset($item)) {
            $value = $item->getValue()['value'];
            if (!is_string($value)) {
                $this->context->addViolation($constraint->badType);
            }
            else {
                try {
                    $date = new DateTimePlus($value);
                }
                catch (\InvalidArgumentException $e) {
                    $this->context->addViolation($constraint->badValue, [
                        '@value' => $value,
                    ]);
                    return;
                }
                catch (\UnexpectedValueException $e) {
                    $this->context->addViolation($constraint->badValue, [
                        '@value' => $value,
                    ]);
                    return;
                }
                if ($date === NULL || $date->hasErrors()) {
                    $this->context->addViolation($constraint->badValue, [
                        '@value' => $value
                    ]);
                }
            }
        }
    }

}
