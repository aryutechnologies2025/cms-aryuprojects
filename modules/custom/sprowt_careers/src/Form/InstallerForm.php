<?php

namespace Drupal\sprowt_careers\Form;

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\Core\Url;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorage;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\sprowt_content\SprowtContentService;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a Sprowt Careers form.
 */
class InstallerForm extends FormBase
{

    protected $templateInfo;

    protected $currentHomePage;

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_careers_installer';
    }

    public function careerPageTemplateInfo() {
        if(isset($this->templateInfo)) {
            return $this->templateInfo;
        }
        $context = [];
        SprowtContentService::gatherTemplateInfoProcess('career_page', 'Career Page', $context);
        $templateInfo = \Drupal::state()->get('sprowt_content.template_info', []);
        $this->templateInfo = $templateInfo['career_page'] ?? [];
        return $this->templateInfo;
    }

    public function sourceMenuItems() {
        /** @var SprowtContentService $service */
        $service = \Drupal::service('sprowt_content.service');
        $menuLinkContent = $service->getMenuLinkContentFromSource();
        $entityArrays = $menuLinkContent['_entities'];
        $menuLinks = [];
        foreach($entityArrays as $uuidKey => $entityArray) {
            $menuName = !empty($entityArray['menu_name'][0]['value']) ? $entityArray['menu_name'][0]['value'] : null;
            if(isset($menuName) && $menuName == 'careers-menu') {
                $exportInfo = $entityArray['_exportInfo'];
                $item = [
                    'uuid' => $exportInfo['uuid'],
                    'title' => $exportInfo['label'],
                    'package_level' => [],
                    'page_type' => null,
                    'parent' => null,
                    'weight' => 0
                ];
                if(!empty($entityArray['parent'][0]['value'])) {
                    $parentUuid = str_replace('menu_link_content:', '', $entityArray['parent'][0]['value']);
                    $item['parent'] = $parentUuid;
                }
                if(!empty($entityArray['weight'][0]['value'])) {
                    $item['weight'] = $entityArray['weight'][0]['value'];
                }
                if(!empty($entityArray["link"][0]["_linkEntity"])){
                    $item['linkUuid'] = $entityArray["link"][0]["_linkEntity"]['uuid'];
                }
                $menuLinks[$exportInfo['uuid']] = $item;
            }
        }

        return $menuLinks;
    }

    public function templateOptions($pageType =  null) {
        $templateInfo = $this->careerPageTemplateInfo();
        $options = [];
        $pageTypes = $this->pageTypes();
        foreach($pageTypes as $type => $label) {
            $options[$label] = [];
        }
        foreach($templateInfo as $item) {
            $pageTypeLabel = $pageTypes[$item['pageType'] ?? 'generic'];
            $options[$pageTypeLabel][$item['uuid']] = $item['label'];
        }
        if(!empty($pageType)) {
            $pageTypeLabel = $pageTypes[$pageType] ?? '';
            if(!empty($options[$pageTypeLabel])) {
                return $options[$pageTypeLabel];
            }
            return [];
        }
        $return = [];
        foreach($options as $label => $labelOptions) {
            if(!empty($labelOptions)) {
                $return[$label] = $labelOptions;
            }
        }
        return $return;
    }

    public function pageTypes() {
        $config = \Drupal::config('field.storage.node.field_career_page_type');
        $settings = $config->get('settings');
        $return = [];
        foreach($settings['allowed_values'] as $allowed_value) {
            $return[$allowed_value['value']] = $allowed_value['label'];
        }
        return $return;
    }

    public function defaultNodes() {
        $menuLinks = $this->sourceMenuItems();
        $templateInfo = $this->careerPageTemplateInfo();
        $items = [];
        foreach($menuLinks as $menuLink) {
            $template = $templateInfo[$menuLink['linkUuid'] ?? '<nolinkUUid>'] ?? [];
            if(!empty($template)) {
                $items[] = [
                    'includeInMenu' => true,
                    'menuLinkTitle' => $menuLink['title'],
                    'nodeTitle' => $template['label'],
                    'nodeTemplate' => $template['uuid'],
                    'menuLinkWeight' => $menuLink['weight'],
                    'nodePublished' => true
                ];
            }
        }
        if(!empty($items)) {
            usort($items, function($a, $b) {
                $aVal = $a['menuLinkWeight'] ?? 0;
                $bVal = $b['menuLinkWeight'] ?? 0;
                if($aVal == $bVal) {
                    return 0;
                }
                return $aVal < $bVal ? -1 : 1;
            });
        }

        return $items;
    }

    public static function currentMenuItems($menu) {
        $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
        $menuContentItems = $storage->loadByProperties([
            'menu_name' => $menu
        ]);
        return $menuContentItems;
    }

    public static function contactUsMenuItem() {
        $menuItems = static::currentMenuItems('main');
        /** @var MenuLinkContent $menuItem */
        foreach($menuItems as $menuItem) {
            $pageTypeItem = $menuItem->field_page_type->first();
            if(!empty($pageTypeItem)) {
                $pageType = $pageTypeItem->getValue();
                if($pageType['value'] == 'contact_us') {
                    return $menuItem;
                }
            }
        }
        return null;
    }

    public function isInMainMenu() {
        if(!$this->homePageExists()) {
            return false;
        }
        $menuItems = static::currentMenuItems('main');
        /** @var MenuLinkContent $menuItem */
        foreach($menuItems as $menuItem) {
            $link = $menuItem->link->first();
            $linkUrl = $link->getUrl();
            try {
                $routeName = $linkUrl->getRouteName();
            }
            catch (\Exception $e) {
                $routeName = null;
            }
            if($routeName == 'entity.node.canonical') {
                $routeParameters = $linkUrl->getRouteParameters();
                $nid = $routeParameters['node'];
                if($nid == $this->currentHomePage->id()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isInUtility() {
        if(!$this->homePageExists()) {
            return false;
        }
        $menuItems = static::currentMenuItems('utility-menu');
        /** @var MenuLinkContent $menuItem */
        foreach($menuItems as $menuItem) {
            $link = $menuItem->link->first();
            $linkUrl = $link->getUrl();
            try {
                $routeName = $linkUrl->getRouteName();
            }
            catch (\Exception $e) {
                $routeName = null;
            }
            if($routeName == 'entity.node.canonical') {
                $routeParameters = $linkUrl->getRouteParameters();
                $nid = $routeParameters['node'];
                if($nid == $this->currentHomePage->id()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function homePageExists() {
        if(!isset($this->currentHomePage)) {
            $this->currentHomePage = sprowt_careers_get_homepage();
        }
        return ($this->currentHomePage instanceof Node);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $templateInfo = $this->careerPageTemplateInfo();
        $pageTypes = $this->pageTypes();
        $homepageExists = $this->homePageExists();
        if($homepageExists) {
            /** @var Node $currentHomePage */
            $currentHomePage = $this->currentHomePage;
            $homepageEditUrl = $currentHomePage->toUrl('edit-form')->toString();
            $message = Markup::create('A careers home page is already set. Go <a href="'.$homepageEditUrl.'" target="_blank">here</a> to edit it.');
            \Drupal::messenger()->addWarning($message);
        }

        $inUtility = $this->isInUtility();
        $inMain = $this->isInMainMenu();

        $contactUs = static::contactUsMenuItem();

        $home = [];

        $home['home_page_alias'] = [
            '#type' => 'textfield',
            '#required' => true,
            '#title' => 'Careers home path',
            '#placeholder' => '/careers',
            '#default_value' => '/careers',
            '#description' => 'A URL alias with a leading slash (/)'
        ];

        $home['home_page_title'] = [
            '#type' => 'textfield',
            '#required' => true,
            '#title' => 'Title'
        ];

        $home['addToUtility'] = [
            '#type' => 'checkbox',
            '#title' => 'Add as a button to utility menu',
            '#attributes' => [
                'class' => ['home-add-to-utility']
            ],
            '#access' => empty($inUtility)
        ];

        $home['utilityMenuTitle'] = [
            '#type' => 'textfield',
            '#title' => 'Button text',
            '#default_value' => 'Careers',
            '#states' => [
                'visible' => [
                    '.home-add-to-utility' => [
                        'checked' => true,
                    ]
                ],
                'required' => [
                    '.home-add-to-utility' => [
                        'checked' => true,
                    ]
                ]
            ],
            '#access' => empty($inUtility)
        ];

        $home['addToMain'] = [
            '#type' => 'checkbox',
            '#title' => 'Add to main menu under "Contact Us"',
            '#attributes' => [
                'class' => ['home-add-to-main']
            ],
            '#access' => empty($inMain) && !empty($contactUs)
        ];

        $home['mainMenuTitle'] = [
            '#type' => 'textfield',
            '#title' => 'Main menu text',
            '#default_value' => 'Careers',
            '#states' => [
                'visible' => [
                    '.home-add-to-main' => [
                        'checked' => true
                    ]
                ],
                'required' => [
                    '.home-add-to-main' => [
                        'checked' => true,
                    ]
                ]
            ],
            '#access' => empty($inMain) && !empty($contactUs)
        ];

        $home['home_page_template'] = [
            '#type' => 'select',
            '#required' => true,
            '#title' => 'Template',
            '#options' => $this->templateOptions('home')
        ];

        $home['home_page_published'] = [
            '#type' => 'checkbox',
            '#title' => 'Published?',
            '#default_value' => true
        ];

        $form['homepageSettingsWrap'] = [
            '#type' => 'fieldset',
            '#title' => 'Careers home page'
        ] + $home;


        if($homepageExists) {
            $form['homepageSettingsWrap']['home_page_title']['#disabled'] = true;
            $form['homepageSettingsWrap']['home_page_title']['#default_value'] = $currentHomePage->label();
            $form['homepageSettingsWrap']['home_page_template']['#access'] = false;
            $form['homepageSettingsWrap']['home_page_template']['#value'] = '<none>';
            $form['homepageSettingsWrap']['home_page_alias']['#access'] = false;
            $form['homepageSettingsWrap']['home_page_alias']['#value'] = '<none>';
            $form['homepageSettingsWrap']['home_page_published']['#access'] = false;
            $form['homepageSettingsWrap']['home_page_published']['#value'] = $currentHomePage->isPublished();
        }
        else {
            $possibleTemplates = array_keys($form['homepageSettingsWrap']['home_page_template']['#options']);
            if(!empty($possibleTemplates)) {
                $form['homepageSettingsWrap']['home_page_template']['#default_value'] = array_shift($possibleTemplates);
            }
        }

        $form['pagesWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => 'pagesWrap'
            ]
        ];

        $template = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['pageItem']
            ]
        ];

        $template['includeInMenu'] = [
            '#type' => 'checkbox',
            '#title' => 'Include in menu',
            '#attributes' => [
                'class' => ['includeInMenu']
            ]
        ];

        $template['menuLinkTitle'] = [
            '#type' => 'textfield',
            '#title' => 'Menu link label',
            '#attributes' => [
                'class' => ['menuLinkTitle']
            ]
        ];

        $weightOptions = [];
        for($i = -50; $i <= 50; ++$i) {
            $weightOptions[$i] = $i;
        }

        $template['menuLinkWeight'] = [
            '#type' => 'select',
            '#title' => 'Menu link weight',
            '#options' => $weightOptions,
            '#attributes' => [
                'class' => ['menuLinkWeight']
            ]
        ];

        $template['nodeTitle'] = [
            '#type' => 'textfield',
            '#title' => 'Page title',
            '#attributes' => [
                'class' => ['nodeTitle'],
            ]
        ];

        $template['nodeTemplate'] = [
            '#type' => 'select',
            '#title' => 'Template',
            '#options' => $this->templateOptions(),
            '#attributes' => [
                'class' => ['nodeTemplate']
            ]
        ];

        $template['nodePublished'] = [
            '#type' => 'checkbox',
            '#title' => 'Published?',
            '#attributes' => [
                'class' => ['nodePublished']
            ]
        ];

        $template['removeItem'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Remove',
            '#attributes' => [
                'type' => 'button',
                'class' => ['action-link','action-link--danger','action-link--icon-trash','removeItem']
            ]
        ];
        $form['itemTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'id' => 'itemTemplate',
                'type' => 'text/html+template',
                'data-template-info' => json_encode($templateInfo),
                'data-page-types' => json_encode($pageTypes)
            ],
            'template' => $template
        ];

        $default = $this->defaultNodes();

        $form['pages'] = [
            '#type' => 'hidden',
            '#default_value' => json_encode($default),
            '#attributes' => [
                'id' => 'pages'
            ]
        ];

        $form['addButton'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Add',
            '#attributes' => [
                'type' => 'button',
                'class' => ['button'],
                'id' => 'addButton'
            ]
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#button_type' => 'primary',
            '#value' => $this->t('Create'),
        ];

        $form['#attributes']['class'] = ['sprowt-careers-installer-form'];

        $form['#attached']['library'][] = 'sprowt_careers/installer';

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if(empty($form['homepageSettingsWrap']['home_page_title']['#disabled'])) {
            $homepageAlias = $form_state->getValue('home_page_alias');
            $pathAlias = PathAlias::create([
                'alias' => $homepageAlias
            ]);
            $violations = $pathAlias->validate();
            foreach ($violations as $violation) {
                if ($violation->getPropertyPath() != 'alias') {
                    continue;
                }
                $form_state->setError($form['homepageSettingsWrap']['home_page_alias'], $violation->getMessage());
            }
            $homePage = [
                'nodeTitle' => $form_state->getValue('home_page_title'),
                'nodeAlias' => $form_state->getValue('home_page_alias'),
                'nodeTemplate' => $form_state->getValue('home_page_template'),
                'nodePublished' => $form_state->getValue('home_page_published') ?? false
            ];
        }


        $pagesJson = $form_state->getValue('pages');
        $pages = json_decode($pagesJson, true);
        if(!empty($homePage)) {
            $pages = array_merge([$homePage], $pages);
        }
        $form_state->set('pages', $pages);
    }


    public function createBatchBuilder(FormStateInterface $formState) {
        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle('Creating careers section');
        $batchBuilder->setInitMessage('Initializing...');

        $menuSettings = [];
        $addToUtility = $formState->getValue('addToUtility');
        $addToMain = $formState->getValue('addToMain');
        if(!empty($addToUtility)) {
            $menuSettings['utilityButton'] = $formState->getValue('utilityMenuTitle');
        }
        if(!empty($addToMain)) {
            $menuSettings['mainMenuTitle'] = $formState->getValue('mainMenuTitle');
        }
        $homepageExists = $this->homePageExists();
        if(empty($homepageExists)) {
            $batchBuilder->addOperation([$this, 'installBlocks']);
        }

        $pages = $formState->get('pages');
        foreach($pages as $page) {
            $batchBuilder->addOperation([$this, 'batchAddPage'], [
                $page
            ]);
        }
        $batchBuilder->addOperation([$this, 'batchAddBackLink']);

        if(empty($homepageExists)) {
            $batchBuilder->addOperation([$this, 'batchUpdateSocialMediaBlocks']);
        }
        if(!empty($menuSettings)) {
            $batchBuilder->addOperation([$this, 'addMenuLinks'], [
                $menuSettings
            ]);
        }
        return $batchBuilder;
    }

    public static function installBlocks(&$context) {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $sandbox['processing'] = true;
        /** @var \Drupal\sprowt_content\SprowtContentService $service */
        $service = \Drupal::service('sprowt_content.service');
        $data = $service->getEntitiesByUuid('block_content', [
            '530a8bc4-a0bc-4089-839b-4e11a1ed8eb6',
            '58db35af-b3c8-43ce-8e14-622eafe84687',
            '5f639cae-d75e-4b52-b0dc-b07f28cb55c3',
            '6a799780-b205-4840-bef4-8b7d17bd752c',
            'e0d15bbf-209d-4419-b9d8-d2b9e851a861'
        ]);
        /** @var \Drupal\content_import_export\Importer $importer */
        $importer = \Drupal::service('content_import_export.importer');
        if(!empty($data) && empty($data['error'])) {
            $entities = $importer->import($data);
        }
        static::installBlockStructure($context);
        $context['message'] = "Added careers blocks";
        $sandbox['processing'] = false;
    }

    public static function installBlockStructure(&$context) {
        $path = \Drupal::service('extension.path.resolver')->getPath('module', 'sprowt_careers');
        $themeName = \Drupal::config('system.theme')->get('default');
        $blocks = [
            'header_layout' => [$path . '/config/block.block.headerlayoutcareers.yml'],
            'footer_layout' => [$path . '/config/block.block.footerlayoutcareers.yml'],
            'header_mobile_menu' => [$path . '/config/block.block.careersmenu.yml'],
            'header1' => [],
            'header2' => [],
            'header3' => [],
            'header4' => [],
            'footer' => [],
            'footer_bottom' => []
        ];

        foreach($blocks as $region => $yamlFiles) {
            $regionBlocks = \Drupal::entityTypeManager()->getStorage('block')->loadByProperties([
                'theme' => $themeName,
                'region' => $region
            ]);
            $blockData = [];
            foreach($yamlFiles as $yamlFile) {
                $dataYaml = file_get_contents($yamlFile);
                $blockDatum = \Drupal\Core\Serialization\Yaml::decode($dataYaml);
                $blockData[$blockDatum['plugin']] = $blockDatum;
            }
            $plugins = array_keys($blockData);


            $blockFound = [];
            /** @var Block $regionBlock */
            foreach($regionBlocks as $regionBlock) {
                $pluginId = $regionBlock->getPluginId();
                if(in_array($pluginId, $plugins)) {
                    $blockFound[] = $pluginId;
                    if(empty($regionBlock->status())) {
                        $regionBlock->setStatus(true);
                        $regionBlock->save();
                    }
                }
                else {
                    if(!empty($regionBlock->status())) {
                        $visibility = $regionBlock->getVisibility() ?? [];
                        if(empty($visibility['entity_bundle:node'])) {
                            $regionBlock->setVisibilityConfig('entity_bundle:node', [
                                'negate' => true,
                                'context_mapping' => [
                                    'node' => '@node.node_route_context:node'
                                ],
                                'bundles' => [
                                    'career_page' => 'career_page',
                                    'job_post' => 'job_post'
                                ]
                            ]);
                            $regionBlock->save();
                        }
                    }
                }
            }
            foreach($blockData as $blockPlugin => $data) {
                if(!in_array($blockPlugin, $blockFound)) {
                    $newBlock = Block::create([
                        'id' => $themeName . '_' . $data['id'],
                        'theme' => $themeName,
                        'region' => $region,
                        'plugin' => $data['plugin'],
                        'status' => true,
                        'dependencies' => $data['dependencies'],
                        'settings' => $data['settings'],
                        'visibility' => $data['visibility'],
                        'weight' => $data['weight'] ?? 0
                    ]);

                    $newBlock->save();
                }
            }
        }
    }

    public static function addMenuLinks($menuSettings, &$context) {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $sandbox['processing'] = true;
        $utilityButtonText = $menuSettings['utilityButton'] ?? null;
        $mainMenuTitle = $menuSettings['mainMenuTitle'] ?? null;
        $home = sprowt_careers_get_homepage();
        if(!empty($utilityButtonText)) {
            $menuLinkContentItem = MenuLinkContent::create([
                'menu_name' => 'utility-menu',
                'title' => $utilityButtonText
            ]);
            $menuLinkContentItem->set('link', [
                'uri' => 'entity:node/' . $home->id(),
            ]);
            $menuLinkContentItem->set('field_list_item_classes', [
                'value' => 'careers'
            ]);
            $menuLinkContentItem->set('weight', [
                'value' => '-1'
            ]);
            $menuLinkContentItem->save();
            $context['message'] = "Added utility menu link";
        }
        if(!empty($mainMenuTitle)) {
            $contactUsMenuItem = static::contactUsMenuItem();
            if($contactUsMenuItem instanceof MenuLinkContent) {
                $menuLinkContentItem = MenuLinkContent::create([
                    'menu_name' => 'utility-menu',
                    'title' => $mainMenuTitle
                ]);
                $menuLinkContentItem->set('link', [
                    'uri' => 'entity:node/' . $home->id(),
                ]);
                $menuLinkContentItem->set('weight', [
                    'value' => '5'
                ]);
                $menuLinkContentItem->set('parent', [
                    'value' => 'menu_link_content:' . $contactUsMenuItem->uuid()
                ]);
                $menuLinkContentItem->save();
                $context['message'] = "Added main menu link";
            }
        }
        $sandbox['processing'] = false;
    }

    public static function batchAddPage($page, &$context) {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $sandbox['processing'] = true;
        $context['message'] = "Installing node, \"{$page['nodeTitle']}\"";
        /** @var SprowtContentService $service */
        $service = \Drupal::service('sprowt_content.service');
        $values = [
            'title' => $page['nodeTitle'],
            'path' => [
                'pathauto' => true
            ]
        ];
        if(!empty($page['nodeAlias'])) {
            $values['path'] = [
                'pathauto' => false,
                'alias' => $page['nodeAlias']
            ];
        }
        /** @var Node $node */
        $node = $service->cloneImportNode($page['nodeTemplate'], $values);
        $node->setUnpublished();
        if(!empty($page['nodePublished'])) {
            $node->setPublished();
        }
        $node->save();
        $nodeUrl = $node->toUrl();
        $pageType = $node->field_career_page_type->value;
        if(!empty($page['includeInMenu'])) {
            $menuLink = MenuLinkContent::create([
                'title' => $page['menuLinkTitle'],
                'menu_name' => 'careers-menu',
                'link' => [
                    'uri' => 'entity:node/' . $node->id()
                ],
                'weight' => $page['menuLinkWeight'] ?? 0
            ]);
            $menuLink->save();
        }
        if($pageType == 'home') {
            static::setCustomBrandingLink($node);
        }

        $context['message'] = "Installed node, \"{$page['nodeTitle']}\"";
        if(empty($context['results'])) {
            $context['results'] = [];
        }
        if(empty($context['results']['nodesAdded'])) {
            $context['results']['nodesAdded'] = [];
        }
        $context['results']['nodesAdded'][] = [
            'uuid' => $node->uuid(),
            'label' => $node->label(),
            'url' => $nodeUrl->toString(),
            'id' => $node->id(),
            'templateUuid' => $page['nodeTemplate'],
            'pageType' => $node->field_career_page_type->value,
            'published' => $node->isPublished()
        ];
        $sandbox['processing'] = false;
    }

    /**
     * @param Node $homeNode
     * @return void
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public static function setCustomBrandingLink($homeNode) {
        $blockStorage = \Drupal::entityTypeManager()->getStorage('block_content');
        $uuids = [
            '530a8bc4-a0bc-4089-839b-4e11a1ed8eb6',
            '58db35af-b3c8-43ce-8e14-622eafe84687'
        ];
        $blocks = $blockStorage->loadByProperties([
            'uuid' => $uuids
        ]);

        $linkValue = [
            'uri' => 'entity:node/' . $homeNode->id()
        ];
        /** @var BlockContent $block */
        foreach($blocks as $block) {
            /** @var LayoutSectionItemList $layout */
            $layout = $block->get(OverridesSectionStorage::FIELD_NAME);
            $sections = $layout->getSections();
            /** @var Section $section */
            foreach($sections as $section) {
                $components = $section->getComponents();
                /** @var SectionComponent $component */
                foreach($components as $component) {
                    $config = $component->get('configuration');
                    if($config['id'] == 'inline_block:custom_branding_block') {
                        if(!empty($config['block_revision_id'])) {
                            $brandingBlock = $blockStorage->loadRevision($config['block_revision_id']);
                        }
                        elseif (!empty($config['uuid'])) {
                            $brandingBlock = $blockStorage->loadByProperties([
                                'uuid' => $config['uuid']
                            ]);
                            if(!empty($brandingBlock)) {
                                $brandingBlock = array_shift($brandingBlock);
                            }
                        }

                        if(!empty($brandingBlock)) {
                            $brandingBlock->set('field_image_link', $linkValue);
                            $brandingBlock->save();
                        }
                    }
                }
            }
        }
    }

    public static function batchAddBackLink(&$context) {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $sandbox['processing'] = true;
        $utilityLinks = \Drupal::entityTypeManager()->getStorage('menu_link_content')
            ->loadByProperties([
                'menu_name' => 'careers-utility'
            ]);
        if(empty($utilityLinks)) {
            $context['message'] = "Adding back link";
            $menuLink = MenuLinkContent::create([
                'title' => 'Back To Main Site',
                'menu_name' => 'careers-utility',
                'link' => [
                    'uri' => 'internal:/'
                ]
            ]);
            $menuLink->save();
            $context['message'] = "Added back link";
        }
        $sandbox['processing'] = false;
    }

    public static function batchUpdateSocialMediaBlocks(&$context) {
        $sandbox = &$context['sandbox'];
        if(!empty($sandbox['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $sandbox['processing'] = true;
        $context['message'] = "Updating social media links";
        $blockStorage = \Drupal::entityTypeManager()->getStorage('block_content');
        /** @var NodeStorage $nodeStorage */
        $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
        $globalSocialMediaBlock = $blockStorage->loadByProperties([
            'uuid' => '7e5f53fa-561e-4ef8-9ba7-778f95cb1cce'
        ]);
        $globalSocialMediaBlock = array_shift($globalSocialMediaBlock);
        /** @var EntityReferenceRevisionsFieldItemList $globalItemList */
        $globalItemList = $globalSocialMediaBlock->get('field_social_media');
        $block = $blockStorage->loadByProperties([
            'uuid' => '470fa0e6-0f04-4057-be5a-519c9b587ffa'
        ]);
        if(!empty($block)) {
            /** @var BlockContent $block */
            $block = array_shift($block);
            /** @var EntityReferenceRevisionsFieldItemList $socialMediaItemList */
            $socialMediaItemList = $block->get('field_social_media');
            $oldParagraphs = $socialMediaItemList->referencedEntities();
            foreach($oldParagraphs as $oldParagraph) {
                $oldParagraph->delete();
            }
            $fieldValue = [];
            $globalParagraphs = $globalItemList->referencedEntities();
            /** @var Paragraph $globalParagraph */
            foreach($globalParagraphs as $globalParagraph) {
                $newParagraph = $globalParagraph->createDuplicate();
                $newParagraph->save();
                $fieldValue[] = [
                    'target_id' => $newParagraph->id(),
                    'target_revision_id' => $newParagraph->getRevisionId()
                ];
            }
            $block->set('field_social_media', $fieldValue);
            $block->save();
            $context['message'] = "Updated social media links";
        }
        $sandbox['processing'] = false;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $batchBuilder = $this->createBatchBuilder($form_state);
        $batchArray = $batchBuilder->toArray();
        \Drupal::state()->set('sprowt_careers.batch', $batchArray);
        $form_state->setRedirectUrl(Url::fromRoute('sprowt_careers.installer_confirm'));
    }

}
