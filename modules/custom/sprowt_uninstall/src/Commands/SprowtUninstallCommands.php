<?php

namespace Drupal\sprowt_uninstall\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\sprowt_uninstall\SprowtUninstallService;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class SprowtUninstallCommands extends DrushCommands
{

    /**
     * @var SprowtUninstallService
     */
    protected $sprowtUninstallService;

    public function __construct($sprowtUninstallService)
    {
        $this->sprowtUninstallService = $sprowtUninstallService;
        parent::__construct();
    }

    /**
     * Uninstall sprowt theme and some custom modules
     *
     * @usage drush suu
     *   Uninstall sprowt profile and some custom modules
     *
     * @command sprowt_uninstall:uninstall
     * @aliases suu
     */
    public function uninstall()
    {
        $this->io()->title('Uninstalling sprowt...');

        $this->io()->section('Uninstall sprowt profile');
        $this->sprowtUninstallService->changeProfile();

        $this->io()->section('Remove coalmarch/workwave users');
        $this->sprowtUninstallService->removeCoalmarchUsersBatchSet();
        drush_backend_batch_process();

        $this->io()->section('Modify user 1');
        $this->sprowtUninstallService->modifyUserOne();

        $this->io()->section('Remove integrations');
        $this->sprowtUninstallService->removeIntegrations();

        $this->io()->section('Uninstall custom modules');
        $this->sprowtUninstallService->uninstallModules();

        $this->io()->success('Sprowt uninstalled');
    }
}
