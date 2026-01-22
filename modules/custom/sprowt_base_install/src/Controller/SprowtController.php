<?php

namespace Drupal\sprowt_base_install\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Render\BareHtmlPageRenderer;
use Drupal\Core\Render\Markup;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Url;
use Drupal\sprowt_base_install\SprowtBaseInstallUtil;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Process\Process;

/**
 * Returns responses for Sprowt routes.
 */
class SprowtController extends ControllerBase
{


    /**
     * Builds the response.
     */
    public function startInstallProcess()
    {
        $installed = \Drupal::state()->get('sprowt_site_installed', false);
        if(!empty($installed)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'site already installed'
            ]);
        }

        $state = \Drupal::state();
        $running = static::isProcessRunning();
        if(!empty($running)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'installer is running'
            ]);
        }

        SprowtBaseInstallUtil::startInstaller();
        return new JsonResponse([
            'success' => true
        ]);
    }

    public function startInstall() {
        $installed = \Drupal::state()->get('sprowt_site_installed', false);
        if(!empty($installed)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'site already installed'
            ]);
        }

        $query = [];
        if(!empty($_GET['XDEBUG_SESSION_START'])) {
            $query['XDEBUG_SESSION_START'] = 'PHPSTORM';
        }

        $fs = new Filesystem();
        $onServer = $fs->exists('/var/www/sprowt3-core');
        if($onServer) {
            $env = $_SERVER['SPROWTHQ_ENVIRONMENT'];
            $site = $_SERVER['SPROWTHQ_SITE_NAME'];
            $hash = sha1("$site.$env" . "What's cookin good lookin'?");
            $url = 'https://webhooks.sprowt.us/siteInstall.php' . "?site=$site&env=$env&hash=$hash";
        }
        else {
            $url = Url::fromRoute('sprowt.start_install_process', [], [
                'absolute' => true,
                'query' => $query
            ])->toString();
        }

        $output = [];

        $cmd = "curl -d 'stub' $url";
        $process = new Process(['curl', '-d', 'stub', $url]);
        if(!empty($_GET['XDEBUG_SESSION_START'])) {
            $env = $process->getEnv() ?? [];
            $env['XDEBUG_SESSION'] = 'PHPSTORM';
            $process->setEnv($env);
        }
        $process->setTimeout(0);

        $process->run();

        return new JsonResponse([
            'success' => $process->isSuccessful()
        ]);
    }

    public function batchInfo()
    {

        $info = SprowtBaseInstallUtil::batchInfo();
        $response = new JsonResponse($info);
        if($info['error']) {
            $response->setStatusCode(404);
        }

        return $response;
    }

    public function installerRunBatchSet() {
        $includes = DRUPAL_ROOT . '/core/includes';
        require_once $includes . '/batch.inc';
        $yaml = file_get_contents(DRUPAL_ROOT . '/profiles/sprowt/sprowt.info.yml');
        $info = Yaml::decode($yaml);
        $redirect = $info['distribution']['install']['redirect_url'] ?? '/admin/content?flush_cache_loop=1';
        if(strpos($redirect, '/') === 0) {
            $redirect = Url::fromUserInput($redirect);
        }
        else {
            $redirect = Url::fromUri($redirect);
        }
        $finalized = \Drupal::state()->get('sprowt.site_install_finalized', false);
        if($finalized) {
            return new RedirectResponse($redirect->toString());
        }

        $url = Url::fromRoute('sprowt.installer_batch');

        $batchBuilder = new \Drupal\Core\Batch\BatchBuilder();
        $batchBuilder->setTitle('Installing sprowt');
        $batchBuilder->addOperation('_sprowt_install_batch_task');
        $batchBuilder->setFinishCallback([static::class,'finishBatch']);
        $batchBuilder->setProgressMessage('Processing...');
        $batchBuilder->setLibraries([
            'sprowt/install_batch'
        ]);

        $batch = $batchBuilder->toArray();
        batch_set($batch);
        return batch_process($redirect, $url);
    }

    public static function finishBatch($success, $results, $operations, $elapsed) {
        $finalized = \Drupal::state()->get('sprowt.site_install_finalized', false);
        if(!$finalized) {
            require_once DRUPAL_ROOT . '/core/includes/install.core.inc';
            $install_state = [
                'parameters' => [
                    'profile' => 'sprowt'
                ],
                'interactive' => true
            ];
            _sprowt_post_install($install_state);
            install_finished($install_state);
            _sprowt_install_finalize($install_state);
        }
    }

    /**
     * stolen from _drupal_maintenance_theme
     * @return void
     */
    protected function installerTheme() {
        $includes = DRUPAL_ROOT . '/core/includes';
        require_once $includes . '/theme.inc';
        require_once $includes . '/common.inc';
        require_once $includes . '/module.inc';
        $custom_theme = 'sprowt_installer';

        $themes = \Drupal::service('theme_handler')
            ->listInfo();

        // If no themes are installed yet, or if the requested custom theme is not
        // installed, retrieve all available themes.

        /** @var \Drupal\Core\Theme\ThemeInitialization $theme_init */
        $theme_init = \Drupal::service('theme.initialization');
        $theme_handler = \Drupal::service('theme_handler');
        if (empty($themes) || !isset($themes[$custom_theme])) {
            $themes = \Drupal::service('extension.list.theme')
                ->getList();
            $theme_handler
                ->addTheme($themes[$custom_theme]);
        }
        // \Drupal\Core\Extension\ThemeHandlerInterface::listInfo() triggers a
        // \Drupal\Core\Extension\ModuleHandler::alter() in maintenance mode, but we
        // can't let themes alter the .info.yml data until we know a theme's base
        // themes. So don't set active theme until after
        // \Drupal\Core\Extension\ThemeHandlerInterface::listInfo() builds its cache.
        $theme = $custom_theme;

        // Find all our ancestor themes and put them in an array.
        // @todo This is just a workaround. Find a better way how to handle themes
        //   on maintenance pages, see https://www.drupal.org/node/2322619.
        // This code is basically a duplicate of
        // \Drupal\Core\Theme\ThemeInitialization::getActiveThemeByName.
        $base_themes = [];
        $ancestor = $theme;
        while ($ancestor && isset($themes[$ancestor]->base_theme)) {
            $base_themes[] = $themes[$themes[$ancestor]->base_theme];
            $ancestor = $themes[$ancestor]->base_theme;
            if ($ancestor) {

                // Ensure that the base theme is added and installed.
                $theme_handler
                    ->addTheme($themes[$ancestor]);
            }
        }
        \Drupal::theme()
            ->setActiveTheme($theme_init
                ->getActiveTheme($themes[$custom_theme], $base_themes));

        // Prime the theme registry.
        \Drupal::service('theme.registry');
    }

    public function batchPage(Request $request) {
        $includes = DRUPAL_ROOT . '/core/includes';
        require_once $includes . '/batch.inc';
        require_once $includes . '/install.inc';
        $this->installerTheme();
        $output = _batch_page($request);
        if ($output === FALSE) {
            throw new AccessDeniedHttpException();
        }
        elseif ($output instanceof Response) {
            return $output;
        }
        elseif (isset($output)) {
            global $install_state;
            $profile = \Drupal::installProfile();
            $info = \Drupal::service('extension.list.profile')->getExtensionInfo($profile);
            $info['version'] = \Drupal::VERSION;
            $install_state['profile_info'] = $info;
            $title = $output['#title'] ?? 'Installing Sprowt';
            /** @var TwigEnvironment $twig */
            $twig = \Drupal::service('twig');
            $twigHtml = file_get_contents(__DIR__ . '/../../templates/installer_batch_sidebar.html.twig');
            $sidebar = $twig->renderInline($twigHtml);
            $page = [
                'sidebar_first' => [
                    '#type' => 'markup',
                    '#markup' => Markup::create($sidebar)
                ]
            ];
            // Also inject title as a page header (if available).
            if ($title) {
                $page['header'] = [
                    '#type' => 'page_title',
                    '#title' => $title,
                ];
            }
            /** @var BareHtmlPageRenderer $renderer */
            $renderer = \Drupal::service('bare_html_page_renderer');
            return $renderer->renderBarePage($output, $title, 'install_page', $page);
        }
    }

    public function reset() {
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

        return new RedirectResponse('/core/install.php');
    }

    public static function installAccess(AccountInterface $account) {
        $installed = \Drupal::state()->get('sprowt_site_installed', false);
        return AccessResult::allowedIf(!$installed);
    }

    public static function installFinalized(AccountInterface $account) {
        $finalized = \Drupal::state()->get('sprowt.site_install_finalized', false);
        return AccessResult::allowedIf(!$finalized);
    }

    public static function installError(AccountInterface $account) {
        $error = \Drupal::state()->get('sprowt.site_install_error', false);
        return AccessResult::allowedIf(!empty($error));
    }

    public function installStatus()
    {
        $status = SprowtBaseInstallUtil::installStatus();
        return new JsonResponse($status);
    }

}
