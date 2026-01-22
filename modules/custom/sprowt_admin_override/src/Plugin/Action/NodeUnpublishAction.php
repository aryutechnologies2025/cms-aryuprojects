<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\UnpublishAction;

class NodeUnpublishAction extends UnpublishAction
{
    use AddRevisionToActionBase;

    public function getRevisionMessage($entity)
    {
        return 'Node unpublished';
    }
}
