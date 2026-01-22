<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

class StickyNode extends \Drupal\node\Plugin\Action\StickyNode
{
    use AddRevisionToActionBase;

    public function getRevisionMessage($entity)
    {
        return 'Node stickied';
    }
}
