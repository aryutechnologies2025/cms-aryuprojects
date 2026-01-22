<?php

namespace Drupal\sprowt_admin_override;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\sprowt_admin_override\Form\TokenTreeFilterForm;

class OverrideService implements TrustedCallbackInterface
{

    public static function trustedCallbacks()
    {
        return [
            'tokenTreePrerender'
        ];
    }

    public static function tokenTreePrerender($element)
    {
        return $element;
    }
}
