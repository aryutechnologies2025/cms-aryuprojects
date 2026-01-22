<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

class UnstickyNode extends \Drupal\node\Plugin\Action\UnstickyNode
{
    use AddRevisionToActionBase;

    public function getRevisionMessage($entity)
    {
        return 'Node un-stickied';
    }
}
