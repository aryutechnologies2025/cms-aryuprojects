<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\PublishAction;

class NodePublishAction extends PublishAction
{
    use AddRevisionToActionBase;

    public function getRevisionMessage($entity)
    {
        return 'Node published';
    }
}
