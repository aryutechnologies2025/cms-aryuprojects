<?php

namespace Drupal\sprowt_install\Form;

use Drupal\config_split\Entity\ConfigSplitEntity;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Provides a Sprowt Install form.
 */
class ReinstallForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_install_reinstall';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $form['#title'] = 'Reinstall Sprowt?';
        $form['message'] = [
            '#type' => 'html_tag',
            '#tag' => 'p',
            'text' => [
                '#type' => 'markup',
                '#markup' => '<strong>WARNING!</strong>. This will delete the current database and redirect you to the intaller.'
            ]
        ];

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#button_type' => 'danger',
                '#value' => 'Reinstall'
            ]
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        //double check access
        $currentUser = \Drupal::currentUser();
        $access = static::access($currentUser);
        if(!$access->isAllowed()) {
            $form_state->setErrorByName('submit', "You need to be an dmin on the installer site in order for this form to work!");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $database = \Drupal::database();
        $options = $database->getConnectionOptions();
        $sql1 = "DROP database {$options['database']};";
        $sql2 = "CREATE database {$options['database']};";
        $mysql = "mysql --user='{$options['username']}' --password='{$options['password']}' --host='{$options['host']}'";
        if(!empty($options['port'])) {
            $mysql .= " --port={$options['port']}";
        }
        $cmdStr = "$mysql --execute=\"$sql1\" 2>&1 && $mysql --execute=\"$sql2\" 2>&1";
        $mysqlProcess = Process::fromShellCommandline($cmdStr);
        $mysqlProcess->setWorkingDirectory(DRUPAL_ROOT);

        $site = $_SERVER['SPROWTHQ_SITE_NAME'];
        $env = $_SERVER['SPROWTHQ_ENVIRONMENT'];
        if($site == 'sprowt3') {
            $siteRoot = DRUPAL_ROOT;
        }
        elseif ($env == 'local') {
            $siteRoot = DRUPAL_ROOT . '/sites/' . $site;
        }
        else {
            $siteRoot = DRUPAL_ROOT . '/sites/' . $site . '--' . $env;
        }
        $configs = [
            $siteRoot . '/config/overrides'
        ];
        $themeName = \Drupal::configFactory()->getEditable('system.theme')->get('default');
        $configs[] = $siteRoot . '/config/' . $themeName;

        $fs = new Filesystem();
        foreach($configs as $configDir) {
            if($fs->exists($configDir)) {
                foreach(glob($configDir . '/*.yml') as $ymlFile) {
                    $fs->remove($ymlFile);
                }
            }
        }

        $mysqlProcess->run();

        if(!$mysqlProcess->isSuccessful()) {
            \Drupal::messenger()->addError("There was an error deleting the database:");
            \Drupal::messenger()->addError($mysqlProcess->getErrorOutput());
        }
        else {
            $form_state->setRedirectUrl(Url::fromUserInput('/core/install.php'));
        }
    }

    public static function access(AccountInterface $account) {
        $roles = $account->getRoles();
        $allowed = $account->isAuthenticated()
            && in_array('administrator', $roles)
            &&  ($_SERVER['SPROWTHQ_ENVIRONMENT'] == 'local'
                || ($_SERVER['SPROWTHQ_SITE_NAME'] == 'installer'
                        || $_SERVER['SPROWTHQ_SITE_NAME'] == 'pei-site'
                        || $_SERVER['SPROWTHQ_SITE_NAME'] == 'pei'
                        || $_SERVER['SPROWTHQ_SITE_NAME'] == 'jess'
                        || $_SERVER['SPROWTHQ_SITE_NAME'] == 'jess-test'
                        || ($_SERVER['SPROWTHQ_SITE_NAME'] == 'sprowt3'
                            && ($_SERVER['SPROWTHQ_ENVIRONMENT'] == 'installer'
                                    || $_SERVER['SPROWTHQ_ENVIRONMENT'] == 'local'
                                )
                        )
                    )
            );
        return AccessResult::allowedIf(!empty($allowed));
    }

}
