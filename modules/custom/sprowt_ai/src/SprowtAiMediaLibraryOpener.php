<?php

namespace Drupal\sprowt_ai;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\Entity\Media;
use Drupal\media_library\MediaLibraryOpenerInterface;
use Drupal\media_library\MediaLibraryState;
use Drupal\sprowt_ai\Element\ClaudePrompt;

class SprowtAiMediaLibraryOpener implements MediaLibraryOpenerInterface
{

    public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
        return AccessResult::allowed();
    }

    public function getSelectionResponse(MediaLibraryState $state, array $selected_ids) {
        $options = $state->getOpenerParameters();
        $options = ['id' => $options['element_id']];
        $entities = Media::loadMultiple($selected_ids);
        $selectedUuids = [];
        foreach ($entities as $entity) {
            $selectedUuids[] = $entity->uuid();
        }

        $response = new AjaxResponse();
        $response->addCommand(new InvokeCommand(
            ClaudePrompt::fieldSelector($options),
            'trigger',
            [
                'mediaLibraryInsert',
                [$selectedUuids]
            ]
        ));
        return $response;
    }
}
