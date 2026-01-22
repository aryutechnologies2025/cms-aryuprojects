<?php

namespace Drupal\sprowt_admin_override\Plugin\Action;

trait AddRevisionToActionBase
{

    public function getRevisionMessage($entity)
    {
        return 'Entity updated';
    }

    public function execute($entity = NULL)
    {

        if(!empty($entity)) {
            $currentUser = \Drupal::currentUser();
            $entity->setNewRevision();
            $entity->setRevisionUserId($currentUser->id());
            $entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $entity->setRevisionLogMessage($this->getRevisionMessage($entity));
        }
        parent::execute($entity);
    }
}
