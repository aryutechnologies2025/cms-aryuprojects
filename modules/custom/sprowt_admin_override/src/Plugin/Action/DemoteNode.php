<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

class DemoteNode extends \Drupal\node\Plugin\Action\DemoteNode
{
    use AddRevisionToActionBase;

    public function getRevisionMessage($entity)
    {
        return 'Node removed from front page';
    }
}
