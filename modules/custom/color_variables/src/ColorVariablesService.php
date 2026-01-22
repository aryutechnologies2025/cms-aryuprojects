<?php

namespace Drupal\color_variables;

use Drupal\color_variables\Entity\ColorVariableItem;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;

/**
 * ColorVariablesService service.
 */
class ColorVariablesService
{

    /**
     * The theme handler.
     *
     * @var \Drupal\Core\Extension\ThemeHandlerInterface
     */
    protected $themeHandler;

    /**
     * The module handler.
     *
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * Constructs a ColorVariablesService object.
     *
     * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
     *   The theme handler.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     */
    public function __construct(
        ThemeHandlerInterface $theme_handler,
        ModuleHandlerInterface $module_handler,
        EntityTypeManagerInterface $entity_type_manager
    ) {
        $this->themeHandler = $theme_handler;
        $this->moduleHandler = $module_handler;
        $this->entityTypeManager = $entity_type_manager;
    }


    public function themeInfoColors($themeName)  {
        try {
            /** @var Extension $themeObj */
            $themeObj = $this->themeHandler->getTheme($themeName);
        } catch (UnknownExtensionException $e) {
            $themeObj = null;
        }

        $themeColors = [];

        if($themeObj instanceof Extension) {
            $info = $themeObj->info ?? [];
            if(!empty($info['colors'])) {
                foreach ($info['colors'] as $colorName => $val) {
                    $themeColors[$colorName] = $val;
                }
            }
            if(!empty($info["base theme"])) {
                $baseColors = $this->themeInfoColors($info["base theme"]);
                $themeColors = array_merge($baseColors, $themeColors);
            }
        }
        return $themeColors;
    }

    public function getColorVariableEntity($themeName) {
        return ColorVariableItem::load($themeName);
    }

    public function getThemePreviewTemplateFile($themeName) {
        /** @var Extension $themeObj */
        $themeObj = $this->themeHandler->getTheme($themeName);
        $themePath = $themeObj->getPath();
        $file = DRUPAL_ROOT . '/' . $themePath . '/templates/color-variables-preview.html.twig';
        if(is_file($file)) {
            return $file;
        }
        $info = $themeObj->info;
        if(!empty($info["base theme"])) {
            return $this->getThemePreviewTemplateFile($info["base theme"]);
        }
        return __DIR__ . '/../templates/color-variables-preview.html.twig';
    }

    public function getBaseColorVariablesForTheme($themeName) {
        try {
            /** @var Extension $themeObj */
            $themeObj = $this->themeHandler->getTheme($themeName);
        } catch (UnknownExtensionException $e) {
            $themeObj = null;
        }
        if($themeObj instanceof Extension) {
            $info = $themeObj->info ?? [];
            if(!empty($info["base theme"])) {
                return $this->getAllColorVariablesForTheme($info["base theme"]);
            }
        }
        return [];
    }

    public function getAllColorVariablesForTheme($themeName) {
        $baseVariables = $this->getBaseColorVariablesForTheme($themeName);
        $return = array_merge($baseVariables, $this->themeInfoColors($themeName));
        $entity = $this->getColorVariableEntity($themeName);
        $context = [
            'theme' => $themeName
        ];
        if($entity instanceof ColorVariableItem) {
            $context['entity'] = $entity;
            $overridden = $entity->getVariables();
            foreach($overridden as $varName => $val) {
                $return[$varName] = $val;
            }
        }

        $this->moduleHandler->alter('color_variables', $return, $context);
        return $return;
    }

    public function getAllColorVariablesFromEntity(ColorVariableItem $entity) {
        $theme = $entity->getTheme();
        $return = $this->getAllColorVariablesForTheme($theme);
        $context = [
            'theme' => $theme,
            'entity' => $entity
        ];
        $overridden = $entity->getVariables();
        foreach($overridden as $varName => $val) {
            $return[$varName] = $val;
        }
        $this->moduleHandler->alter('color_variables', $return, $context);
        return $return;
    }

    public function getCssVariableArrayForTheme($themeName) {
        $colors = $this->getAllColorVariablesForTheme($themeName);
        $return = [];
        foreach($colors as $varName => $val) {
            if(!empty($val)) {
                $return['color-' . $varName] = $val;
            }
        }
        $context = [
            'theme' => $themeName
        ];
        $this->moduleHandler->alter('color_variables_css', $return, $context);
        return $return;
    }

    public function getCssVariableArrayForEntity(ColorVariableItem $entity) {
        $themeName = $entity->getTheme();
        $colors = $this->getAllColorVariablesFromEntity($entity);
        $return = [];
        foreach($colors as $varName => $val) {
            $return['color-' . $varName] = $val;
        }
        $context = [
            'theme' => $themeName,
            'entity' => $entity
        ];
        $this->moduleHandler->alter('color_variables_css', $return, $context);
        return $return;
    }

    public function getCssMarkupForTheme($themeName, $selector = ':root') {
        $colors = $this->getCssVariableArrayForTheme($themeName);
        return $this->getCssMarkup($colors, $selector);
    }

    public function getCssMarkupForEntity(ColorVariableItem $entity, $selector = ':root') {
        $colors = $this->getCssVariableArrayForEntity($entity);
        return $this->getCssMarkup($colors, $selector);
    }


    public function getCssMarkup($variables, $selector = ':root') {
        if(empty($variables)) {
            return null;
        }
        $str = [];
        $str[] = '';
        $indent = '  ';
        $str[] = $selector . ' {';
        foreach($variables as $var => $val) {
            $safeVal = $this->safeCssValue($val);
            $str[] = $indent . "--$var: $safeVal" . ';';
        }
        $str[] = '}';
        $str[] = '';
        return implode("\n", $str);
    }


    public function safeCssValue($val) {
        $ret = trim(str_replace([
            '}',
            '{',
            ';'
        ], '', $val));

        return $ret;
    }
}
