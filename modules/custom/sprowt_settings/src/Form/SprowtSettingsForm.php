<?php

namespace Drupal\sprowt_settings\Form;

use Drupal\block_content\Entity\BlockContent;
use Drupal\chat_codes\Entity\ChatCode;
use Drupal\color_variables\Entity\ColorVariableItem;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Form;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Drupal\sprowt\Form\PackageLevelForm;
use Drupal\sprowt_settings\SprowtSettings;
use Drupal\sprowt_theme\SprowtThemeService;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Sprowt Settings settings for this site.
 */
class SprowtSettingsForm extends ConfigFormBase
{
    /**
     * @var SprowtSettings
     */
    protected $sprowtSettings;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;



    public static $fields = [
        'general' => [
            'phone_replace' => [
                'default' => '$1-$2-$3',
                'description' => 'The pattern used for the phone number. A phone number comes in three parts: $1 = the first three digits. $2 = the second three digits. $3 = the last four digits.',
                'title' => 'Phone number pattern',
                'class' => ['phone-number-pattern-input']
            ],
			'disable_aggregaterating_schema' => [
				'type' => 'checkbox',
				'default' => false,
				'description' => 'Prevent aggregateRating schema from rendering on page.',
				'title' => 'Disable aggregateRating Schema'
			],
			'price_range' => [
				'title' => 'LocalBusiness schema price range value',
				'description' => 'LocalBusiness schema category price range value to appear on Google. For more information, visit <a href="https://developers.google.com/search/docs/appearance/structured-data/local-business">https://developers.google.com/search/docs/appearance/structured-data/local-business</a>',
			],
			'blog_dates_displayed' => [
				'type' => 'checkbox',
				'default' => false,
				'description' => 'Display of Blog Post dates sitewide.',
				'title' => 'Blog Dates Displayed'
			],
			'blog_global_author' => [
				'title' => 'Blog Global Author',
				'description' => 'Set author for all Blog Posts'
			],
            'blog_global_author_link' => [
                'title' => 'Blog Global Author Link',
                'description' => 'Set link for Global Author',
				'default' => '/about-us/meet-team'
            ],
            'mail_reroute' => [
                'type' => 'radios',
                'title' => 'Mail routing',
                'description' => 'Determines how mail is routed globally through the site',
                'default' => 'default'
            ],
            'mail_reroute_email' => [
                'title' => 'Reroute Email',
                'description' => 'The email to reroute all emails through. Leave this blank to simply block emails when rerouted.',
                'type' => 'email'
            ],
            'jump_button' => [
                'type' => 'checkbox',
                'default' => false,
                'description' => 'Add a "jump to top" button to the bottom of the page to automatically jump to the top when someone clicks it.',
                'title' => 'Jump To Top Button'
            ]
        ],
        'branding' => [
            'menu_style' => [
                'title' => 'Main menu style',
                'description' => 'The style of the main menu.',
                'type' => 'select',
                'options' => [
                    '' => 'Default',
                    'blocky' => 'Blocky',
                ],
                'weight' => -2
            ],
            'sprowt_theme' => [
                'title' => 'Theme',
                'description' => 'The chosen node theme for home pages, services, city pages, etc',
                'type' => 'select',
                'weight' => -2
            ],
        ],
        'company_info' => [
            'company_name' => [
                'title' => 'Company Name',
                'description' => 'Name of the company to display on the site.',
                'required' => true,
                'weight' => -2
            ],
            'packageLevel' => [
                'title' => 'Package level',
                'description' => 'Indicates the site package level. Only affects rollout during site install. Does not affect a site that has already been rolled out.',
                'required' => true,
                'type' => 'select',
                'weight' => -2
            ],
            'ala_carte' => [
                'title' => 'Optional site add-ons',
                'description' => 'A la carte options for this client\'s site',
                'type' => 'select',
                'weight' => -2
            ],
            'hide_texting_opt_in' => [
                'title' => 'Hide Texting Opt-In Field',
                'description' => 'Checking this will hide the opt in checkbox for texting on the webforms.',
                'type' => 'checkbox',
                'weight' => -2
            ],
            'year_started' => [
                'title' => 'Year Started',
                'description' => 'The year the company began. This will also determine the "years in business."',
                'type' => 'number',
                'attributes' => [
                    'list' => 'year-list'
                ],
                'weight' => 0
            ],
            'webform_to_email' => [
                'type' => 'email',
                'title' => 'Webform to email',
                'description' => 'Email typically used as the \'to\' email in webforms.',
                'weight' => 0
            ],
            'webform_from_email' => [
                'type' => 'email',
                'title' => 'Webform from email',
                'description' => 'Email typically used as the \'from\' email in webforms.',
                'weight' => 0
            ],
            'company_phone' => [
                'title' => 'Company Phone Number',
                'description' => "Hard-Line phone number to display site-wide. This number is typically swapped with a tracking number via the CTM integration.",
                'required' => true,
                'phone' => true,
                'weight' => 1
            ],
            'customer_login' => [
                'type' => 'url',
                'title' => 'Customer Login/My Account link/Payment Portal',
                'description' => 'Customer login link used throughout site that directs to the clients payment portal and/or customer account login for their customers to route to.',
                'weight' => 1
            ],
            'state' => [
                'title' => 'State/Province',
                'required' => true,
                'type' => 'select',
                'description' => 'The U.S. state or Canadian province for the company',
                'weight' => 1
            ],
        ],
        'contact' => [
            'contact_name' => [
                'title' => 'Contact Name',
                'description' => 'Full name of the point of contact.'
            ],
            'contact_email' => [
                'title' => 'Contact Email',
                'type' => 'email',
                'description' => 'Email for the point of contact.'
            ],
            'contact_phone' => [
                'title' => 'Contact Phone',
                'description' => 'Phone number for the point of contact.',
                'phone' => true
            ]
        ]
    ];

    protected $industries;

    protected $branches;

    public function __construct(
        ConfigFactoryInterface $config_factory,
        SprowtSettings $sprowtSettings,
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->sprowtSettings = $sprowtSettings;
        parent::__construct($config_factory);
    }

    public static function defaultSettings() {
        $default = [];
        foreach (static::$fields as $cat => $fields) {
            foreach($fields as $id => $def) {
                $default[$id] = null;
                if(!empty($def['default'])) {
                    $default[$id] = $def['default'];
                }
            }
        }
        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('config.factory'),
            $container->get('sprowt_settings.manager'),
            $container->get('entity_type.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_settings_sprowt_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [SprowtSettings::$configName];
    }

    protected function getIndustries($refresh = false) {
        if(isset($this->industries) && !$refresh) {
            return $this->industries;
        }

        /** @var TermStorageInterface $storage */
       $storage = $this->entityTypeManager->getStorage('taxonomy_term');
       $this->industries = $storage->loadTree('industry', 0, 1, true);
       return $this->industries;
    }

    protected function getBranches($refresh = false) {
        if(isset($this->branches)) {
            return $this->branches;
        }

        /** @var NodeStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('node');
        $this->branches = $storage->loadByProperties([
            'type' => 'branch'
        ]);
        return $this->branches;
    }

    public static function getSettingsDefinitionsByType($type)
    {
        $return = [];
        foreach (static::$fields as $cat => $fields) {
            foreach($fields as $id => $def) {
                $def['type'] = $def['type'] ?? 'textfield';
                if($def['type'] == $type) {
                    $return[$id] = $def;
                }
            }
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['#attributes'] = [
            'novalidate' => true
        ];
        $form['settings'] = [
            '#type' => 'vertical_tabs'
        ];
        $form['yearList'] = [
            '#type' => 'html_tag',
            '#tag' => 'datalist',
            '#attributes' => [
                'id' => 'year-list'
            ]
        ];
        $yearPeriod = new \DatePeriod(
            new \DateTime('1900-06-15'),
            new \DateInterval('P1Y'),
            new \DateTime('now')
        );
        foreach($yearPeriod as $date) {
            $form['yearList']['yearList--' . $date->format('Y')] = [
                '#type' => 'html_tag',
                '#tag' => 'option',
                '#attributes' => [
                    'value' => $date->format('Y')
                ]
            ];
        }


        $tabs = [];

        $tabs['general'] = [
            '#type' => 'details',
            '#title' => $this->t('General')
        ] + $this->general($form, $form_state);

        $tabs['branding'] = [
                '#type' => 'details',
                '#title' => $this->t('Branding')
            ] + $this->branding($form, $form_state);

        $tabs['company_info'] = [
            '#type' => 'details',
            '#title' => $this->t('Company Info')
        ] + $this->companyInfo($form, $form_state);

        $tabs['company_info']['contact'] = [
            '#type' => 'details',
            '#title' => $this->t('Point of Contact'),
            '#open' => true,
            '#weight' => 10,
            '#description' => 'The Main Point of Contact Coalmarch has with the company. e.g. the business owner, Sales/Marketing Manager, Operations/Office Manager, etc.'
        ] + $this->pointOfContact($form, $form_state);

        $tabs['integrations'] = [
                '#type' => 'details',
                '#title' => $this->t('Integrations')
            ] + $this->integrations($form, $form_state);

        $tabs['ctm'] = [
            '#type' => 'details',
            '#title' => $this->t('Phone Number Buttons')
        ] + $this->ctm($form, $form_state);

        $tabs['custom'] = [
                '#type' => 'details',
                '#title' => $this->t('Custom Tokens')
            ] + $this->customTokens($form, $form_state);

        $tabs['conditionalTokens'] = [
            '#type' => 'details',
            '#title' => $this->t('Conditional Tokens')
        ] + $this->conditionalTokens($form, $form_state);

        $tabs = $this->groupify($tabs, 'settings');

        $form += $tabs;
        $form['#attached'] = [
            'library' => [
                'sprowt_settings/settings_form'
            ]
        ];
        return parent::buildForm($form, $form_state);
    }

    public function buildSettingsFormSection($section) {
        $form = [];
        $fields = static::$fields[$section] ?? [];
        $i = 0;
        foreach($fields as $id => $def) {
            $form[$id] = [
                '#type' => 'textfield',
            ];
            $classes = [];
            foreach($def as $key => $val) {
                switch($key) {
                    case 'type':
                        $form[$id]['#type'] = $val;
                        break;
                    case 'title':
                        $form[$id]['#title'] = $this->t($val);
                        break;
                    case 'description':
                        $form[$id]['#description'] = $this->t($val);
                        break;
                    case 'default':
                        $form[$id]['#default_value'] = $val;
                        break;
                    case 'required':
                        $form[$id]['#required'] = $val;
                        break;
                    case 'phone':
                        if(!empty($val)) {
                            $classes[] = 'phone-field';
                        }
                        $form[$id]['#type'] = 'tel';
                        $form[$id]['#size'] = 20;
                        $form[$id]['#maxlength'] = 20;
                        if(empty($form[$id]['#element_validate'])) {
                            $form[$id]['#element_validate'] = [];
                        }
                        $form[$id]['#element_validate'][] = [$this, 'validatePhone'];
                        break;
                    case 'class':
                        if(!empty($val)) {
                            $classes = array_merge($classes, $val);
                        }
                        break;
                    case 'length':
                        $form[$id]['#maxlength'] = $val;
                        break;
                    case 'placeholder':
                        if(empty($form[$id]['#attributes'])) {
                            $form[$id]['#attributes'] = [];
                        }
                        $form[$id]['#attributes']['placeholder'] = $val;
                        break;
                    case 'weight':
                        $form[$id]['#weight'] = $val;
                        break;
                    case 'attributes':
                        if(empty($form[$id]['#attributes'])) {
                            $form[$id]['#attributes'] = [];
                        }
                        foreach($val as $key => $v) {
                            $form[$id]['#attributes'][$key] = $v;
                        }
                        break;
                    case 'options':
                        $form[$id]['#options'] = $val;
                        break;
                }
            }
            if(empty($form[$id]['#attributes'])) {
                $form[$id]['#attributes'] = [];
            }
            $form[$id]['#attributes']['data-sprowt-setting'] = $id;
            if(!empty($classes)) {
                $form[$id]['#attributes']['class'] = $classes;
            }

            $form[$id]['#default_value'] = $this->getSetting($id, $form[$id]['#default_value'] ?? null);

            if(!empty($def['phone'])) {
                if(!empty($form[$id]['#default_value'])) {
                    $form[$id]['#default_value'] = $this->sprowtSettings->formatPhone($form[$id]['#default_value']);
                }
            }
        }
        return $form;
    }

    protected function defaultThemeSettingsUrl($options = []) {
        $defaultTheme = $this->configFactory->get('system.theme')->get('default');
        if(!empty($defaultTheme)) {
            $opts = array_merge([
                'query' => \Drupal::destination()->getAsArray()
            ], $options);
            return Url::fromRoute('system.theme_settings_theme', [
                'theme' => $defaultTheme
            ], $opts);
        }
        return null;
    }

    public function branding($parent, FormStateInterface $formState) {
        $form = $this->buildSettingsFormSection('branding');

        $form['sprowt_theme']['#options'] = SprowtThemeService::$themes;
        $form['sprowt_theme']['#empty_option'] = 'No theme';

        $logo = $this->getSetting('special_offer_logo');
        $reverseLogo = $this->getSetting('special_offer_logo_reverse');



        $form['logo'] = $this->editLink('Logo Image', $this->defaultThemeSettingsUrl([
            'fragment' => 'edit-theme-settings' //link to details block above
        ]), 'The Logo file used in the Header Logo block');

        $form['reverse_logo'] = $this->editLink('Reverse Logo Image', $this->defaultThemeSettingsUrl([
            'fragment' => 'edit-logo'
        ]), 'The Logo file used where there are background colors that require a reverse color scheme. Typically used on special offers.');

        $form['touch_icon'] = $this->editLink('Touch Icon', $this->defaultThemeSettingsUrl([
            'fragment' => 'edit-logo-reverse'
        ]), "The icon image used when you add an a URL as an app to your phone's home screen and used in place of the favicon on Safari.");

        $form['favicon'] = $this->editLink('Favicon', $this->defaultThemeSettingsUrl([
            'fragment' => 'edit-touch-icon'
        ]), "The file for the favicon on the browser tab.");

        $form['special_offer_logo'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Special Offer Logo'),
            '#description' => Markup::create(implode('<br>', [
                $this->t('Logo used specifically for special offers.'),
                $this->t('Valid extensions are: .gif, .png, .jpg, .jpeg, .webp, and .svg.'),
                $this->t('Max file size is 2M.')
            ])),
            '#upload_validators' => [
                'file_validate_extensions' => ['gif png jpg jpeg svg webp'],
                // Pass the maximum file size in bytes
                'file_validate_size' => [2 * 1024 * 1024], //2M
            ],
            '#accept' => 'image/*,.svg',
            '#default_value' => $logo instanceof File ? [$logo->id()] : null,
            '#weight' => 1,
            '#attributes' => [
                'data-sprowt-setting' => 'special_offer_logo'
            ]
        ];

        $form['special_offer_logo_reverse'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Special Offer Logo Reverse'),
            '#description' => Markup::create(implode('<br>', [
                $this->t('Logo used specifically for special offers, but for a reverse color scheme.'),
                $this->t('Valid extensions are: .gif, .png, .jpg, .jpeg, .webp, and .svg.'),
                $this->t('Max file size is 2M.')
            ])),
            '#upload_validators' => [
                'file_validate_extensions' => ['gif png jpg jpeg svg webp'],
                // Pass the maximum file size in bytes
                'file_validate_size' => [2 * 1024 * 1024], //2M
            ],
            '#accept' => 'image/*,.svg',
            '#default_value' => $reverseLogo instanceof File ? [$reverseLogo->id()] : null,
            '#weight' => 1,
            '#attributes' => [
                'data-sprowt-setting' => 'special_offer_logo_reverse'
            ]
        ];

        $defaultTheme = $this->configFactory->get('system.theme')->get('default');

        $form['color_variables'] = _color_variables_form_item($defaultTheme);
        $form['color_variables']['#weight'] = 5;

        return $this->groupify($form, 'branding');
    }

    public function general($parent, FormStateInterface $formState) {
        $form = $this->buildSettingsFormSection('general');
        $form['phone_replace']['#field_suffix'] = 'e.g.: <span class="pattern-example">'.$this->sprowtSettings->formatPhone('1234567890').'</span>';

        $form['mail_reroute']['#options'] = [
            'default' => $this->t('Default behavior (reroute/block on all environments except Live)'),
            'reroute' => $this->t('Reroute/block all mail'),
            'pass' => $this->t('Allow all mail as normal')
        ];

        return $this->groupify($form, 'general');
    }

    public function pointOfContact($parent, FormStateInterface $formState) {
        $form = $this->buildSettingsFormSection('contact');
        return $this->groupify($form, 'contact');
    }

    public static function industryElement() {
        $industryTerms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
            'vid' => 'industry'
        ]);
        $selectedIndustries = [];
        $industryOptions = [];
        /** @var Term $industryTerm */
        foreach($industryTerms as $industryTerm) {
            $industryOptions[$industryTerm->uuid()] = $industryTerm->getName();
            $isCompanyIndustry = $industryTerm->field_is_company_industry->value;
            if(!empty($isCompanyIndustry) && $isCompanyIndustry !== '0') {
                $selectedIndustries[] = $industryTerm->uuid();
            }
        }

        $element = [
            '#type' => 'checkboxes',
            '#title' => t('Industries Serviced'),
            '#required' => true,
            '#description' => t("The type(s) of industry this company's services provided fall under"),
            '#attributes' => [
                'class' => ['company-type-field'],
                'data-sprowt-setting' => 'company_type'
            ],
            '#options' => array_merge($industryOptions),
            '#default_value' => $selectedIndustries,
            '#weight' => -2,
            '#prefix' => '<div id="company-industry-wrapper">',
            '#suffix' => '</div>',
        ];

        return $element;
    }

    public function companyInfo($parent, FormStateInterface $formState) {
        $form = $this->buildSettingsFormSection('company_info');

        if(empty($form['webform_to_email']['#attributes'])) {
            $form['webform_to_email']['#attributes'] = [];
        }
        $form['webform_to_email']['#element_validate'] = [
            [$this, 'validateEmail']
        ];
        $form['webform_to_email']['#attributes']['multiple'] = true;

        $form['packageLevel']['#options'] = PackageLevelForm::packageOptions();



        $form['company_type'] = static::industryElement();

        $form['add_new_industry'] = [
            '#type' => 'link',
            '#title' => 'Add Custom Industry',
            '#url' => Url::fromRoute('sprowt_settings.sprowt_settings_add_industries'),
            '#ajax' => [
                'dialogType' => 'modal',
                'dialog' => [
                    'height' => 900,
                    'width' => '85%',
                    'maxWidth' => '800px',
                    'resizable' => true,
                    'draggable' => true,
                    'autoResize' => false
                ],
            ],
            '#attributes' => [
                'class' => ['button']
            ],
            '#weight' => -2
        ];

        $branches = $this->getBranches();
        $selectedBranch = null;
        $branchOpts = [];
        /** @var Node $branch */
        foreach($branches as $branch) {
            $branchOpts[$branch->uuid()] = $branch->label();
            $isMainBranch = $branch->field_is_main_branch->value;
            if(!empty($isMainBranch) && $isMainBranch !== '0') {
                $selectedBranch = $branch;
            }
        }
        asort($branchOpts);
        $form['main_branch'] = [
            '#type' => 'select',
            '#title' => $this->t('Main Location'),
            '#description' => $this->t('Main location for the company.'),
            '#options' => array_merge(['' => '- Select -'], $branchOpts),
            '#default_value' => $selectedBranch instanceof Node ? $selectedBranch->uuid() : null,
            '#required' => true,
            '#weight' => 1,
            '#attributes' => [
                'data-sprowt-setting' => 'main_branch'
            ]
        ];

        $form['state']['#options'] = SprowtSettings::$states;

        $form['year_started']['#min'] = 0;
        $form['year_started']['#max'] = (new \DateTime())->format('Y');
        /** @var BlockContent $affiliationsBlock */
        $affiliationsBlock = SprowtSettings::loadBlockByUuid('d347d783-31c7-46ee-840b-5e54cc30fb34');
        /** @var BlockContent $socialMediaBlock */
        $socialMediaBlock = SprowtSettings::loadBlockByUuid('7e5f53fa-561e-4ef8-9ba7-778f95cb1cce');

        if($affiliationsBlock instanceof BlockContent) {
            $form['affiliations'] = $this->editLink('Affiliations', $affiliationsBlock->toUrl('edit-form', [
                    'query' => \Drupal::destination()->getAsArray()
                ]), 'Setup/update the affiliated organizations/businesses the client is involved with. e.g. Better Business Bureau') + [
                    '#weight' => 2
                ];
        }

        if($socialMediaBlock instanceof BlockContent) {
            $form['social_media'] = $this->editLink('Social Media Footer Links', $socialMediaBlock->toUrl('edit-form', [
                    'query' => \Drupal::destination()->getAsArray()
                ]), 'Setup/update the links to the various social media profiles the client has.') + [
                    '#weight' => 2
                ];
        }


        $form['ala_carte']['#options'] = static::alaCarteOptions();

        $form['ala_carte']['#multiple'] = true;

        return $this->groupify($form, 'company_info');
    }

    public static function alaCarteOptions()
    {
        return [
            'career_template' => 'Careers Page template (not site) ',
            'image_gallery' => 'Image Gallery',
            'team_profiles' => 'Team Profiles',
            'solutions_finder' => 'Solutions Finder',
            'learning_center' => 'Learning Center'
        ];
    }

    public static function validateEmail(&$element, FormStateInterface $form_state, &$complete_form) {
        $rawValue = !empty($element['#value']) ? trim($element['#value']) : '';
        $value = '';
        if($rawValue !== '') {
            if(strpos($rawValue, ',') !== false) {
                $emails = explode(',', $rawValue);
                $valid = true;
                $value = [];
                foreach($emails as $email) {
                    $email = trim($email);
                    if(!empty($email)) {
                        $valid &= \Drupal::service('email.validator')
                            ->isValid($email);
                        $value[] = $email;
                    }
                }
                $value = implode(',', $value);
                $form_state->setValueForElement($element, $value);
                if(!$valid) {
                    $form_state
                        ->setError($element, t('The email addresses %mail are not valid.', [
                            '%mail' => $value,
                        ]));
                }
            }
            else {
                $value = trim($rawValue);
                $form_state
                    ->setValueForElement($element, $value);
                if ($value !== '' && !\Drupal::service('email.validator')
                        ->isValid($value)) {
                    $form_state
                        ->setError($element, t('The email address %mail is not valid.', [
                            '%mail' => $value,
                        ]));
                }
            }
        }
    }

    public function integrations($parent, FormStateInterface $formState) {
        $gtmContainer = \Drupal\google_tag\Entity\Container::load('default');
        if($gtmContainer instanceof \Drupal\google_tag\Entity\Container) {
            $gtmUrl = $gtmContainer->toUrl('edit-form', [
                'query' => \Drupal::destination()->getAsArray()
            ]);
        }
        else {
            $gtmUrl = $this->routeUrl('entity.google_tag_container.collection');
        }

        $form = [];

        $form['gtm'] = $this->editLink('GTM', $gtmUrl, 'The integration between the site and Google Tag Manager that allows for event/lead and traffic tracking');

        $form['ctm'] = $this->editLink('CTM', $this->routeUrl('ctm_api.settings'), 'The integration between the site and CallTrackingMetrics that allows for tracking number swapping and phone call tracking');

        $form['chat_codes'] = $this->editLink('Chat codes', $this->routeUrl('entity.chat_code.collection'), 'The integration between the site and an external/third-party webchat feature');

        $form['scripts'] = $this->editLink('Inserted scripts', $this->routeUrl('entity.script.collection'), 'The integration between the site and external/third-party scripts');

        $form['antispam'] = $this->editLink('AntiSpam module', $this->routeUrl('sprowt_antispam.settings_form'), 'The integration between the site and our anti-spam tool to mitigate spam form submissions from getting through to clients');

        $form['lawnbot'] = $this->editLink('Servicebot Module', $this->routeUrl('lawnbot.settings_form'), 'The integration between the site and our preferred ebooking vendor, Servicebot');

        $form['pestpac'] = $this->editLink('Pestpac module', $this->routeUrl('pestpac_api.settings'), "The webform integration that allows form submissions to be sent directly into the client's CRM");

        $form['sa5'] = $this->editLink('SA5 module', $this->routeUrl('sa5_api.settings_form'), "The webform integration that allows form submissions to be sent directly into the client\'s CRM");

        $form['sales_center'] = $this->editLink('Sales Center module', $this->routeUrl('sales_center_api.settings_form'), "The webform integration that allows form submissions to be sent directly into the client's CRM");

        return $form;
    }

    protected function routeUrl($route, $params = [], $options = []) {
        if(empty($options['query'])) {
            $options['query'] = [];
        }
        $options['query']['destination'] = \Drupal::destination()->get();
        return Url::fromRoute($route, $params, $options);
    }

    public function ctm($parent, FormStateInterface $formState) {
        $buttons = $this->getSetting('ctm', []);
        $form['ctmValue'] = [
            '#type' => 'hidden',
            '#default_value' => json_encode($buttons),
            '#attributes' => [
                'id' => 'ctm-buttons-value-field'
            ]
        ];
        $form['ctmTableDescription'] = [
            '#type' => 'item',
            '#title' => '',
            '#description_toggle' => false,
            '#description' => Markup::create('If a client has multiple phone numbers, you can add them all here and then pull these into the content via the tokens generated by this feature. To review the new tokens generated by this feature, visit the <a href="/admin/help/token" target="_blank">Token Library</a>.')
        ];
        $form['ctmTable'] = [
            '#type' => 'table',
            '#attributes' => [
                'id' => 'ctm-table'
            ],
            '#rows' => [
                [
                    'valueCell' => [
                        '#type' => 'html_tag',
                        '#tag' => 'div',
                        '#attributes' => [
                            'data-placeholder' => '1'
                        ]
                    ],
                    'removeCell' => [
                        '#type' => 'html_tag',
                        '#tag' => 'div',
                        '#attributes' => [
                            'data-placeholder' => '1'
                        ]
                    ]
                ]
            ]
        ];
        $form['add_ctm_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Add Ctm Button'),
            '#submit' => [[$this, 'addToken']],
            '#name' => 'addToken',
            '#attributes' => [
                'id' => 'add-ctm-button'
            ]
        ];

        $form['ctmTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'id' => 'ctm-template',
                'type' => 'text/template'
            ]
        ];
        $template = [
            '#type' => 'html_tag',
            '#tag' => 'tr',
            'valueCell' => [
                '#type' => 'html_tag',
                '#tag' => 'td',
                '#attributes' => [
                    'class' => ['value-cell']
                ],
                'machine_name' => [
                    '#type' => 'textfield',
                    '#title' => $this->t('Machine Name'),
                    '#attributes' => [
                        'class' => ['ctm-button-machine-name', 'ctm-input', 'custom-machine-name'],
                        'required' => 'required'
                    ],
                    '#description' => t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.')
                ],
                'ctmNumber' => [
                    '#type' => 'select',
                    '#title' => $this->t('Phone number'),
                    '#options' => [
                        'company_phone' => $this->t('Company Phone'),
                        'contact_phone' => $this->t('Company Contact Phone'),
                        'custom' => $this->t('Custom')
                    ],
                    '#attributes' => [
                        'class' => ['ctm-button-number', 'ctm-select'],
                        'data-custom' => 'ctm-custom-number',
                        'required' => 'required'
                    ],
                ],
                'customNumber' => [
                    '#type' => 'textfield',
                    '#title' => $this->t('Custom Number'),
                    '#attributes' => [
                        'class' => ['ctm-custom-number', 'ctm-input', 'phone-field'],
                    ],
                ],
                'buttonValue' => [
                    '#type' => 'select',
                    '#title' => $this->t('Button Value'),
                    '#options' => [
                        'number_only' => $this->t('Formatted Phone Number'),
                        'custom' => $this->t('Custom')
                    ],
                    '#attributes' => [
                        'class' => ['ctm-button-value', 'ctm-select'],
                        'data-custom' => 'ctm-custom-value',
                        'required' => 'required'
                    ],
                ],
                'customValue' => [
                    '#type' => 'textfield',
                    '#title' => $this->t('Custom Value'),
                    '#attributes' => [
                        'class' => ['ctm-custom-value', 'ctm-input'],
                    ],
                ],
            ],
            'removeCell' => [
                '#type' => 'html_tag',
                '#tag' => 'td',
                '#attributes' => [
                    'class' => ['remove-cell']
                ],
                'remove' => [
                    '#type' => 'submit',
                    '#value' => $this->t('Remove'),
                    '#attributes' => [
                        'class' => ['remove-ctm-button']
                    ],
                ]
            ]
        ];

        $form['ctmTemplate']['template'] = $template;

        return $this->groupify($form, 'ctm');
    }

    public function customTokens($parent, FormStateInterface $formState) {
        $savedTokens = $this->getSetting('custom', []);
        $form['tokens'] = [
            '#type' => 'hidden',
            '#default_value' => !empty($savedTokens) ? json_encode($savedTokens) : '{}',
            '#attributes' => [
                'id' => 'token-value-field'
            ]
        ];
        $form['tokenTableDescription'] = [
            '#type' => 'item',
            '#title' => '',
            '#description_toggle' => false,
            '#description' => 'Create custom tokens that pertain only to this specific site. e.g. the client loves to use a specific tagline throughout the content, has additional UVPs that should be baked into content, has specific special offers, etc.'
        ];
        $form['tokenTable'] = [
            '#type' => 'table',
            '#attributes' => [
                'id' => 'custom-token-table'
            ],
            '#rows' => [
                [
                    'valueCell' => [
                        '#type' => 'html_tag',
                        '#tag' => 'div',
                        '#attributes' => [
                            'data-placeholder' => '1'
                        ]
                    ],
                    'removeCell' => [
                        '#type' => 'html_tag',
                        '#tag' => 'div',
                        '#attributes' => [
                            'data-placeholder' => '1'
                        ]
                    ]
                ]
            ]
        ];

        $form['token_actions'] = [
            '#type' => 'actions'
        ];

        $form['token_actions']['add_token'] = [
            '#type' => 'submit',
            '#value' => $this->t('Add Token'),
            '#submit' => [[$this, 'addToken']],
            '#name' => 'addToken',
            '#attributes' => [
                'id' => 'addTokenButton'
            ]
        ];

        /** @var ModuleHandler $moduleHandler */
        $moduleHandler = \Drupal::service('module_handler');
        if($moduleHandler->moduleExists('sprowt_content')) {
            $form['token_actions']['sync_tokens_with_source'] = [
                '#type' => 'submit',
                '#value' => $this->t('Add new tokens from source'),
                '#name' => 'syncTokenButton',
                '#attributes' => [
                    'id' => 'syncTokenButton'
                ]
            ];
        }

        $form['tokenTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'id' => 'token-template',
                'type' => 'text/template'
            ]
        ];

        $template = [
            '#type' => 'html_tag',
            '#tag' => 'tr',
            'valueCell' => [
                '#type' => 'html_tag',
                '#tag' => 'td',
                '#attributes' => [
                    'class' => ['value-cell']
                ],
                'machine_name' => [
                    '#type' => 'textfield',
                    '#title' => $this->t('Machine Name'),
                    '#attributes' => [
                        'class' => ['custom-token-machine-name', 'custom-machine-name'],
                        'required' => 'required'
                    ],
                    '#description' => t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores. This will be used as the name of the token prefixed with "sprowt:" e.g. [sprowt:special_offer_pest]')
                ],
                'value' => [
                    '#type' => 'textarea',
                    '#title' => $this->t('Value'),
                    '#attributes' => [
                        'class' => ['custom-token-value']
                    ],
                    '#description' => 'The text output of the token on the front end of the site; what customers will see when browsing the site. e.g. "$100 off"'
                ]
            ],
            'removeCell' => [
                '#type' => 'html_tag',
                '#tag' => 'td',
                '#attributes' => [
                    'class' => ['remove-cell']
                ],
                'remove' => [
                    '#type' => 'submit',
                    '#value' => $this->t('Remove'),
                    '#attributes' => [
                        'class' => ['remove-token']
                    ],
                ]
            ]
        ];

        $form['tokenTemplate']['template'] = $template;

        return $this->groupify($form, 'custom');
    }

    public function tokenMachineName($value, $element, FormStateInterface $formState) {
        $tokenFields = $formState->getValue('tokens');
        foreach($tokenFields as $delta => $tokenField) {
            if($tokenField['values']['machine_name'] == $value
                && !in_array($delta, $element['#parents'])
            ) {
                return true;
            }
        }
        return false;
    }

    public function addToken(&$form, FormStateInterface &$formState) {
        $tokens = $formState->getValue('tokens') ?? [];
        if(empty($tokens)) {
            $tokens  = [];
        }
        $tokens[] = [
            'values' => [
                'machine_name' => null,
                'value' => null
            ],
            'weight' => count($tokens)
        ];
        $formState->setValue('tokens', $tokens);
        $formState->setRebuild();
    }

    public function removeToken(&$form, FormStateInterface &$formState) {
        $trigger = $formState->getTriggeringElement();
        $delta = str_replace('removeCustomToken--', '', $trigger['#name']);
        $tokens = $formState->getValue('tokens') ?? [];
        if(empty($tokens)) {
            $tokens  = [];
        }
        if(!empty($tokens[$delta])) {
            unset($tokens[$delta]);
        }
        $formState->setValue('tokens', $tokens);
        $formState->setRebuild();
    }

    public function replaceTableCallback(&$form, FormStateInterface &$formState) {
        return $form['custom']['tokens'];
    }

    public function conditionalTokenOperationsLinks($key, $value = null, $visibility = null) {
        $links = [
            'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('sprowt_settings.conditional_token', [], [
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
                'url' => Url::fromRoute('sprowt_settings.conditional_token_delete', [
                    'key' => $key,
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

    public function conditionalTokenElement(FormStateInterface &$formState, $key, $value = null, $visibility = null) {
        $form = [];
        $form['conditional_token__' . $key] = [
            '#type' => 'container',
        ];


        $wrap = &$form['conditional_token__' . $key];
        $wrap['key'] = [
            '#type' => 'item',
            '#title' => $this->t('Machine name'),
            'value' => [
                '#type' => 'markup',
                '#markup' => Markup::create("<div style='padding-bottom: 10px; padding-left: 15px;'>{$key}</div>")
            ]
        ];

        $wrap['value'] = [
            '#type' => 'item',
            '#title' => $this->t('Value'),
            'value' => [
                '#type' => 'markup',
                '#markup' => Markup::create("<div style='padding-bottom: 10px; padding-left: 15px;'>{$value}</div>")
            ]
        ];

        $wrap['visibility'] = $this->buildVisibilityResult([], $formState, [], $visibility);



        $form['conditional_token__' . $key]['operationsCell'] = [
            '#type' => 'html_tag',
            '#tag' => 'td',
            '#attributes' => [
                'class' => ['remove-cell']
            ],
            'operations' => [
                '#type' => 'operations',
                '#title' => $this->t('Operations'),
                '#links' => $this->conditionalTokenOperationsLinks($key, $value, $visibility),
            ]
        ];

        return $form;
    }

    public function rebuildConditionalTokens(&$form, FormStateInterface &$formState) {
        $return = $this->conditionalTokens($form, $formState);
        return $return['conditional_token_wrapper'];
    }


    /**
     * Helper function for building the visibility UI form.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form array with the visibility UI added in.
     */
    protected function buildVisibilityResult(array $form, FormStateInterface $form_state, $contextExtra = [], $visibility = []) {

        $form['visibility_description_wrap'] = [
            '#type' => 'item',
            '#title' => t('Visibility'),
        ];

        $form['visibility_description_wrap']['visibility_description'] = [
            '#type' => 'fieldset'
        ];
        $summaryWrap = &$form['visibility_description_wrap']['visibility_description'];

        $manager = \Drupal::service('plugin.manager.condition');
        foreach ($visibility as $condition_id => $configuration) {

            /** @var \Drupal\Core\Condition\ConditionInterface $condition */
            $condition = $manager->createInstance($condition_id, $configuration ?? []);
            $condition_form = [];
            $condition_form['#type'] = 'item';
            $condition_form['#title'] = $condition->getPluginDefinition()['label'];
            $summary = $condition->summary();
            $condition_form['#field_prefix'] = $summary;

            switch($condition_id) {
                case 'user_role':
                    $condition_form['#title'] = t('Roles');
                    break;
                case 'request_path':
                    $condition_form['#title'] = t('Pages');
                    break;
            }


            if(!empty($condition_form)) {
                $summaryWrap[$condition_id] = $condition_form;
            }
        }

        return $form;
    }

    public function getConditionalTokens(FormStateInterface $formState) {
        $tokens = $this->sprowtSettings->getConditionalTokens();
        return $tokens ?? [];
    }

    public function conditionalTokens($parent, FormStateInterface $formState) {

        $form = [];
        $form['conditional_token_wrapper'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'conditional_token_wrapper',
            ]
        ];

        $wrapper = &$form['conditional_token_wrapper'];
        $wrapper['table'] = [
            '#type' => 'table',
        ];
        $tokens = $this->getConditionalTokens($formState);

        $i = 0;
        foreach($tokens as $token) {
            ++$i;
            $key = $token['key'] ?? '_stub' . $i;
            $value = $token['value'] ?? null;
            $visibility = $token['visibility'] ?? [];
            $wrapper['table'][$key . '_row'] = $this->conditionalTokenElement($formState, $key, $value, $visibility);
        }


        $form['conditional_token_actions'] = [
            '#type' => 'actions'
        ];

        $url = Url::fromRoute('sprowt_settings.conditional_token');

        $form['conditional_token_actions']['addStubConditionalToken'] = [
            '#type' => 'link',
            '#title' => $this->t('Add Token'),
            '#url' => $url,
            '#ajax' => [
                'dialogType' => 'modal',
                'dialog' => [
                    'height' => 1000,
                    'width' => '85%',
                    'maxWidth' => 800,
                    'autoResize' => false,
                    'resizable' => true,
                    'draggable' => true,
                ],
            ],
            '#attributes' => [
                'class' => ['button', 'button--small']
            ]
        ];

        $form['conditional_token_hidden'] = [
            '#type' => 'container',
            '#attributes' => [
                'class' => ['hidden']
            ]
        ];

        $form['conditional_token_hidden']['rebuild'] = [
            '#type' => 'button',
            '#value' => $this->t('Rebuild'),
            '#ajax' => [
                'callback' => [$this, 'rebuildConditionalTokens'],
                'event' => 'click',
                'wrapper' => 'conditional_token_wrapper',
            ],
            '#name' => 'rebuildConditionalTokens',
            '#attributes' => [
                'class' => ['rebuildConditionalTokens--button']
            ]
        ];

        return $this->groupify($form, 'conditionalTokens');

    }


    protected function groupify(&$form, $group) {
        foreach($form as &$element) {
            $element['#group'] = $group;
        }
        return $form;
    }

    protected function editLink($title, $url = null, $description = null) {
        $link = [
            '#title' => $title,
            '#type' => 'item',
            'editLink' => [
                '#type' => 'link',
                '#title' => 'Click to Edit',
                '#attributes' => [
                    'target' => '_blank',
                    'class' => ['click-to-edit', 'button']
                ]
            ]
        ];
        if(!empty($url)) {
            if($url instanceof Url) {
                $link['editLink']['#url'] = $url;
            }
            else {
                $link['editLink']['#url'] = Url::fromUserInput($url);
            }
        }
        if(!empty($description)) {
            $link['#description'] = $description;
        }

        return $link;
    }

    public function validatePhone($element, FormStateInterface $formState, $form) {
        $value = $formState->getValue($element['#parents']);
        $formState->setValue($element['#parents'], $this->sprowtSettings->formatPhone($value, '$1$2$3'));
    }

    public function getSetting($key, $default = null) {
        $context = [
            'settingsForm' => true
        ];
        return $this->sprowtSettings->getSetting($key, $default, $context);
    }

    protected function buildSettings(FormStateInterface $formState) {
        $settings = [];
        $values = $formState->getValues();
        $default = static::defaultSettings();
        foreach($default as $key => $defaultValue) {
            if($key != 'custom') {
                $settings[$key] = $values[$key] ?? null;
            }
        }
        $tokens = empty($values['tokens']) ? [] : json_decode($values['tokens'], true);
        $settings['custom'] = [];
        foreach($tokens as $machine => $val) {
            $machine = preg_replace('/[^a-z0-9_]+/', '_', strtolower($machine));
            $machine = preg_replace('/_[_]+/', '_', $machine);
            $machine = trim($machine, '_');
            if(!empty($machine) && !empty($val)) {
                $settings['custom'][$machine] = $val;
            }
        }
        $ctm = empty($values['ctmValue']) ? [] : json_decode($values['ctmValue'], true);

        foreach($ctm as $machineName => $ctmVal) {
            if(empty($settings['ctm'])) {
                $settings['ctm'] = [];
            }
            $settings['ctm'][$machineName] = $ctmVal;
        }

        return $settings;
    }

    protected function setLogoSingle($key, FormStateInterface $formState) {
        $logo = $formState->getValue($key);
        if(!empty($logo)) {
            $fid = array_pop($logo);
            /** @var File $logo */
            $logo = $this->entityTypeManager->getStorage('file')->load($fid);
            $this->sprowtSettings->setSetting($key, $logo);
        }
        else {
            $this->sprowtSettings->setSetting($key, null);
        }
    }

    protected function setLogos(FormStateInterface $formState) {
        $this->setLogoSingle('special_offer_logo', $formState);
        $this->setLogoSingle('special_offer_logo_reverse', $formState);
    }

    protected function setCompanyType(FormStateInterface $formState) {
        $checkboxes = $formState->getValue('company_type') ?? [];
        $uuids = [];
        foreach($checkboxes as $uuid => $selected) {
            if($selected) {
                $uuids[] = $selected;
            }
        }
        $otherName = $formState->getValue('company_type_other');
        $otherIndustry = null;
        $industries = $this->getIndustries(true);
        $selectedIndustries = [];
        $currentIndustries = [];
        /** @var Term $industryTerm */
        foreach($industries as $industryTerm) {
            $isCompanyIndustry = $industryTerm->field_is_company_industry->value;
            if(!empty($isCompanyIndustry) && $isCompanyIndustry !== '0') {
                $currentIndustries[] = $industryTerm;
            }
            if(in_array($industryTerm->uuid(), $uuids)) {
                $selectedIndustries[] = $industryTerm;
            }
            if($industryTerm->label() == $otherName) {
                $otherIndustry = $industryTerm;
                $selectedIndustries[] = $industryTerm;
                if(!in_array($industryTerm->uuid(), $uuids)) {
                    $uuids[] = $industryTerm->uuid();
                }
            }
        }

        if(!empty($otherName) && empty($otherIndustry)) {
            $newIndustry = Term::create([
                'name' => $otherName,
                'vid' => 'industry',
                'field_is_company_industry' => [['value' => '1']]
            ]);
            $newIndustry->save();
            $selectedIndustries[] = $newIndustry;
            $industries[] = $newIndustry;
            $uuids[] = $newIndustry->uuid();
        }

        if(!empty($currentIndustries)) {
            /** @var Term $industryTerm */
            foreach ($currentIndustries as $industryTerm) {
                if(!in_array($industryTerm->uuid(), $uuids)) {
                    $industryTerm->set('field_is_company_industry', [
                        'value' => '0'
                    ]);
                    $industryTerm->save();
                }
            }
        }

        if(!empty($selectedIndustries)) {
            /** @var Term $industryTerm */
            foreach($selectedIndustries as $industryTerm) {
                $isCompanyIndustry = $industryTerm->field_is_company_industry->value;
                if(empty($isCompanyIndustry) || $isCompanyIndustry === '0') {
                    $industryTerm->set('field_is_company_industry', [
                        'value' => '1'
                    ]);
                    $industryTerm->save();
                }
            }
        }
    }

    protected function setMainBranch(FormStateInterface $formState) {
        $uuid = $formState->getValue('main_branch');
        $this->sprowtSettings->setSetting('main_branch', $uuid);
    }

    protected function validateCompanyType($form, FormStateInterface $formState) {
        $uuid = $formState->getValue('company_type');
        $otherName = $formState->getValue('company_type_other');
        if($uuid == 'other' && empty($otherName)) {
            $formState->setErrorByName('company_type_other', $this->t('An industry served is required.'));
        }
    }

    public function validateTokens($form, FormStateInterface &$formState) {

    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $this->validateTokens($form, $form_state);
        $this->validateCompanyType($form, $form_state);
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $settings = $this->buildSettings($form_state);
        $this->sprowtSettings->setSettings($settings);
        $this->setLogos($form_state);
        $this->setCompanyType($form_state);
        $this->setMainBranch($form_state);

        //refresh render cache
        \Drupal::service('cache.render')->invalidateAll();
        //refresh token cache
        token_clear_cache();

        $this->messenger()->addStatus($this->t('Sprowt settings have been saved.'));
    }

}
