<?php

namespace Drupal\zipcode_finder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a zipcode finder entity type.
 */
interface ZipcodeFinderInterface extends ContentEntityInterface, EntityChangedInterface
{

}
