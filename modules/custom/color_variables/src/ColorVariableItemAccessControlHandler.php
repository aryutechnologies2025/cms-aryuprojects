<?php

namespace Drupal\color_variables;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the theme color variables entity type.
 */
class ColorVariableItemAccessControlHandler extends EntityAccessControlHandler
{

    /**
     * {@inheritdoc}
     */
    protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account)
    {

        switch ($operation) {
            case 'view revision':
            case 'view all revisions':
            case 'view':
                return AccessResult::allowedIfHasPermission($account, 'view theme color variables');
            case 'revert':
            case 'update':
                return AccessResult::allowedIfHasPermissions($account, ['edit theme color variables', 'administer theme color variables'], 'OR');
            case 'delete revision':
            case 'delete':
                return AccessResult::allowedIfHasPermissions($account, ['delete theme color variables', 'administer theme color variables'], 'OR');

            default:
                // No opinion.
                return AccessResult::neutral();
        }

    }

    /**
     * {@inheritdoc}
     */
    protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL)
    {
        return AccessResult::allowedIfHasPermissions($account, ['create theme color variables', 'administer theme color variables'], 'OR');
    }

}
