<?php


namespace Drupal\inline_block_content\Plugin\Block;

use Drupal\block_content\Access\RefinableDependentAccessInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Plugin\Block\InlineBlock as InlineBlockBase;

class InlineBlock extends InlineBlockBase
{

    /**
     * Load entity by UUID if not found
     * @return RefinableDependentAccessInterface|\Drupal\block_content\BlockContentInterface|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    protected function getEntity()
    {
        if (!isset($this->blockContent)) {
            if (!empty($this->configuration['block_serialized'])) {
                $this->blockContent = unserialize($this->configuration['block_serialized']);
            }
            elseif (!empty($this->configuration['block_revision_id'])) {
                $entity = $this->entityTypeManager->getStorage('block_content')->loadRevision($this->configuration['block_revision_id']);
                if(isset($entity) && isset($this->configuration['uuid']) && $entity->uuid() == $this->configuration['uuid']) {
                    $this->blockContent = $entity;
                }
            }
            if (!isset($this->blockContent) && isset($this->configuration['type']) && isset($this->configuration['uuid'])) {
                $entity = $this->entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $this->configuration['uuid']]);
                $this->blockContent = is_array($entity) ? array_pop($entity) : $entity;
            }
            if ($this->blockContent instanceof RefinableDependentAccessInterface && $dependee = $this->getAccessDependency()) {
                $this->blockContent->setAccessDependency($dependee);
            }

            if(isset($this->blockContent)) {
                //This is to deal with the EntityChangedConstraint when editing these blocks.
                //I hope this doesn't break anything.
                $now = new \DateTime();
                $this->blockContent->setChangedTime($now->getTimestamp());
                //hopefully this solves any issues with the changes not being detected
                $this->configuration['block_revision_id'] = $this->blockContent->getRevisionId();
            }
        }
        return parent::getEntity();
    }

    public function returnEntity() {
        return $this->getEntity();
    }

    public function saveBlockContent($new_revision = FALSE, $duplicate_block = FALSE)
    {
        /** @var \Drupal\block_content\BlockContentInterface $block */
        $block = NULL;
        if (!empty($this->configuration['block_serialized'])) {
            /** @var BlockContent $block */
            $block = unserialize($this->configuration['block_serialized']);
        }
        if ($duplicate_block) {
            if (empty($block) && !empty($this->configuration['block_revision_id'])) {
                $block = $this->entityTypeManager->getStorage('block_content')->loadRevision($this->configuration['block_revision_id']);
            }
            if ($block) {
                $this->configuration['block_serialized'] = serialize($block);
            }
            else {
                $block = $this->getEntity();
                if($block) {
                    $this->configuration['block_serialized'] = serialize($block);
                }
            }
            if(empty($block) && !empty($this->configuration['block_revision_id'])) {
                //block is broken/missing. Create a new one to avoid errors.
                $block = $this->entityTypeManager->getStorage('block_content')->create([
                    'type' => $this->getDerivativeId(),
                    'reusable' => FALSE,
                ]);
                $this->configuration['block_serialized'] = serialize($block);
            }
        }
        else {
            if($block instanceof BlockContent) {
                // came across this error where a new block is trying to save with the same uuid. Don't know why it's happening.
                // maybe it has to do with lb_copy_section or section_library?
                $uuid = $block->uuid();
                if (!empty($uuid) && $block->isNew()) {
                    $db = \Drupal::database();
                    $id = $db->query("SELECT id FROM {block_content} WHERE uuid = :uuid", [':uuid' => $uuid])->fetchField();
                    if (!empty($id)) {
                        $newUuid = \Drupal::service('uuid')->generate();
                        $block->set('uuid', $newUuid);
                        $this->configuration['block_serialized'] = serialize($block);
                    }
                }
            }
        }


        parent::saveBlockContent($new_revision, $duplicate_block);
    }
}
