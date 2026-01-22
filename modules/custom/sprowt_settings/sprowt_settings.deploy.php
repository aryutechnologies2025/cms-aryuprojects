<?php

use Drupal\node\Entity\Node;
use Drupal\webform\WebformInterface;

/**
 * Transfer over to use new webform tokens
 */
function sprowt_settings_deploy_9001()
{
    /** @var \Drupal\sprowt_settings\SprowtSettings $sprowtSettings */
//    $sprowtSettings = \Drupal::service('sprowt_settings.manager');
//    $webformEmail = $sprowtSettings->getSetting('webform_email');
//    $currentTo = $sprowtSettings->getSetting('webform_to_email');
//    $currentFromEmail = $sprowtSettings->getSetting('webform_from_email');
//    if(!empty($webformEmail) && empty($currentFromEmail) && empty($currentTo)) {
//        $sprowtSettings->setSetting('webform_from_email', $webformEmail);
//        $sprowtSettings->setSetting('webform_to_email', $webformEmail);
//    }
//
//    $webforms = \Drupal\webform\Entity\Webform::loadMultiple();
//
//    /** @var \Drupal\webform\Entity\Webform $webform */
//    foreach($webforms as $webform) {
//        $handlers = $webform->getHandlers();
//        foreach($handlers as $handler) {
//            $change = false;
//            if($handler instanceof \Drupal\webform\Plugin\WebformHandler\EmailWebformHandler) {
//                $currentToEmail = $handler->getSetting('to_mail');
//                if($currentToEmail == '[sprowt:webform_email]') {
//                    $handler->setSetting('to_mail', '[sprowt:webform_to_email]');
//                    $change = true;
//                }
//                $currentFromEmail = $handler->getSetting('from_mail');
//                if($currentFromEmail == '[sprowt:webform_email]') {
//                    $handler->setSetting('from_mail', '[sprowt:webform_from_email]');
//                    $change = true;
//                }
//            }
//            if($change) {
//                $webform->updateWebformHandler($handler);
//                $webform->save();
//            }
//        }
//    }
}

/**
 * Fix missing webform emails
 */
function sprowt_settings_deploy_9002() {
    $sprowtSettings = \Drupal::service('sprowt_settings.manager');
    $currentTo = $sprowtSettings->getSetting('webform_to_email');
    $currentFrom = $sprowtSettings->getSetting('webform_from_email');
    $installInfo = \Drupal::state()->get('install_info', []);
    $companyInfo = $installInfo['companyInfo'] ?? [];
    $webformFrom = $companyInfo['webform_from_email'] ?? null;
    $webformTo = $companyInfo['webform_to_email'] ?? null;
    if((empty($currentTo) && empty($currentFrom))
        && (!empty($webformTo) && !empty($webformFrom))
    ) {
        $sprowtSettings->setSetting('webform_from_email', $webformFrom);
        $sprowtSettings->setSetting('webform_to_email', $webformTo);
    }
}

/**
 * Add texting opt in field to webforms
 */
function sprowt_settings_deploy_100001()
{
    $webformIds = [
        'contact_us',
        'contact_us_subsite_main_site',
        'free_estimate',
        'free_estimate_subsite_main_site',
        'free_quote',
        'get_an_online_pricing',
        'redeem_coupon',
        'solution_page'
    ];

    $webformStorage = \Drupal::entityTypeManager()->getStorage('webform');

    foreach ($webformIds as $webformId) {
        /** @var \Drupal\webform\Entity\Webform $webform */
        $webform = $webformStorage->load($webformId);
        if(!$webform instanceof WebformInterface) {
            // If the webform does not exist, skip to the next one.
            continue;
        }
        $elements = $webform->getElementsDecoded();
        $flattened = $webform->getElementsDecodedAndFlattened();
        $newElementKey = 'texting_opt_in';
        $newElement = [
            '#type' => 'sprowt_settings_checkbox',
            '#title'=> 'Texting opt-in',
            '#sprowt_setting' => 'hide_texting_opt_in',
            '#negate' => true,
            '#title_override' => "You agree to receive informational messages (appointment reminders, account notifications, etc.)"
                ." from [sprowt:company_name]. Message frequency varies. Message and data rates may apply. "
                ."For help, reply HELP or contact us at [sprowt:company_phone]. "
                ."We will not share your phone number with any third parties for marketing purposes, "
                ."and you can opt out at any time by replying STOP.",
            '#description' => '<p><a href="/privacy-policy"><span>Message Use - Privacy Policy</span></a>.</p>'
        ];

        $keyExists = false;
        $elementExists = false;
        foreach ($flattened as $flatKey => $flattenedElement) {
            if($flatKey == $newElementKey) {
                $keyExists = true;
                if($flattenedElement['#type'] == 'sprowt_settings_checkbox') {
                    $elementExists = true;
                    break;
                }
            }
        }
        if($elementExists) {
            continue;
        }
        if($keyExists) {
            $newElementKey .= '_2';
        }

        $insertAfter = '';
        switch($webformId) {
            case 'contact_us':
                $insertAfter = 'how_can_we_help_you_';
                break;
            case 'contact_us_subsite_main_site':
                $insertAfter = 'how_can_we_help_you_';
                break;
            case 'free_estimate':
                $insertAfter = 'how_can_we_help_you_';
                break;
            case 'free_estimate_subsite_main_site':
                $insertAfter = 'how_can_we_help_you_';
                break;
            case 'free_quote':
                $insertAfter = 'how_can_we_help_you_';
                break;
            case 'get_an_online_pricing':
                $insertAfter = 'how_can_we_help_you_';
                break;
            case 'redeem_coupon':
                $insertAfter = 'do_you_have_any_additional_comments_questions_';
                break;
            case 'solution_page':
                $insertAfter = 'how_can_we_help_you_';
                break;
        }

        $insertAfterExists = false;
        foreach ($flattened as $flatKey => $flattenedElement) {
            if($insertAfter == $flatKey) {
                $insertAfterExists = true;
                break;
            }
        }
        if(empty($insertAfterExists)) {
            $insertAfter = null;
        }

        $newElements = [];
        $setElements = false;
        foreach ($elements as $elementKey => $element) {
            $newElements[$elementKey] = $element;
            if(!empty($insertAfter) && $elementKey == $insertAfter) {
                $newElements[$newElementKey] = $newElement;
                $setElements = true;
            }
        }
        if(!isset($insertAfter) && empty($setElements)) {
            $newElements[$newElementKey] = $newElement;
            $setElements = true;
        }

        if($setElements) {
            $webform->setElements($newElements);
            $webform->save();
        }
    }

}

/**
 * Disable texting opt in for certain clients
 */
function sprowt_settings_deploy_100002()
{
    /** @var \Drupal\sprowt_settings\SprowtSettings $sprowtSettings */
    $sprowtSettings = \Drupal::service('sprowt_settings.manager');
    /** @var \Drupal\sprowt_subsite\SettingsManager $subsiteSettings */
    $subsiteSettings = \Drupal::service('sprowt_subsite.settings_manager');
    $sitenames = [
        'envirogreen',
        'ecoservepest-s3',
        'uglyweeds',
        'allnonepest',
        'alfordpestcontrol',
        'allgreenok',
        'benjaminlawns',
        'fxpestcontrol',
        'greenerstill',
        'ilandscaped',
        'roots',
        'scottcountylawns',
        'turfrxil',
        'greenleafpc-s3',
        'agrilawn',
        'workmanpest',
        'd-bug-s3',
        'pestbear',
        'falconcommercialpools',
        'economypool',
        'progressivepoolrepairfl',
        'premiercaninedetection-s3',
        'whitmorepestcontrol',
        'acpest-s3',
        'enviroconpest-s3',
        'triangle-lawn-care-s3',
        'unlimitedlawncare-s3',
        'ppsriverregion'
    ];
    $subsiteSitenames = [
        'freedomlawnsusa' => [391]
    ];

    foreach($sitenames as $sitename) {
        if($sitename == $_SERVER['SPROWTHQ_SITE_NAME']) {
            $sprowtSettings->setSetting('hide_texting_opt_in', true);
        }
    }

    foreach ($subsiteSitenames as $sitename => $subsiteNids) {
        if($sitename == $_SERVER['SPROWTHQ_SITE_NAME']) {
            foreach($subsiteNids as $subsiteNid) {
                $subsite = Node::load($subsiteNid);
                if ($subsite instanceof Node) {
                    $subsiteSettings->setSetting($subsite, 'hide_texting_opt_in', true);
                }
            }
        }
    }

}
