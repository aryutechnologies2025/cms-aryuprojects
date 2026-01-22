<?php

namespace Drupal\sprowt_subsite\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\sprowt_subsite\SettingsManager;
use Drupal\system\Plugin\Block\SystemMenuBlock;

/**
 * Provides a subsite menu block block.
 *
 * @Block(
 *   id = "subsite_menu_block",
 *   admin_label = @Translation("Subsite menu block"),
 *   category = @Translation("Menus"),
 *   forms = {
 *     "settings_tray" = "\Drupal\system\Form\SystemMenuOffCanvasForm",
 *   },
 * )
 */
class SubsiteMenuBlock extends SystemMenuBlock
{
    public static $menuFields = [
        'field_main_menu' => [
            'title' => 'Main menu',
            'main' => 'main'
        ],
        'field_utility_menu' => [
            'title' => 'Utility menu',
            'main' => 'utility-menu'
        ],
        'field_footer_menu' => [
            'title' => 'Footer menu',
            'main' => 'footer'
        ],
        'field_mobile_utility_menu' => [
            'title' => 'Mobile utility menu',
            'main' => 'mobile-utility-menu'
        ],
        'field_mobile_footer_utility_menu' => [
            'title' => 'Mobile footer utility menu',
            'main' => 'mobile-footer-utility-menu'
        ],
    ];

    protected $menuName;

    public function defaultConfiguration()
    {
        $default = parent::defaultConfiguration();
        $default['menu_field'] = null;
        $default['suggestion'] = null;
        return $default;
    }


    /**
     * Form API callback: Processes the elements in field sets.
     *
     * Adjusts the #parents of field sets to save its children at the top level.
     */
    public static function processMenuBlockFieldSets(&$element, FormStateInterface $form_state, &$complete_form) {
        array_pop($element['#parents']);
        return $element;
    }

    public function blockForm($form, FormStateInterface $form_state)
    {
        $form = parent::blockForm($form, $form_state);

        $form['subsite_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Subsite settings'),
            '#open' => true
        ];

        $menuFields = [];
        foreach(static::$menuFields as $fieldName => $fieldInfo) {
            $menuFields[$fieldName] = $fieldInfo['title'];
        }

        $form['subsite_settings']['menu_field'] = [
            '#type' => 'select',
            '#title' => 'Subsite menu type',
            '#options' => $menuFields,
            '#required' => true,
            '#default_value' => $this->configuration['menu_field']
        ];

        $form['style'] = [
            '#type' => 'details',
            '#title' => $this->t('HTML and style options'),
            '#open' => true,
            '#process' => [[get_class(), 'processMenuBlockFieldSets']],
        ];


        $form['style']['suggestion'] = [
            '#type' => 'machine_name',
            '#title' => $this->t('Theme hook suggestion'),
            '#default_value' => $this->configuration['suggestion'],
            '#field_prefix' => '<code>menu__</code>',
            '#description' => $this->t('A theme hook suggestion can be used to override the default HTML and CSS classes for menus found in <code>menu.html.twig</code>.'),
            '#machine_name' => [
                'error' => $this->t('The theme hook suggestion must contain only lowercase letters, numbers, and underscores.'),
                'exists' => [$this, 'suggestionExists'],
            ],
        ];

        return $form;
    }

    /**
     * Checks for an existing theme hook suggestion.
     *
     * @return bool
     *   Returns FALSE because there is no need of validation by unique value.
     */
    public function suggestionExists() {
        return FALSE;
    }

    public function blockSubmit($form, FormStateInterface $form_state)
    {
        parent::blockSubmit($form, $form_state);
        $values = $form_state->getValues();
        $this->configuration['menu_field'] = $values["subsite_settings"]["menu_field"] ?? null;
        $this->configuration['suggestion'] = $form_state->getValue('suggestion') ?? null;
    }

    public function getSubsite() {
        return SettingsManager::getCurrentNodeSubsite();
    }

    public function getMenuName() {
        $subsite = $this->getSubsite();
        if(!empty($subsite) && !empty($this->configuration['menu_field'])) {
            /** @var EntityReferenceFieldItemList $itemList */
            $itemList = $subsite->get($this->configuration['menu_field']);
            if(!$itemList->isEmpty()) {
                /** @var EntityReferenceItem $item */
                $item = $itemList->first();
                return $item->target_id;
            }
        }
        //return main site version or just the main menu just in case
        $fieldInfo = static::$menuFields[$this->configuration['menu_field']] ?? [];
        return $fieldInfo['main'] ?? 'main';
    }

    public function getMenuTitle() {
        $fieldInfo = static::$menuFields[$this->configuration['menu_field']] ?? null;
        return !isset($fieldInfo) ? 'No menu set' : $fieldInfo['title'];
    }

    public function getDerivativeId()
    {
        //return provided menu name if set
        if(isset($this->menuName)) {
            return $this->menuName;
        }
        return parent::getDerivativeId();
    }

    public function getSourceMenu() {
        $fieldInfo = static::$menuFields[$this->configuration['menu_field']] ?? null;
        return !isset($fieldInfo) ? 'no-menu' : $fieldInfo['main'];
    }

    public function build()
    {
        //temporarily set menu name to dynamic one
        $this->menuName = $this->getMenuName();
        $build = parent::build();
        //mimic functionality in menu block
        if (!empty($build['#theme'])) {
            // Add the configuration for use in menu_block_theme_suggestions_menu().
            $build['#subsite_menu_block_configuration'] = $this->configuration;
            $build['#subsite_menu_block_configuration']['subsite_menu_block'] = true;
            $menu_name = $this->getMenuName();
            if(strpos($menu_name, '--')) {
                $parts = explode('--', $menu_name);
                $originalMenuName = array_pop($parts);
                $build['#subsite_menu_block_configuration']['original_menu_name'] = $originalMenuName;
            }
            // Remove the menu name-based suggestion so we can control its precedence
            // better in menu_block_theme_suggestions_menu().
            $build['#theme'] = 'menu';
        }
        $this->menuName = null;
        return $build;
    }

    public function previewBuild() {
        return $this->t('menu: "@menu"', [
            '@menu' => $this->getMenuTitle()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheTags() {
        //temporarily set menu name to dynamic one
        $this->menuName = $this->getMenuName();
        $return = parent::getCacheTags();
        $this->menuName = null;
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheContexts() {
        //temporarily set menu name to dynamic one
        $this->menuName = $this->getMenuName();
        $return = parent::getCacheContexts();
        $this->menuName = null;
        return $return;
    }

    public function getCacheMaxAge()
    {
        return 0;
    }
}
