<?php

namespace Drupal\script_inserter;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\system\Plugin\Condition\RequestPath;

/**
 * Provides an interface defining a script entity type.
 */
interface ScriptInterface extends ContentEntityInterface, EntityChangedInterface
{

    /**
     * Gets the script title.
     *
     * @return string
     *   Title of the script.
     */
    public function getLabel();

    /**
     * Sets the script title.
     *
     * @param string $title
     *   The script title.
     *
     * @return \Drupal\script_inserter\ScriptInterface
     *   The called script entity.
     */
    public function setLabel($title);

    /**
     * Gets the script creation timestamp.
     *
     * @return int
     *   Creation timestamp of the script.
     */
    public function getCreatedTime();

    /**
     * Sets the script creation timestamp.
     *
     * @param int $timestamp
     *   The script creation timestamp.
     *
     * @return \Drupal\script_inserter\ScriptInterface
     *   The called script entity.
     */
    public function setCreatedTime($timestamp);

    /**
     * Returns the script status.
     *
     * @return bool
     *   TRUE if the script is enabled, FALSE otherwise.
     */
    public function isEnabled();

    /**
     * Sets the script status.
     *
     * @param bool $status
     *   TRUE to enable this script, FALSE to disable.
     *
     * @return \Drupal\script_inserter\ScriptInterface
     *   The called script entity.
     */
    public function setStatus($status);


    /**
     * Gets the script location.
     *
     * @return string
     *   Location of the script.
     */
    public function getLocation();


    /**
     * Gets the script code.
     *
     * @return string
     *   Code of the script.
     */
    public function getCode();

    /**
     * Sets the script location
     *
     * @param string $location
     *   The script location.
     *
     * @return \Drupal\script_inserter\ScriptInterface
     *   The called script entity.
     */
    public function setLocation($location);


    /**
     * Sets the script code.
     *
     * @param string $code
     *   The script code.
     *
     * @return \Drupal\script_inserter\ScriptInterface
     *   The called script entity.
     */
    public function setCode($code);


    /**
     * returns the option list for the script location
     *
     * @return array
     */
    public static function getLocationOptions();

    /**
     * returns the label for the location
     *
     * @return string|null
     */
    public function getLocationLabel();

    /**
     * returns the render array for the script
     *
     * @return array
     */
    public function render();

    /**
     * @return RequestPath
     */
    public function getPageRestriction();

    /**
     * @return bool
     */
    public function restrictedByPage();

}
