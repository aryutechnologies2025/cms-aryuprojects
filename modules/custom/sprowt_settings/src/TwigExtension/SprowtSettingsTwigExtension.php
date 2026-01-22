<?php

namespace Drupal\sprowt_settings\TwigExtension;

use Drupal\sprowt_settings\SprowtSettings;
use Twig\Extension\AbstractExtension;

class SprowtSettingsTwigExtension extends AbstractExtension
{

    /**
     * @var SprowtSettings
     */
    protected $sprowtSettings;

    public function __construct(SprowtSettings $sprowtSettings) {
        $this->sprowtSettings = $sprowtSettings;
    }

    public function getFilters()
    {
        return [
            new \Twig\TwigFilter('sprowt_tokens', [$this, 'tokenReplace']),
            new \Twig\TwigFilter('sprowt_phone', [$this, 'phoneFormat']),
        ];
    }

    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('sprowt_get_setting', [$this, 'getSprowtSetting']),
            new \Twig\TwigFunction('sprowt_theme_get_setting', [$this, 'getSprowtThemeSetting']),
            new \Twig\TwigFunction('sprowt_get_field_value', 'sprowt_get_field_value'),
        ];
    }

    public function tokenReplace($text) {
        return $this->sprowtSettings->replaceSprowtTokens($text);
    }

    public function phoneFormat($text, $format = null) {
        return $this->sprowtSettings->formatPhone($text, $format);
    }

    public function getSprowtSetting($key, $default = null) {
        return $this->sprowtSettings->getSetting($key, $default);
    }

    public function getSprowtThemeSetting($key, $theme = null) {
        return $this->sprowtSettings->getThemeSetting($key, $theme);
    }
}
