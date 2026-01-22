<?php

declare(strict_types=1);

namespace Drupal\sprowt_admin_override;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\gin\GinDescriptionToggle;

/**
 * Defines a service provider for the Sprowt Admin Override module.
 *
 * @see https://www.drupal.org/node/2026959
 */
class SprowtAdminOverrideServiceProvider implements ServiceProviderInterface, ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    // @DCG Example of how to register a new service.
    // @code
    //   $container
    //     ->register('sprowt_admin_override.example_subscriber', ExampleSubscriber::class)
    //     ->addTag('event_subscriber')
    //     ->addArgument(new Reference('entity_type.manager'));
    // @endcode

      //override GinDescriptionToggle service with our own implementation
      //so we can customize where it shows up
      $container->register(GinDescriptionToggle::class, SprowtAdminOverrideDescriptionToggle::class);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // @DCG Example of how to swap out existing service.
    // @code
    //   if ($container->hasDefinition('logger.dblog')) {
    //     $container->getDefinition('logger.dblog')
    //       ->setClass(ExampleLogger::class);
    //   }
    // @endcode
  }

}
