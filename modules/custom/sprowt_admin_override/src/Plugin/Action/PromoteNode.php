<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

class PromoteNode extends \Drupal\node\Plugin\Action\PromoteNode
{
    use AddRevisionToActionBase;

    public function getRevisionMessage($entity)
    {
        return 'Node promoted';
    }
}
