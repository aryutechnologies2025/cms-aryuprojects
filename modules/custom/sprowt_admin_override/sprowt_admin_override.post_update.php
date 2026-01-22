<?php


function sprowt_admin_override_post_update_add_metatag_edit_action(&$sandbox)
{
    $yml = file_get_contents(__DIR__ .'/config/install/system.action.sprowt_admin_override_edit_metatags.yml');
    $data = Symfony\Component\Yaml\Yaml::parse($yml);
    $config = \Drupal::service('config.factory')->getEditable('system.action.sprowt_admin_override_edit_metatags');
    $config->setData($data);
    $config->save();
}
