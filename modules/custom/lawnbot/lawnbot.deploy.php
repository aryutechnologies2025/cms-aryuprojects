<?php

/**
 * Update handlers with new setting
 */
function lawnbot_deploy_10001() {
    $webforms = \Drupal::entityTypeManager()->getStorage('webform')->loadMultiple();
    /** @var \Drupal\webform\WebformInterface $webform */
    foreach($webforms as $webform) {
        /** @var \Drupal\webform\Plugin\WebformHandlerPluginCollection $handlers */
        $handlers = $webform->getHandlers('Lawnbot integration');
        /** @var \Drupal\lawnbot\Plugin\WebformHandler\LawnbotWebformHandler $handler */
        foreach($handlers as $handler) {
            $handler->setSetting('address_send', 'components');
            $webform->updateWebformHandler($handler);
            $webform->save();
        }
    }
}
