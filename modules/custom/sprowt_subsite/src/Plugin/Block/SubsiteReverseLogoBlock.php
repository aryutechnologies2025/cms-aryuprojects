<?php

namespace Drupal\sprowt_subsite\Plugin\Block;

/**
 * Provides a logo block
 *
 * @Block(
 *   id = "sprowt_subsite_logo_reverse_block",
 *   admin_label = @Translation("Subsite reverse logo block"),
 *   forms = {
 *     "settings_tray" = "Drupal\system\Form\SystemBrandingOffCanvasForm",
 *   },
 * )
 */
class SubsiteReverseLogoBlock extends SubsiteLogoBlock
{

    protected $logoField = 'field_reverse_logo';

    protected $imgLinkClasses = [
        'subsite-reverse-logo',
        'site-logo'
    ];

    protected function systemLogo() {
        return sprowt_theme_get_setting('logo_reverse.url');
    }


    public function getCacheMaxAge()
    {
        return 0;
    }
}
