<?php

namespace Drupal\sprowt_uninstall;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ModuleInstaller;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;

class SprowtUninstallService
{

    public function removeCoalmarchUsersBatchSet() {
        $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple();
        $toDelete = [];
        /** @var User $user */
        foreach($users as $user) {
            $uid = $user->id();
            if($uid === '1'
                || $uid === 1
                || $user->isAnonymous()
            ) {
                continue;
            }
            $mail = $user->getEmail();
            if(strpos($mail, '@coalmarch.com') !== false
                || strpos($mail, '@workwave.com') !== false
            ) {
                $toDelete[] = $uid;
            }
        }
        foreach ($toDelete as $duid) {
            $this->removeUserBatchSet($duid);
        }
    }

    public function removeUserBatchSet($uid) {
        $edit = array(
            'user_cancel_notify' => false
        );
        user_cancel($edit, $uid, 'user_cancel_reassign');
    }

    public function modifyUserOne() {
        $user = User::load(1);
        $password = sha1(time() . rand());
        $user->setPassword($password);
        $toEmail = \Drupal::config('sprowt_settings.settings')->get('webform_email');
        if(empty($toEmail)) {
            $toEmail = \Drupal::config('system.site')->get('mail') ?? null;
        }
        if(!empty($toEmail)) {
            $user->setEmail($toEmail);
        }
        $user->setUsername('admin');
        $user->save();
    }

    public function removeIntegrations() {
        $containers = \Drupal::entityTypeManager()->getStorage('google_tag_container')->loadMultiple();
        foreach ($containers as $container) {
            $container->delete();
        }
        \Drupal::configFactory()->getEditable('mailgun.settings')->set('api_key', '')->save();
        \Drupal::configFactory()->getEditable('recaptcha.settings')->set('site_key', '')->save();
        \Drupal::configFactory()->getEditable('recaptcha.settings')->set('secret_key', '')->save();
        \Drupal::configFactory()->getEditable('sprowt_translation.settings')->delete();
        \Drupal::configFactory()->getEditable('sprowt_address_autocomplete.settings')->delete();
        \Drupal::configFactory()->getEditable('sprowt_ai.settings')->delete();
        \Drupal::configFactory()->getEditable('sprowt_settings.settings')->set('mail_reroute', 'pass')->save();


        $mailSystem = \Drupal::configFactory()->getEditable('mailsystem.settings');
        $mailSystemDefaults = $mailSystem->get('defaults');
        $newMailSystemDefaults = [];
        foreach($mailSystemDefaults as $key => $value) {
            $newMailSystemDefaults[$key] = 'php_mail';
        }
        $mailSystem->set('defaults', $newMailSystemDefaults);
        $mailSystem->save();


        $webforms = \Drupal::entityTypeManager()->getStorage('webform')->loadMultiple();
        foreach($webforms as $webform) {
            $this->removeCaptchaFromWebform($webform);
        }

        $siteMail = \Drupal::config('system.site')->get('mail') ?? null;

        $updateSettings = \Drupal::configFactory()->getEditable('update.settings');
        $notification = $updateSettings->get('notification');
        $notification['emails'] = [];
        if(!empty($siteMail)
            && strpos($siteMail, '@coalmarch.com') === false
            && strpos($siteMail, '@workwave.com') === false
        ){
            $notification['emails'][] = $siteMail;
        }
        $updateSettings->set('notification', $notification)->save();
    }

    /**
     * @param Webform $webform
     * @return void
     */
    protected function removeCaptchaFromWebform($webform) {
        $elements = $webform->getElementsInitializedAndFlattened();
        foreach($elements as $key => $element) {
            if($element['#type'] == 'captcha') {
                $webform->deleteElement($key);
                $webform->save();
            }
        }
    }

    public function changeProfile() {
        _sprowt_install_switch_profile('standard');
    }

    public function uninstallModules() {
        //uninstall sprowt ai fields and entities that depend on the module first
        _sprowt_ai_uninstall();

        // Purge any fields pending deletion before attempting to uninstall modules
        field_purge_batch(1000);

        $moduleList = [
            'cmauth',
            'content_import_export',
            'content_library',
            'ctm_api',
            'pestpac_api',
            'pestpac_webforms',
            'sa5_api',
            'sa5_webforms',
            'sales_center_api',
            'sales_center_webforms',
            'speed_lead',
            'sprowt2migrate',
            'sprowt_antispam',
            'sprowt_content',
            'sprowt_my_business',
            'sprowt_translation',
            'sprowt_subsite_installer',
            'sprowt_ai_prompt_library',
            'sprowt_ai',
            //contrib modules
            'uptimerobot',
            'userback',
            'mailgun',
            'google_tag',
            'config_split'
        ];
        /** @var ModuleHandler $moduleHandler */
        $moduleHandler = \Drupal::service('module_handler');
        /** @var ModuleInstaller $moduleInstaller */
        $moduleInstaller = \Drupal::service('module_installer');
        $uninstall = [];
        foreach($moduleList as $module) {
            if($moduleHandler->moduleExists($module)) {
                $uninstall[] = $module;
            }
        }
        $moduleInstaller->uninstall($uninstall);
    }

}
