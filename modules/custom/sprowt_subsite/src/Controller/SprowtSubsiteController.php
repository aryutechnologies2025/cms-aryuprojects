<?php

namespace Drupal\sprowt_subsite\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\sprowt_subsite\SettingsManager;
use Drupal\sprowt_subsite\SubsiteService;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Sprowt Subsite routes.
 */
class SprowtSubsiteController extends ControllerBase
{

    /**
     * Builds the response.
     */
    public function configureSubsite($node)
    {
        if ($node instanceof Node) {
            $subsite = SettingsManager::getSubsiteFromNode($node);
            if ($subsite instanceof Node) {
                $url = $subsite->toUrl('edit-form');
            } else {
                $url = $node->toUrl('edit-form');
            }
        } else {
            $url = Url::fromUserInput('/');
            \Drupal::messenger()->addError('No node provided');
        }
        $request = \Drupal::request();
        $query = $request->query->all();
        $url->setOption('query', $query);

        return new RedirectResponse($url->toString());
    }

    public function subsiteHomePageView($node)
    {
        if ($node instanceof Node) {
            /** @var SubsiteService $service */
            $service = \Drupal::service('sprowt_subsite.service');
            $homepage = $service->getSubsiteHomePageFromNode($node);
            if ($homepage instanceof Node) {
                $url = $homepage->toUrl();
            } else {
                \Drupal::messenger()->addError('No subsite home page found');
                $subsite = SettingsManager::getSubsiteFromNode($node);
                if ($subsite instanceof Node) {
                    $url = $subsite->toUrl('edit-form');
                } else {
                    $url = $node->toUrl('edit-form');
                }
            }
        } else {
            $url = Url::fromUserInput('/');
            \Drupal::messenger()->addError('No node provided');
        }
        $request = \Drupal::request();
        $query = $request->query->all();
        $url->setOption('query', $query);

        return new RedirectResponse($url->toString());
    }

    public static function configureSubsiteAccess(AccountInterface $account)
    {
        $permission = $account->hasPermission('edit any subsite content')
            || $account->hasPermission('edit own subsite content')
            || $account->hasPermission('administer nodes');

        if ($permission) {
            $routeMatch = \Drupal::routeMatch();
            $node = $routeMatch->getParameter('node');
            if (!empty($node) && !$node instanceof Node) {
                $node = Node::load($node);
            }
            if(!empty($node) && $node->bundle() != 'subsite') {
                $subsite = SettingsManager::getSubsiteFromNode($node);
            }
            return AccessResult::allowedIf(isset($subsite) && $subsite instanceof Node);
        }

        return AccessResult::forbidden();
    }

    public static function subsiteHomePageViewAccess(AccountInterface $account)
    {
        $permission = $account->hasPermission('access content');

        if ($permission) {
            $routeMatch = \Drupal::routeMatch();
            $node = $routeMatch->getParameter('node');
            if (!empty($node) && !$node instanceof Node) {
                $node = Node::load($node);
            }
            if(!empty($node)) {
                $subsite = SettingsManager::getSubsiteFromNode($node);
            }
            return AccessResult::allowedIf(isset($subsite) && $subsite instanceof Node);
        }

        return AccessResult::forbidden();
    }

}
