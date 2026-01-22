<?php

namespace Drupal\lawnbot;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a servicebot entity type.
 */
interface ServicebotInterface extends ContentEntityInterface, EntityChangedInterface
{

    /**
     * Gets the servicebot title.
     *
     * @return string
     *   Title of the servicebot.
     */
    public function getTitle();

    /**
     * Sets the servicebot title.
     *
     * @param string $title
     *   The servicebot title.
     *
     * @return \Drupal\lawnbot\ServicebotInterface
     *   The called servicebot entity.
     */
    public function setTitle($title);

    /**
     * Gets the servicebot creation timestamp.
     *
     * @return int
     *   Creation timestamp of the servicebot.
     */
    public function getCreatedTime();

    /**
     * Sets the servicebot creation timestamp.
     *
     * @param int $timestamp
     *   The servicebot creation timestamp.
     *
     * @return \Drupal\lawnbot\ServicebotInterface
     *   The called servicebot entity.
     */
    public function setCreatedTime($timestamp);

    /**
     * Returns the servicebot status.
     *
     * @return bool
     *   TRUE if the servicebot is enabled, FALSE otherwise.
     */
    public function isEnabled();

    /**
     * Sets the servicebot status.
     *
     * @param bool $status
     *   TRUE to enable this servicebot, FALSE to disable.
     *
     * @return \Drupal\lawnbot\ServicebotInterface
     *   The called servicebot entity.
     */
    public function setStatus($status);

    /**
     * Get the ServiceBot customer id
     * @return string | null
     */
    public function getCustomerId();

    /**
     * Get the ServiceBot bot id
     * @return string | null
     */
    public function getBotId();
}
