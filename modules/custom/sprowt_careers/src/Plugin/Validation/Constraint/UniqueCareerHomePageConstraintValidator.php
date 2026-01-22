<?php

namespace Drupal\sprowt_careers\Plugin\Validation\Constraint;

use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueCareerHomePageConstraint constraint.
 */
class UniqueCareerHomePageConstraintValidator extends ConstraintValidator
{

    /**
     * {@inheritdoc}
     * @var Node $entity
     */
    public function validate($entity, Constraint $constraint)
    {

        $stop = true;
        // @DCG Validate the entity here.
        if ($entity->isNew() && $entity->bundle() == 'career_page') {
            $pageType = $entity->field_career_page_type->value;
            if($pageType == 'home') {
                $currentHome = sprowt_careers_get_homepage();
                if(!empty($currentHome) && $currentHome->uuid() != $entity->uuid()) {
                    /** @var Node $currentHome */
                    $editUrl = $currentHome->toUrl('edit-form')->toString();
                    $errorMsg = Markup::create('There can be only one careers home page. Go <a href="'.$editUrl.'">here</a> to edit it.');
                    $this->context->buildViolation((string) $errorMsg)
                        // @DCG The path depends on entity type. It can be title, name, etc.
                        ->atPath('field_career_page_type')
                        ->addViolation();
                }
            }
        }
    }
}
