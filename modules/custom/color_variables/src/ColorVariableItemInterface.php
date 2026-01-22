<?php

namespace Drupal\color_variables;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a theme color variables entity type.
 */
interface ColorVariableItemInterface extends ContentEntityInterface, EntityChangedInterface
{

    /**
     * Gets the theme color variables creation timestamp.
     *
     * @return int
     *   Creation timestamp of the theme color variables.
     */
    public function getCreatedTime();

    /**
     * Sets the theme color variables creation timestamp.
     *
     * @param int $timestamp
     *   The theme color variables creation timestamp.
     *
     * @return \Drupal\color_variables\ColorVariableItemInterface
     *   The called theme color variables entity.
     */
    public function setCreatedTime($timestamp);

    /**
     * Returns the theme color variables status.
     *
     * @return bool
     *   TRUE if the theme color variables is enabled, FALSE otherwise.
     */
    public function isEnabled();

    /**
     * Sets the theme color variables status.
     *
     * @param bool $status
     *   TRUE to enable this theme color variables, FALSE to disable.
     *
     * @return \Drupal\color_variables\ColorVariableItemInterface
     *   The called theme color variables entity.
     */
    public function setStatus($status);

    /**
     * Return the overridden color variables
     * @return array
     */
    public function getVariables();

    /**
     * Return the id for the theme associated with these variables
     * @return string
     */
    public function getTheme();

}
