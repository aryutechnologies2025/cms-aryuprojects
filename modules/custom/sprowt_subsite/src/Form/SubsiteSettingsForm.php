<?php

namespace Drupal\sprowt_subsite\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\sprowt_settings\Form\SprowtSettingsForm;
use Drupal\sprowt_subsite\SettingsManager;

/**
 * Provides a Sprowt Subsite form.
 */
class SubsiteSettingsForm extends SprowtSettingsForm
{

    /**
     * @var Node
     */
    protected $subsite;

    /**
     * @var SettingsManager
     */
    protected $settingsManager;

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_subsite_subsite_settings';
    }

    protected function setSubsite() {
        $routeMatch = \Drupal::routeMatch();
        $node = $routeMatch->getParameter('node');
        if(!empty($node) && !$node instanceof Node) {
            $node = Node::load($node);
        }
        $this->subsite = $node;
    }

    protected function settingsManager() {
        if(isset($this->settingsManager)) {
            return $this->settingsManager;
        }
        $this->settingsManager = \Drupal::service('sprowt_subsite.settings_manager');
        return $this->settingsManager;
    }

    public function getSetting($key, $default = null) {
        return $this->settingsManager()->getSetting($this->subsite, $key, $default, false);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $this->setSubsite();
        $form = parent::buildForm($form, $form_state);
        unset($form['integrations']);
        unset($form['company_info']['contact']);
        return $form;
    }

    public function companyInfo($parent, FormStateInterface $formState)
    {
        $form = parent::companyInfo($parent, $formState);
        $hideFields = [
            'company_type',
            'main_branch',
            'packageLevel',
            'company_type_other',
            'add_new_industry',
            'affiliations',
            'social_media',
            ''
        ];

        foreach($hideFields as $hideField) {
            unset($form[$hideField]);
        }
        $selects = [
            'state'
        ];
        foreach($selects as $select) {
            $form[$select]['#empty_option'] = '- Select -';
            $form[$select]['#empty_value'] = '';
            $form[$select]['#default_value'] = $this->settingsManager()->getSetting($this->subsite, $select);
        }


        $form['solution_finder_enabled']['#default_value'] = $this->settingsManager()->getSetting($this->subsite, 'solution_finder_enabled');

        return $form;
    }

    public function general($parent, FormStateInterface $formState)
    {
        $form = parent::general($parent, $formState);
        $form['phone_replace']['#default_value'] = $this->settingsManager()->getSetting($this->subsite, 'phone_replace');
        $form['blog_dates_displayed']['#default_value'] = $this->settingsManager()->getSetting($this->subsite, 'blog_dates_displayed');
        $hideFields = [
            'mail_reroute',
            'mail_reroute_email',
        ];
        foreach($hideFields as $hideField) {
            unset($form[$hideField]);
        }

        return $form;
    }

    public function branding($parent, FormStateInterface $formState)
    {
        $form = parent::branding($parent, $formState);
        $hideFields = [
            'logo',
            'reverse_logo',
            'touch_icon',
            'favicon',
            'color_variables',
            'sprowt_theme'
        ];
        foreach($hideFields as $hideField) {
            unset($form[$hideField]);
        }

        $selects = [
            'sprowt_theme'
        ];
        foreach($selects as $select) {
            $form[$select]['#empty_option'] = '- Select -';
            $form[$select]['#empty_value'] = '';
            $form[$select]['#default_value'] = $this->settingsManager()->getSetting($this->subsite, $select);
        }

        return $form;
    }


    public function buildSettingsFormSection($section)
    {
        $form = parent::buildSettingsFormSection($section);
        foreach($form as &$formItem) {
            $formItem['#required'] = false;
        }
        return $form;
    }

    protected function setLogoSingle($key, FormStateInterface $formState) {
        $logo = $formState->getValue($key);
        if(!empty($logo)) {
            $fid = array_pop($logo);
            /** @var File $logo */
            $logo = $this->entityTypeManager->getStorage('file')->load($fid);
            $this->settingsManager()->setSetting($this->subsite, $key, $logo);
        }
        else {
            $this->settingsManager()->deleteSetting($this->subsite, $key);
        }
    }

    protected function buildSettings(FormStateInterface $formState)
    {
        $settings = parent::buildSettings($formState);
        //unset all null settings so they get deleted/unsaved
        foreach($settings as $key => $val) {
            $mainSetting = $this->sprowtSettings->getSetting($key, null);
            if(!isset($val)) {
                unset($settings[$key]);
            }
            if(is_string($val) && empty($val)) {
                unset($settings[$key]);
            }
            if($val == $mainSetting) {
                unset($settings[$key]);
            }
        }
        if(empty($settings['custom'])) {
            unset($settings['custom']);
        }
        if(empty($settings['ctm'])) {
            unset($settings['ctm']);
        }
        return $settings;
    }

    public function getConditionalTokens(FormStateInterface $formState) {
        $tokens = $this->settingsManager()->getConditionalTokens($this->subsite);
        return $tokens ?? [];
    }

    public function conditionalTokenOperationsLinks($key, $value = null, $visibility = null) {
        $links = [
            'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('sprowt_subsite.conditional_token', [
                    'subsite' => $this->subsite->id(),
                ], [
                    'query' => [
                        'key' => $key,
                        'value' => $value,
                        'visibility' => json_encode($visibility)
                    ]
                ]),
                'attributes' => [
                    'class' => ['use-ajax', 'button', 'action-link'],
                    'data-dialog-type' => 'modal',
                    'data-dialog-options' => json_encode([
                        'height' => 1000,
                        'width' => '85%',
                        'maxWidth' => 800,
                        'autoResize' => false,
                        'resizable' => true,
                        'draggable' => true,
                    ])
                ],
            ],
            'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('sprowt_subsite.conditional_token_delete', [
                    'key' => $key,
                    'subsite' => $this->subsite->id(),
                ]),
                'attributes' => [
                    'class' => ['use-ajax', 'button', 'button-danger'],
                    'data-dialog-type' => 'modal',
                    'data-dialog-options' => json_encode([
                        'height' => 400,
                        'width' => '85%',
                        'maxWidth' => 600,
                        'autoResize' => false,
                        'resizable' => true,
                        'draggable' => true,
                    ])
                ]
            ]
        ];

        return $links;
    }

    public function conditionalTokens($parent, FormStateInterface $formState) {
        $form = parent::conditionalTokens($parent, $formState);

        $url = Url::fromRoute('sprowt_subsite.conditional_token', [
            'subsite' => $this->subsite->id(),
        ]);
        $form['conditional_token_actions']['addStubConditionalToken']['#url'] = $url;

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $settings = $this->buildSettings($form_state);
        $this->settingsManager()->setSettings($this->subsite, $settings);
        $this->setLogos($form_state);
    }

    public static function access(AccountInterface $account)
    {
        $permission = $account->hasPermission('edit any subsite content')
            || $account->hasPermission('edit own subsite content')
            || $account->hasPermission('administer nodes');

        if ($permission) {
            $routeMatch = \Drupal::routeMatch();
            $node = $routeMatch->getParameter('node');
            if (!empty($node) && !$node instanceof Node) {
                $node = Node::load($node);
            }
            return AccessResult::allowedIf($node->bundle() == 'subsite');
        }

        return AccessResult::forbidden();
    }

}
