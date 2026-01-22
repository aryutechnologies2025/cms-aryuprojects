<?php

namespace Drupal\zipcode_finder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a zipcode finder log entity type.
 */
interface ZipcodeFinderLogInterface extends ContentEntityInterface, EntityChangedInterface
{

}
