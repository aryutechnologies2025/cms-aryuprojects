<?php

namespace Drupal\chat_codes;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\system\Plugin\Condition\RequestPath;

/**
 * Provides an interface defining a chat code entity type.
 */
interface ChatCodeInterface extends ContentEntityInterface, EntityChangedInterface
{

    /**
     * Gets the chat code title.
     *
     * @return string
     *   Title of the chat code.
     */
    public function getLabel();

    /**
     * Sets the chat code title.
     *
     * @param string $title
     *   The chat code title.
     *
     * @return \Drupal\chat_codes\ChatCodeInterface
     *   The called chat code entity.
     */
    public function setLabel($title);

    /**
     * Gets the chat code creation timestamp.
     *
     * @return int
     *   Creation timestamp of the chat code.
     */
    public function getCreatedTime();

    /**
     * Sets the chat code creation timestamp.
     *
     * @param int $timestamp
     *   The chat code creation timestamp.
     *
     * @return \Drupal\chat_codes\ChatCodeInterface
     *   The called chat code entity.
     */
    public function setCreatedTime($timestamp);

    /**
     * Returns the chat code status.
     *
     * @return bool
     *   TRUE if the chat code is enabled, FALSE otherwise.
     */
    public function isEnabled();

    /**
     * Sets the chat code status.
     *
     * @param bool $status
     *   TRUE to enable this chat code, FALSE to disable.
     *
     * @return \Drupal\chat_codes\ChatCodeInterface
     *   The called chat code entity.
     */
    public function setStatus($status);

    /**
     * @return RequestPath
     */
    public function getPageRestriction();

    /**
     * @return bool
     */
    public function restrictedByPage();

    /**
     * Returns an array of visibility condition configurations.
     *
     * @return array
     *   An array of visibility condition configuration keyed by the condition ID.
     */
    public function getVisibility();

    /**
     * Gets conditions for this block.
     *
     * @return \Drupal\Core\Condition\ConditionInterface[]|\Drupal\Core\Condition\ConditionPluginCollection
     *   An array or collection of configured condition plugins.
     */
    public function getVisibilityConditions();

    /**
     * Gets a visibility condition plugin instance.
     *
     * @param string $instance_id
     *   The condition plugin instance ID.
     *
     * @return \Drupal\Core\Condition\ConditionInterface
     *   A condition plugin.
     */
    public function getVisibilityCondition($instance_id);

    /**
     * Sets the visibility condition configuration.
     *
     * @param string $instance_id
     *   The condition instance ID.
     * @param array $configuration
     *   The condition configuration.
     *
     * @return $this
     */
    public function setVisibilityConfig($instance_id, array $configuration);
}
