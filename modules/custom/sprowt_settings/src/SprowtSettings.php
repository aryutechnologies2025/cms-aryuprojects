<?php

namespace Drupal\sprowt_settings;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\sprowt_settings\TranslatableTrait;
use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Drupal\sprowt_settings\Form\SprowtSettingsForm;
use Drupal\system\Plugin\Condition\RequestPath;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Plugin\views\wizard\TaxonomyTerm;
use Drupal\taxonomy\TermStorage;
use Drupal\taxonomy\TermStorageInterface;

class SprowtSettings implements TrustedCallbackInterface
{
    use StateTrait;
    use TranslatableTrait;
    use MessengerTrait;

    public static $configName = 'sprowt_settings.settings';

    public static $stateKeys = [
        'listsOnlyCityPages' => 'sprowt_settings.listsOnlyCityPages',
        'cityPageMetaTagStore' => 'sprowt_settings.cityPageMetaTagStore'
    ];

    /**
     * @var Connection
     */
    protected $database;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * @var StateInterface
     */
    protected $state;

    /**
     * @var FileSystemInterface
     */
    protected $fileSystem;

    protected $config;

    public $fileDirectory = 'public://sprowt';

    protected $fileSettings = [

    ];

    protected $imageSettings = [
        'special_offer_logo' => [
            'title' => 'Special Offer Logo',
            'description' => 'Logo used specifically for special offers.'
        ],
        'special_offer_logo_reverse' => [
            'title' => 'Special Offer Logo Reverse',
            'description' => 'Logo used specifically for special offers, but for a reverse color scheme.'
        ]
    ];

    public function __construct(
        Connection $database,
        EntityTypeManagerInterface $entityTypeManager,
        ConfigFactoryInterface $configFactory,
        StateInterface $state,
        FileSystemInterface $fileSystem
    ) {
        $this->database = $database;
        $this->entityTypeManager = $entityTypeManager;
        $this->configFactory = $configFactory;
        $this->config = $configFactory->getEditable(static::$configName);
        $this->state = $state;
        $this->fileSystem = $fileSystem;
    }

    public function imageSettingsKeys() {
        return array_keys($this->imageSettings);
    }

    public static function trustedCallbacks()
    {
        return ['fieldPostRender'];
    }

    public function getFileSettings() {
        return array_merge($this->fileSettings, $this->imageSettingsKeys());
    }

    public static function loadBlockByUuid($uuid) {
        $storage = \Drupal::entityTypeManager()->getStorage('block_content');
        $blocks = $storage->loadByProperties([
            'uuid' => $uuid
        ]);
        if(empty($blocks) || !count($blocks)) {
            return null;
        }
        return array_shift($blocks);
    }

    public function getSetting($key, $default = null, $alterContext = []) {
        if($key == 'webform_mail' || $key == 'webform_email') {
            $key = 'webform_from_email';
        }
        $alterContext['default'] = $default;
        if($key == 'years_in_business') {
            $yearStarted = $this->getSetting('year_started');
            if(!empty($yearStarted)) {
                $now = new \DateTime();
                $years = $now->format('Y') - $yearStarted;
                return $years;
            }
            return $default;
        }
        if($key == 'state_name') {
            $abbr = $this->config->get('state') ?? null;
            return static::getStateName($abbr) ?? $default;
        }
        if($key == 'company_type') {
            /** @var TermStorageInterface $storage */
            $storage = $this->entityTypeManager->getStorage('taxonomy_term');
            $terms = $storage->loadByProperties([
                'field_is_company_industry' => '1'
            ]);
            return $terms ?? [];
        }
        if($key == 'main_branch') {
            /** @var NodeStorageInterface $storage */
            $storage = $this->entityTypeManager->getStorage('node');
            $nodes = $storage->loadByProperties([
                'field_is_main_branch' => '1'
            ]);
            return is_array($nodes) ? array_pop($nodes) : null;
        }
        if(in_array($key, $this->getFileSettings())) {
            $uuid = $this->config->get($key);
            if(empty($uuid)) {
                return $default;
            }
            $file = $this->entityTypeManager->getStorage('file')->loadByProperties([
                $this->entityTypeManager->getDefinition('file')->getKey('uuid') => $uuid
            ]);
            $ret = is_array($file) ? array_pop($file) : $file;
            if($ret instanceof File) {
                return $ret;
            }
            return $default;
        }

        $defaultValues = SprowtSettingsForm::defaultSettings();
        if(in_array($key, array_keys($defaultValues))) {
            if(!isset($default) && isset($defaultValues[$key])) {
                $default = $defaultValues[$key];
            }
            $value = $this->config->get($key);
            return $value ?? $default;
        }

        $value = $this->config->get($key);
        if(!isset($value)) {
            $ctm = $this->config->get('ctm') ?? [];
            if(!empty($ctm[$key])) {
                return $ctm[$key];
            }
            if($this->isConditionalToken($key)) {
                return $this->returnConditionalToken($key);
            }
            $custom = $this->config->get('custom') ?? [];
            return $custom[$key] ?? $default;
        }

        \Drupal::moduleHandler()->alter('sprowt_get_setting', $value, $key, $alterContext);

        return $value;
    }

    public function returnConditionalToken($key, $default = null)
    {
        if(!$this->isConditionalToken($key)) {
            return $default;
        }
        $tokens = $this->getConditionalTokens([$key]);
        $tokenInfo = $tokens[$key] ?? [];
        if(empty($tokenInfo['value'])) {
            return $default;
        }
        $visibility = $tokenInfo['visibility'] ?? [];
        if($this->isVisible($visibility)) {
            return $tokenInfo['value'] ?? $default;
        }

        return $default;
    }

    public function isVisible($visibility, $node = null)
    {
        $show = true;
        /** @var ContextRepositoryInterface $contextRepo */
        $contextRepo = \Drupal::service('context.repository');
        $contextHandler = \Drupal::service('context.handler');
        $conditions = [];
        $manager = \Drupal::service('plugin.manager.condition');
        foreach ($visibility as $visibilityValue) {
            /** @var \Drupal\Core\Condition\ConditionInterface $condition */
            $condition = $manager->createInstance($visibilityValue['id'], $visibilityValue ?? []);
            if($condition instanceof ConditionInterface) {
                $conditions[] = $condition;
            }
        }
        /** @var ConditionInterface $condition */
        foreach ($conditions as $condition) {
            $missing_value = false;
            $missing_context = false;
            if($condition instanceof ContextAwarePluginInterface) {
                try {
                    $contexts = $contextRepo->getRuntimeContexts(array_values($condition->getContextMapping()));
                    $contextHandler->applyContextMapping($condition, $contexts);
                }
                catch (MissingValueContextException $e) {
                    $missing_value = true;
                }
                catch (ContextException $e) {
                    $missing_context = true;
                }
            }
            if($node instanceof Node && $condition instanceof ConditionPluginBase) {
                //apply provided node as a context
                $nodeContext = EntityContext::fromEntity($node, 'entity:node');
                $condition->setContext('node', $nodeContext);
            }
            if($condition instanceof RequestPath) {
                $config = $condition->getConfig();
                if(empty($config['pages']) && !empty($config['negate'])) {
                    //empty pages and negate (hide for listed) not working working correctly for some reason.
                    //so set pages to some random value
                    $config['pages'] = '/' . sha1(time() . rand() . 'show all pages');
                    $condition->setConfiguration($config);
                }
            }
            try {
                $show = $show && $condition->execute();
            }
            catch (ContextException $e) {
                // If a condition is missing context and is not negated, consider that a
                // fail.
                $show = $condition->isNegated();
            }
            catch (\Exception $e) {
                \Drupal::logger('sprowt_settings')->error("Visibility condition error: " . $e->getMessage() . ' @backtrace_string', [
                    '@backtrace_string' => $e->getTraceAsString()
                ]);
                $show = $condition->isNegated();
            }
        }

        return $show;
    }

    public function isConditionalToken($key)
    {
        $conditionalTokens = $this->getConditionalTokens();
        return isset($conditionalTokens[$key]);
    }

    public function getConditionalTokens($keys = []) {
        $allTokens = $this->config->get('conditional_tokens') ?? [];
        if(!empty($keys) && !empty($allTokens)) {
            $allTokens = array_filter($allTokens, function ($key) use ($keys) {
                return in_array($key, $keys);
            }, ARRAY_FILTER_USE_KEY);
        }
        return $allTokens;
    }

    public function setConditionalToken($key, $value, $visibility = []) {
        $conditionalTokens = $this->config->get('conditional_tokens') ?? [];
        $conditionalTokens[$key] = [
            'key' => $key,
            'value' => $value,
            'visibility' => $visibility
        ];
        $this->config->set('conditional_tokens', $conditionalTokens);
        $this->config->save();
    }

    public function deleteConditionalToken($key) {
        $conditionalTokens = $this->config->get('conditional_tokens') ?? [];
        if(isset($conditionalTokens[$key])) {
            unset($conditionalTokens[$key]);
            $this->config->set('conditional_tokens', $conditionalTokens);
            $this->config->save();
        }
    }

    protected function setCompanyType($uuids) {
        if(!is_array($uuids)) {
            $uuids = [$uuids];
        }
        $selectedUuids = [];
        foreach($uuids as $uuid) {
            if($uuid instanceof TaxonomyTerm) {
                $selectedUuids[] = $uuid->uuid();
            }
            else {
                $selectedUuids[] = $uuid;
            }
        }


        /** @var TermStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $industries = $storage->loadTree('industry', 0, 1, true);
        $return = [];
        /** @var Term $industryTerm */
        foreach($industries as $industryTerm) {
            $uuid = $industryTerm->uuid();
            $isCompanyIndustry = $industryTerm->field_is_company_industry->value;
            if(!empty($isCompanyIndustry) && $isCompanyIndustry !== '0' && !in_array($uuid, $selectedUuids)) {
                $industryTerm->set('field_is_company_industry', [
                    'value' => '0'
                ]);
                $industryTerm->save();
            }
            if(empty($isCompanyIndustry) || $isCompanyIndustry === '0') {
                if(in_array($uuid, $selectedUuids)) {
                    $industryTerm->set('field_is_company_industry', [
                        'value' => '1'
                    ]);
                    $industryTerm->save();
                }
            }
            if(in_array($uuid, $selectedUuids)) {
                $return[] = $industryTerm;
            }
        }
        return $return;
    }

    protected function setMainBranch($uuid) {
        if($uuid instanceof Node) {
            $uuid = $uuid->uuid();
        }

        /** @var NodeStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('node');
        $nodes = $storage->loadByProperties([
            'type' => 'branch'
        ]);
        /** @var Node $node */
        foreach($nodes as $node) {
            $isMainBranch = $node->field_is_main_branch->value;
            if(!empty($isMainBranch) && $isMainBranch !== '0') {
                $node->set('field_is_main_branch', [
                    'value' => '0'
                ]);
                $node->save();
            }
            if($uuid == $node->uuid()) {
                $node->set('field_is_main_branch', [
                    'value' => '1'
                ]);
                $node->save();
            }
        }
        return $uuid;
    }

    public function setSetting($key, $value) {
        if($key == 'company_type') {
            return $this->setCompanyType($value);
        }
        if($key == 'main_branch') {
            return $this->setMainBranch($value);
        }
        if($key == 'state_name') {
            $state = static::getStateAbbreviation($value);
            $this->config->set('state', $state);
            return $this->config->save();
        }

        if(in_array($key, $this->getFileSettings())) {
            $this->fileSystem->prepareDirectory($this->fileDirectory, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
            $currentFile = $this->getSetting($key);
            if(empty($value)) {
                if($currentFile instanceof File) {
                    $currentFile->delete();
                }
                $this->config->set($key, null);
                return $this->config->save();
            }
            if(!$value instanceof File) {
                throw new SprowtException('Value must be a File or null');
            }
            if(strpos($value->getFileUri(), $this->fileDirectory) !== 0) {
                /** @var \Drupal\file\FileRepositoryInterface $file_repository */
                $file_repository = \Drupal::service('file.repository');
                $value = $file_repository->move($value, $this->fileDirectory . '/' . $value->getFilename(), FileSystemInterface::EXISTS_REPLACE);
            }
            if(!$value->isPermanent()) {
                $value->setPermanent();
                $value->save();
            }
            if($currentFile instanceof File) {
                if($value->uuid() != $currentFile->uuid()) {
                    $currentFile->delete();
                }
            }
            $this->config->set($key, $value->uuid());
            return $this->config->save();
        }
        if($key == 'custom' && !is_array($value)) {
            throw new SprowtException('Setting value is invalid!');
        }
        if($key == 'ctm') {
            if(!is_array($value)) {
                throw new SprowtException('Setting value is invalid!');
            }
            $this->config->set($key, $value);
            return $this->config->save();
        }

        $possibleKeys = array_keys(SprowtSettingsForm::defaultSettings());
        $possibleKeys[] = 'custom';
        if(in_array($key, $possibleKeys)) {
            $this->config->set($key, $value);
            return $this->config->save();
        }
        $custom = $this->config->get('custom') ?? [];
        $custom[$key] = $value;
        $this->config->set('custom', $custom);
        return $this->config->save();
    }

    public function setSettings($settings) {
        foreach($settings as $key => $val) {
            $this->config->set($key, $val);
        }
        $this->config->save();
    }

    public function getIndustry() {
        /** @var TermStorage $storage */
        $storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $entity = $storage->loadByProperties([
            'field_is_company_industry' => '1'
        ]);
        $entity = is_array($entity) ? array_pop($entity) : $entity;

        if($entity instanceof Term) {
            return Markup::create($entity->getName());
        }
        return '';
    }

    public function getPhone($key = null, $replace = null) {
        if(empty($key)) {
            $key = 'company_phone';
        }
        $phone = $this->getSetting($key);
        if(empty($phone)) {
            return null;
        }

        return $this->formatPhone($phone, $replace);
    }

    public function formatPhone($phone, $replace = null) {
        if(empty($phone)) {
            return null;
        }
        if(empty($replace)) {
            $replace = $this->getSetting('phone_replace', '$1-$2-$3');
        }
        return preg_replace("/.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*/", $replace, $phone);
    }

    public function getMetaTagsValue($entity, $fieldName)
    {
        $tags = [];
        foreach ($entity->{$fieldName} as $item) {
            // Get serialized value and break it into an array of tags with values.
            $serialized_value = $item->get('value')->getValue();
            if (!empty($serialized_value)) {
                $unserialized = metatag_data_decode($serialized_value);
                if(is_array($unserialized)) {
                    $tags = $tags + $unserialized;
                }
            }
        }

        return $tags;
    }

    public function setCityPageAsListsOnly(Node $node) {
        $uuid = $node->uuid();
        $listOnlyLists = $this->state->get(static::$stateKeys['listsOnlyCityPages'], []);
        if(!in_array($uuid, $listOnlyLists)) {
            $listOnlyLists[] = $uuid;
        }
        $this->state->set(static::$stateKeys['listsOnlyCityPages'], $listOnlyLists);
    }

    public function unsetCityPageAsListsOnly(Node $node) {
        $uuid = $node->uuid();
        $listOnlyLists = $this->state->get(static::$stateKeys['listsOnlyCityPages'], []);
        if(in_array($uuid, $listOnlyLists)) {
            $key = array_search($uuid, $listOnlyLists);
            unset($listOnlyLists[$key]);
            $listOnlyLists = array_values($listOnlyLists);
        }
        $this->state->set(static::$stateKeys['listsOnlyCityPages'], $listOnlyLists);
    }

    public function cityPageIsListsOnly(Node $node) {
        $uuid = $node->uuid();
        $listOnlyLists = $this->state->get(static::$stateKeys['listsOnlyCityPages'], []);
        return in_array($uuid, $listOnlyLists);
    }

    public function getCityPageCachedMetaTags(Node $node) {
        $uuid = $node->uuid();
        $cache = $this->state->get(static::$stateKeys['cityPageMetaTagStore'], []);
        return $cache[$uuid] ?? [];
    }

    public function setCityPageMetTagCache(Node $node, $delete = false) {
        $uuid = $node->uuid();
        $cache = $this->state->get(static::$stateKeys['cityPageMetaTagStore'], []);
        if($delete) {
            if(!empty($cache[$uuid])) {
                unset($cache[$uuid]);
                $this->state->set(static::$stateKeys['cityPageMetaTagStore'], $cache);
            }
        }
        else {
            $metaTags = $this->getMetaTagsValue($node, 'field_meta_tags');
            $cache[$uuid] = $metaTags;
            $this->state->set(static::$stateKeys['cityPageMetaTagStore'], $cache);
        }
        return $cache[$uuid] ?? [];
    }

    public function setChangedListsOnlyCache($nid, $delete = false) {
        $changed = \Drupal::state()->get('sprowt_settings.city_page_list_only_changed_cache', []);
        if($delete) {
            if (in_array($nid, $changed)) {
                $key = array_search($nid, $changed);
                unset($changed[$key]);
                \Drupal::state()->set('sprowt_settings.city_page_list_only_changed_cache', array_values($changed));
            }
        }
        else {
            if (!in_array($nid, $changed)) {
                $changed[] = $nid;
                \Drupal::state()->set('sprowt_settings.city_page_list_only_changed_cache', $changed);
            }
        }
    }

    public function isListOnlyFromCache($nid) {
        $changed = \Drupal::state()->get('sprowt_settings.city_page_list_only_changed_cache', []);
        return in_array($nid, $changed);
    }

    public function cityPageListOnlyPreSave(Node $node, $original = null)
    {
        if ($node->bundle() != 'city_page') {
            return $node;
        }
        $isNew = empty($original);
        $listsOnly = $node->get('field_used_for_lists_only')->value == "1";
        if($isNew) {
            $changed = true;
        }
        else {
            $originalVal = $original->get('field_used_for_lists_only')->value == "1";
            $changed = $listsOnly != $originalVal;
        }
        if(!$changed) {
            return $node;
        }
        $this->setChangedListsOnlyCache($node->id());
        $metaTags = $this->getMetaTagsValue($node, 'field_meta_tags');
        if($listsOnly) {
            if(!$this->cityPageIsListsOnly($node)) {
                $this->setCityPageMetTagCache($node);
            }
            if ($isNew) {
                $metaTags['robots'] = 'noindex';
                $node->set('field_meta_tags', serialize($metaTags));
                $this->setCityPageAsListsOnly($node);
                return $node;
            }

            if (empty($metaTags['robots']) || $metaTags['robots'] != 'noindex') {
                $metaTags['robots'] = 'noindex';
                $node->set('field_meta_tags', serialize($metaTags));
                $this->setCityPageAsListsOnly($node);
                return $node;
            }
        }
        else {
            if($this->cityPageIsListsOnly($node)) {
                $metaTagCache = $this->getCityPageCachedMetaTags($node);
                if (!empty($metaTags['robots']) && $metaTags['robots'] == 'noindex') {
                    if(!empty($metaTagCache['robots'])) {
                        $metaTags['robots'] = $metaTagCache['robots'];
                    }
                    else {
                        unset($metaTags['robots']);
                    }
                    $node->set('field_meta_tags', serialize($metaTags));
                    $this->unsetCityPageAsListsOnly($node);
                    $this->setCityPageMetTagCache($node, true);
                    return $node;
                }
            }
        }

        return $node;
    }

    public function cityPageListOnlyPostSave(Node $node, $original = null)
    {
        if ($node->bundle() != 'city_page') {
            return $node;
        }
        if(!$this->isListOnlyFromCache($node->id())) {
            return $node;
        }
        /** @var \Drupal\simple_sitemap\Manager\Generator $simpleSiteMap */
        $simpleSiteMap = \Drupal::service('simple_sitemap.generator');
        $sitemapSettings = $simpleSiteMap->setSitemaps()->entityManager()->getEntityInstanceSettings(
            'node',
            $node->id()
        );
        $listsOnly = $this->cityPageIsListsOnly($node);
        if($listsOnly
            && (empty($sitemapSettings) || $sitemapSettings['index'] == true)
        ) {
            $sitemapSettings['index'] = false;
            $simpleSiteMap->setSitemaps()->entityManager()->setEntityInstanceSettings(
                'node',
                $node->id(),
                $sitemapSettings
            );
            $simpleSiteMap->setSitemaps()
                ->rebuildQueue()
                ->generate();
        }
        if(!$listsOnly
            && (!empty($sitemapSettings) && $sitemapSettings['index'] != true)
        ) {
            $sitemapSettings['index'] = true;
            $simpleSiteMap->setSitemaps()->entityManager()->setEntityInstanceSettings(
                'node',
                $node->id(),
                $sitemapSettings
            );
            $simpleSiteMap->setSitemaps()
                ->rebuildQueue()
                ->generate();
        }
        $this->setChangedListsOnlyCache($node->id(), true);

        return $node;
    }

    public function tokenInfo() {
        $fields = SprowtSettingsForm::$fields;
        $fieldsFlat = [];
        foreach ($fields as $cat => $catFields) {
            $fieldsFlat = array_merge($fieldsFlat, $catFields);
        }
        $defaultInfo = function($key) use ($fieldsFlat) {
            return $fieldsFlat[$key] ?? null;
        };

        $settingsArray = SprowtSettingsForm::defaultSettings();
        $tokens = [];
        foreach($settingsArray as $key => $defaultValue) {
            if($key != 'custom') {
                $element = $defaultInfo($key) ?? [
                    'title' => $key,
                    'description' => ''
                ];
                if(!empty($element)) {
                    $tokens[$key] = [
                        'name' => t($element['title'])
                    ];
                    if (!empty($element['description'])) {
                        $tokens[$key]['description'] = t($element['description']);
                    }
                }
            }
            if($key == 'state') {
                $tokens[$key]['name'] = t('State Abbreviation');
                $tokens[$key]['description'] = t('The two letter state abbreviation');
                $tokens['state_name'] = [
                    'name' => t('State Full Name'),
                    'description' => t('The full state name')
                ];
            }
            if($key == 'year_started') {
                $tokens['years_in_business'] = [
                    'name' => t('Years In Business'),
                    'description' => t('The years the company has been in business (rounded up)')
                ];
            }
        }

        $tokens['company_type'] = [
            'name' => t('Company Type'),
            'description' => t('The type(s) of services this Sprowt company provides')
        ];


        $tokens['main_branch:?'] = [
            'name' => 'Main Location',
            'description' => 'Replace the "?" with a desired view mode (defaults to teaser).'
        ];

        foreach($this->imageSettings as $key => $def) {

            $tokens[$key] = [
                'name' => t($def['title']),
                'description' => t('Original image relative url')
            ];

            $tokens[$key . ':?'] = [
                'name' => t($def['title'] . ' -- Image Style'),
                'description' => t('Relative image url using a style (replace ? with the style machine name)')
            ];

            $tokens[$key . ':absolute'] = [
                'name' => t($def['title'] . '-- Absolute'),
                'description' => t('Original image absolute url')
            ];

            $tokens[$key . ':img'] = [
                'name' => t($def['title'] . '-- Image HTML'),
                'description' => t('Original image as html')
            ];

            $tokens[$key . ':absolute:?'] = [
                'name' => t($def['title'] . ' -- Absolute -- Image Style'),
                'description' => t('Absolute image url using a style (replace ? with the style machine name)')
            ];

            $tokens[$key . ':img:?'] = [
                'name' => t($def['title'] . ' -- Image HTML -- Image Style'),
                'description' => t('Absolute image as HTML using a style (replace ? with the style machine name)')
            ];
        }

        $tokens['phone_button:default'] = [
            'name' => t('Phone Button: Company Phone'),
            'description' => t('Phone number button using the company phone.')
        ];


        $tokens['phone_button:default:no-swap'] = [
            'name' => t('Phone Button: Company Phone -- no swap'),
            'description' => t('Phone number button without ctm swapping for both the tel link and the inner text.')
        ];

        $tokens['phone_button:default:no-swap-inner'] = [
            'name' => t('Phone Button: Company Phone -- no swap inner text'),
            'description' => t('Phone number button without ctm swapping only the tel link. Not the inner text.')
        ];

        $tokens['phone_button:default:link-only'] = [
            'name' => t('Phone Button: Company Phone -- link only'),
            'description' => t('Phone number link (not a button).')
        ];

        $tokens['phone_button:default:link-only-no-swap'] = [
            'name' => t('Phone Button: Company Phone -- link only no swap'),
            'description' => t('Phone number link (not a button) without ctm swapping.')
        ];

        $tokens['phone_button:default:link-only-no-swap-inner'] = [
            'name' => t('Phone Button: Company Phone -- link only no swap inner text'),
            'description' => t('Phone number link (not a button) without ctm swapping for both the tel link and the inner text.')
        ];

        $ctm = $this->getSetting('ctm', []);
        if(!empty($ctm)) {
            foreach($ctm as $machine_name => $buttonDef) {
                $tokens['phone_button:' . $machine_name] = [
                    'name' => t('Phone Button: ' . $machine_name),
                    'description' => t('Phone number button')
                ];

                $tokens['phone_button:' . $machine_name . ':no-swap'] = [
                    'name' => t('Phone Button: ' . $machine_name . ' -- no swap'),
                    'description' => t('Phone number button without ctm swapping for both the tel link and the inner text.')
                ];

                $tokens['phone_button:' . $machine_name . ':no-swap-inner'] = [
                    'name' => t('Phone Button: ' . $machine_name . ' -- no swap inner text'),
                    'description' => t('Phone number button without ctm swapping only the tel link. Not the inner text.')
                ];

                $tokens['phone_button:' . $machine_name . ':link-only'] = [
                    'name' => t('Phone Button: ' . $machine_name . ' -- link only'),
                    'description' => t('Phone number link (not a button).')
                ];

                $tokens['phone_button:' . $machine_name . ':link-only-no-swap'] = [
                    'name' => t('Phone Button: ' . $machine_name . ' -- link only no swap'),
                    'description' => t('Phone number link (not a button) without ctm swapping.')
                ];

                $tokens['phone_button:' . $machine_name . ':link-only-no-swap-inner'] = [
                    'name' => t('Phone Button: ' . $machine_name . ' -- link only no swap inner text'),
                    'description' => t('Phone number link (not a button) without ctm swapping for both the tel link and the inner text.')
                ];
            }
        }

        $custom = $this->getSetting('custom', []);
        foreach($custom as $key => $value) {
            $tokens[$key] = [
                'name' => t('Sprowt custom token: "' . $key . '"'),
                'description' => $value
            ];
        }

        $conditionalTokens = $this->getConditionalTokens();
        foreach($conditionalTokens as $key => $def) {
            $tokens[$key] = [
                'name' => t('Sprowt conditional token: "' . $key . '"'),
                'description' => t($def['value'])
            ];
        }

        $tokens['main_nav_url:?'] = [
            'name' => 'Relative URL for basic page in the main nav',
            'description' => 'Replace the "?" with the value for page type (home, services, etc)'
        ];

        return $tokens;
    }

    public function tokenReplace($tokens, array $data, array $options, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata) {
        $replacements = [];
        foreach($tokens as $name => $original) {
            \Drupal::service('module_handler')->alter('sprowt_token_name', $name);
            $arguments = [];
            if(strpos($name, ':') !== false) {
                $arguments = explode(':', $name);
                $name = array_shift($arguments);
                $arguments = array_filter(array_values($arguments));
                if(!empty($arguments)) {
                    $arguments = array_map(function($arg) {
                        return trim($arg);
                    }, $arguments);
                }
            }
            if($name == 'skip') {
                continue;
            }
            if($name == 'aggregate_reviews') {
                continue;
            }
            if($name == 'company_phone' || $name == 'contact_phone') {
                if(empty($arguments)) {
                    $arguments[] = 'phone';
                }
            }

            $replacement = $this->getSetting($name);
            if($name == 'phone_button') {
                $machine = array_shift($arguments);
                $modifier = null;
                if(!empty($arguments)) {
                    $modifier = array_shift($arguments);
                }
                if($machine == 'default') {
                    $def = [
                        'phone' => 'company_phone',
                        'value' => 'number_only'
                    ];
                }
                else {
                    $def = $this->getSetting($machine);
                }
                if(!empty($def)) {
                    if($def['phone'] == 'company_phone') {
                        $phone = $this->getSetting('company_phone');
                    }
                    else if($def['phone'] == 'contact_phone') {
                        $phone = $this->getSetting('contact_phone');
                    }
                    else {
                        $phone = $def['phone'];
                    }
                    if($def['value'] == 'number_only') {
                        $value = $this->formatPhone($phone);
                    }
                    else {
                        $value = $def['value'];
                    }
                }
                if(strpos($value, '[') !== false) {
                    $value = \Drupal::token()->replace($value);
                }
                if(!empty($value) && !empty($phone)) {
                    switch ($modifier) {
                        case 'no-swap':
                            $html = "<a href=\"tel:$phone\" class=\"button ctm-no-swap\">$value</a>";
                            break;
                        case 'no-swap-inner':
                            $html = "<a href=\"tel:$phone\" class=\"button\"><span class=\"ctm-no-swap\">$value</span></a>";
                            break;
                        case 'link-only':
                            $html = "<a href=\"tel:$phone\">$value</a>";
                            break;
                        case 'link-only-no-swap':
                            $html = "<a href=\"tel:$phone\" class=\"ctm-no-swap\">$value</a>";
                            break;
                        case 'link-only-no-swap-inner':
                            $html = "<a href=\"tel:$phone\"><span class=\"ctm-no-swap\">$value</span></a>";
                            break;
                        default:
                            $html = "<a href=\"tel:$phone\" class=\"button\">$value</a>";
                            break;
                    }
                }
                if(!empty($html)) {
                    $replacements[$original] = Markup::create($html);
                }
                else {
                    $replacements[$original] = '';
                }
            }
            else if ($name == 'main_nav_url') {
                $pageType = $arguments[0] ?? '<none>';
                $links = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties([
                    'menu_name' => 'main',
                    'field_page_type' => $pageType
                ]);
                if(!empty($links)) {
                    /** @var MenuLinkContent $link */
                    $link = array_shift($links);
                    $url = $link->getUrlObject();
                    $replacements[$original] = $url->toString();
                }
                else {
                    $replacements[$original] = '#';
                }
            }
            else if(!empty($replacement) && in_array($name, $this->imageSettingsKeys())) {
                /** @var File $file */
                $file = $replacement;
                $absolute = false;
                $style = $arguments[0] ?? null;
                $html = false;
                if($style == 'absolute') {
                    $style = $arguments[1] ?? null;
                    $absolute = true;
                }
                if($style == 'img') {
                    $html = true;
                    $style = $arguments[1] ?? null;
                }
                if(!empty($style)) {
                    /** @var ImageStyle $style */
                    $style = ImageStyle::load($style);
                }
                if(empty($style)) {
                    $url = $file->createFileUrl(!$absolute);
                }
                else {
                    $url = $style->buildUrl($file->getFileUri());
                    if(!$absolute) {
                        $url = \Drupal::service('file_url_generator')->transformRelative($url);
                    }
                }
                if(!empty($html)) {
                    $replacements[$original]  = Markup::create('<img src="'.$url.'">');
                }
                else {
                    $replacements[$original] = $url;
                }
            }
            else if($name == 'company_type') {
                $replacements[$original] = $this->getIndustry();
            }
            else if($name == 'main_branch') {
                $viewMode = $arguments[0] ?? 'teaser';
                $node = $this->getSetting('main_branch');
                if($node instanceof Node) {
                    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
                    $build = $view_builder->view($node, $viewMode);
                    $replacements[$original] = Markup::create(\Drupal::service('renderer')->render($build));
                }
                else {
                    $replacements[$original] = '';
                }
            }
            else if(!empty($replacement) && !empty($arguments[0]) && $arguments[0] == 'phone') {
                $format = null;
                if(!empty($arguments[1])) {
                    $format = $arguments[1];
                }
                $replacements[$original] = Markup::create($this->formatPhone($replacement, $format));
            }
            else if(isset($replacement)) {
                if(is_string($replacement) && !empty($replacement)) {
                    $replacements[$original] = Markup::create($replacement);
                }
                else {
                    $replacements[$original] = $replacement;
                }
            }
            else {
                $replacements[$original] = '';
            }
        }
        return $replacements;
    }

    /**
     * rough copy of Token::replace but limits to just sprowt tokens
     * @param $text
     */
    public function replaceSprowtTokens($text, array $data = [], array $options = [], BubbleableMetadata $bubbleable_metadata = NULL) {
        /** @var Token $token */
        $token = \Drupal::service('token');
        $text_tokens = $token->scan($text);
        if (empty($text_tokens)) {
            return $text;
        }

        if(empty($text_tokens['sprowt'])) {
            return $text;
        }

        $bubbleable_metadata_is_passed_in = (bool) $bubbleable_metadata;
        $bubbleable_metadata = $bubbleable_metadata ?: new BubbleableMetadata();

        $replacements = [];
        $replacements += $token->generate('sprowt', $text_tokens['sprowt'], $data, $options, $bubbleable_metadata);
        if (!empty($options['clear'])) {
            $replacements += array_fill_keys($text_tokens['sprowt'], '');
        }

        // Escape the tokens, unless they are explicitly markup.
        foreach ($replacements as $token => $value) {
            $replacements[$token] = $value instanceof MarkupInterface ? $value : new HtmlEscapedText($value);
        }

        // Optionally alter the list of replacement values.
        if (!empty($options['callback'])) {
            $function = $options['callback'];
            $function($replacements, $data, $options, $bubbleable_metadata);
        }

        $tokens = array_keys($replacements);
        $values = array_values($replacements);

        $renderer = \Drupal::service('renderer');

        // If a local $bubbleable_metadata object was created, apply the metadata
        // it collected to the renderer's currently active render context.
        if (!$bubbleable_metadata_is_passed_in && $renderer->hasRenderContext()) {
            $build = [];
            $bubbleable_metadata->applyTo($build);
            $renderer->render($build);
        }

        return str_replace($tokens, $values, $text);
    }

    public static function fieldPostRender($rendered, $renderArray) {
        if($rendered instanceof Markup) {
            $tokenData = [];
            $node = \Drupal::routeMatch()->getParameter('node');
            if($node instanceof Node) {
                $tokenData['node'] = $node;
            }
            $html = (string) $rendered;
            $newHtml = \Drupal::token()->replace($html, $tokenData);
            $newRendered = Markup::create($newHtml);
            $rendered = $newRendered;
        }
        return $rendered;
    }

    /**
     * Based off of file_validate_is_image()
     * @param FileInterface $file
     * @return array
     */
    public static function fileValidateIsImage(FileInterface $file) {
        $errors = [];

        $image_factory = \Drupal::service('image.factory');
        $image = $image_factory->get($file->getFileUri());
        if (!$image->isValid()) {
            $supported_extensions = static::getValidImageExtensions();
            $errors[] = t('The image file is invalid or the image type is not allowed. Allowed types: %types', ['%types' => implode(', ', $supported_extensions)]);
        }

        return $errors;
    }

    public static function getValidImageExtensions() {
        $image_factory = \Drupal::service('image.factory');
        $supported_extensions = $image_factory->getSupportedExtensions();
        if(!in_array('svg', $supported_extensions)) {
            $supported_extensions[] = 'svg';
        }
        return $supported_extensions;
    }

    public static function validateTouchIcon(FileInterface $file) {
        /** @var FileSystem $filesystem */
        $filesystem = \Drupal::service('file_system');
        $path = $filesystem->realpath($file->getFileUri());
        $errors = static::validateTouchIconFromPath($path);
        if(!empty($errors)) {
            foreach($errors as $key => $error) {
                //just warn about this error. Allow any size to be uploaded.
                if(strpos($error, 'accommodate retina displays') !== false) {
                    unset($errors[$key]);
                }
            }
        }
    }

    public static function validateTouchIconFromPath($path) {
        $errors = [];
        if(is_file($path)) {
            $size = getimagesize($path);
            $width = $size[0];
            $height = $size[1];
            $type = $size[2];
            if($type != IMAGETYPE_PNG) {
                $errors[] = t("Touch icons must be a .png file");
            }
            if($width < 180 || $height < 180) {
                $errors[] = t("It's recommended touch icons be at least 180x180 to accommodate retina displays.");
            }
        }
        return $errors;
    }

    public function themeSettingsAlter(&$form, FormStateInterface $formState) {

        $configKey = $form["config_key"]["#value"];
        $theme = str_replace('.settings', '', $configKey);
        //reset validator so
        $validators = $form["logo"]["settings"]["logo_upload"]["#upload_validators"] ?? null;
        if(is_array($validators)) { // remove default image validator
            if(isset($validators['file_validate_is_image'])) {
                unset($validators['file_validate_is_image']);
                $form["logo"]["settings"]["logo_upload"]["#upload_validators"] = $validators;
            }
        }
        else {
            $form["logo"]["settings"]["logo_upload"]["#upload_validators"] = [];
        }
        //add svg to valid extensions
        $form["logo"]["settings"]["logo_upload"]["#upload_validators"][static::class . '::fileValidateIsImage'] = [];
        $form["logo"]["settings"]["logo_upload"]["#upload_validators"]['file_validate_extensions'] = [implode(' ', static::getValidImageExtensions())];

        $i = 0;
        foreach($form as $key => $val) {
            if(!empty($val['#type'])) {
                ++$i;
                $form[$key]['#weight'] = $i;
            }
        }

        $fileSet = [
            'logo_reverse' => "reverse logo",
            'touch_icon' => "touch icon"
        ];

        foreach($fileSet as $key => $text) {

            $form[$key] = [
                '#type' => 'details',
                '#title' => $this->t(ucfirst($text) . " image"),
                '#weight' => $form['logo']['#weight'],
                '#open' => true
            ];

            $form[$key]['default_' . $key] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Use the ' .$text. ' supplied by the theme'),
                '#default_value' => theme_get_setting($key . '.use_default', $theme) ?? 1,
                '#tree' => FALSE,
            ];

            $form[$key]['settings'] = [
                '#type' => 'container',
                '#states' => [
                    // Hide the logo settings when using the default logo.
                    'invisible' => [
                        'input[name="default_'.$key.'"]' => ['checked' => TRUE],
                    ],
                ],
            ];
            $form[$key]['settings'][$key . '_path'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Path to custom ' . $text),
                '#default_value' => theme_get_setting($key . '.path', $theme),
            ];

            $form[$key]['settings'][$key . '_upload'] = [
                '#type' => 'file',
                '#title' => $this->t('Upload '.$text.' image'),
                '#description' => $this->t("If you don't have direct file access to the server, use this field to upload your $text."),
                '#upload_validators' => [
                    static::class . '::fileValidateIsImage' => [],
                    'file_validate_extensions' => [implode(' ', static::getValidImageExtensions())]
                ],
            ];
        }

        $form['touch_icon']['#description'] = $this->t("The touch icon is used to add icon images to mobile/tablet devices. See <a href=\"https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html\" target=\"_blank\">here</a> for more information.");

        $touchValidators = $form['touch_icon']['settings']['touch_icon_upload']['#upload_validators'];
        $form['touch_icon']['settings']['touch_icon_upload']['#upload_validators'] = array_merge($touchValidators, [
            'file_validate_extensions' => ['png'],
            static::class . '::validateTouchIcon' => [],
        ]);

        $request_method = \Drupal::requestStack()->getCurrentRequest()->getMethod();
        if($request_method == 'GET') {
            //warn about touch icon
            $touchIconPath = $this->getThemeImagePath('touch_icon', $theme);
            if (!empty($touchIconPath)) {
                $errors = static::validateTouchIconFromPath($touchIconPath);
                if (!empty($errors)) {
                    $message = Markup::create(implode('<br>', $errors));
                    $this->messenger()->addWarning($message);
                }
            }
        }

        // Inject human-friendly values and form element descriptions for reverse logo
        foreach (['logo_reverse' => 'logo_reverse.svg', 'touch_icon' => 'touch_icon.png'] as $type => $default) {
            if (isset($form[$type]['settings'][$type . '_path'])) {
                $element = &$form[$type]['settings'][$type . '_path'];

                // If path is a public:// URI, display the path relative to the files
                // directory; stream wrappers are not end-user friendly.
                $original_path = $element['#default_value'];
                $friendly_path = NULL;

                if (StreamWrapperManager::getScheme($original_path) == 'public') {
                    $friendly_path = StreamWrapperManager::getTarget($original_path);
                    $element['#default_value'] = $friendly_path;
                }

                // Prepare local file path for description.
                if ($original_path && isset($friendly_path)) {
                    $local_file = strtr($original_path, ['public:/' => PublicStream::basePath()]);
                }
                elseif ($theme) {
                    /** @var ExtensionPathResolver $resolver */
                    $resolver = \Drupal::service('extension.path.resolver');
                    if($theme == 'system.theme.global') {
                        $theme_path = $resolver->getPath('theme', 'sprowt3');
                    }
                    else {
                        $theme_path = $resolver->getPath('theme', $theme);
                    }
                    $local_file = $theme_path . '/' . $default;
                }
                else {
                    $local_file = $this->themeManager->getActiveTheme()->getPath() . '/' . $default;
                }

                $element['#description'] = $this->t('Examples: <code>@implicit-public-file</code> (for a file in the public filesystem), <code>@explicit-file</code>, or <code>@local-file</code>.', [
                    '@implicit-public-file' => isset($friendly_path) ? $friendly_path : $default,
                    '@explicit-file' => StreamWrapperManager::getScheme($original_path) !== FALSE ? $original_path : 'public://' . $default,
                    '@local-file' => $local_file,
                ]);
            }
        }

        $logoSets = ['logo', 'logo_reverse'];
        foreach($logoSets as $key) {
            $form[$key][$key . '_width'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Width'),
                '#description' => $this->t('Set the logo width (optional)'),
                '#default_value' => theme_get_setting($key . '.width', $theme)
            ];
        }

        $form['#validate'] = array_merge([
            [$this, 'validateThemeSettings']
        ], $form['#validate']);

        $form['#submit'] = array_merge([
            [$this, 'submitThemeSettings']
        ], $form['#submit']);

        return $form;
    }

    public function validateThemeSettings(&$form, FormStateInterface $formState) {
        $fileSet = [
            'logo_reverse' => "reverse logo",
            'touch_icon' => "touch icon"
        ];
        foreach($fileSet as $key => $text) {
            // Check for a new uploaded logo.
            if (isset($form[$key])) {
                $file = _file_save_upload_from_form($form[$key]['settings'][$key . '_upload'], $formState, 0);
                if ($file) {
                    // Put the temporary file in form_values so we can save it on submit.
                    $formState->setValue($key . '_upload', $file);
                }
            }

            // When intending to use the default logo, unset the logo_path.
            if ($formState->getValue('default_' . $key)) {
                $formState->unsetValue($key . '_path');
            }

            // If the user provided a path for the file, make sure a file
            // exists at that path.
            if ($formState->getValue($key . '_path')) {
                $path = $this->validatePath($formState->getValue($key . '_path'));
                if (!$path) {
                    $formState->setErrorByName($key . '_path', $this->t('The '.$text.' path is invalid.'));
                }
            }
        }
    }

    /**
     * Copied from ThemeSettingsForm::validatePath
     * Helper function for the system_theme_settings form.
     *
     * Attempts to validate normal system paths, paths relative to the public files
     * directory, or stream wrapper URIs. If the given path is any of the above,
     * returns a valid path or URI that the theme system can display.
     *
     * @param string $path
     *   A path relative to the Drupal root or to the public files directory, or
     *   a stream wrapper URI.
     *
     * @return mixed
     *   A valid path that can be displayed through the theme system, or FALSE if
     *   the path could not be validated.
     */
    protected function validatePath($path) {
        // Absolute local file paths are invalid.
        if ($this->fileSystem->realpath($path) == $path) {
            return FALSE;
        }
        // A path relative to the Drupal root or a fully qualified URI is valid.
        if (is_file($path)) {
            return $path;
        }
        // Prepend 'public://' for relative file paths within public filesystem.
        if (StreamWrapperManager::getScheme($path) === FALSE) {
            $path = 'public://' . $path;
        }
        if (is_file($path)) {
            return $path;
        }
        return FALSE;
    }

    public function submitThemeSettings(&$form, FormStateInterface $formState) {
        $values = $formState->getValues();
        $config_key = $values['config_key'];
        $config = \Drupal::configFactory()->getEditable($config_key);

        // If the user uploaded a new logo or favicon, save it to a permanent location
        // and use it in place of the default theme-provided file.
        $default_scheme = \Drupal::configFactory()->getEditable('system.file')->get('default_scheme');
        $fileSet = [
            'logo_reverse' => "reverse logo",
            'touch_icon' => "touch icon"
        ];
        foreach($fileSet as $key => $text) {
            try {
                if (!empty($values[$key . '_upload'])) {
                    $filename = $this->fileSystem->copy($values[$key . '_upload']->getFileUri(), $default_scheme . '://');
                    $values['default_' . $key] = 0;
                    $values[$key . '_path'] = $filename;
                }
            }
            catch (FileException $e) {
                // Ignore.
            }

            unset($values[$key . '_upload']);
            if (!empty($values[$key . '_path'])) {
                $values[$key . '_path'] = $this->validatePath($values[$key . '_path']);
            }

            $config->set($key . '.use_default', $values['default_' . $key] ?? 0);
            $config->set($key . '.path', $values[$key . '_path'] ?? '');

            $config->save();
            unset($values['default_' . $key]);
            unset($values[$key . '_path']);
        }

        $logoSets = ['logo', 'logo_reverse'];
        foreach($logoSets as $key) {
            $width = $values[$key . '_width'] ?? null;
            $config->set($key . '.width', $width);
            $config->save();
            unset($values[$key . '_width']);
        }

        //set new values without extra stuff
        $formState->setValues($values);
    }

    public function getThemeImagePath($key, $theme = null) {
        $theme_setting_key = str_replace('.url', '', $key);
        if (!isset($theme)) {
            $theme = \Drupal::theme()->getActiveTheme()->getName();
        }
        if($theme == 'system.theme.global') {
            $themeObj = null;
        }
        else {
            try {
                /** @var \Drupal\Core\Extension\Extension $themeObj */
                $themeObj = \Drupal::service('theme_handler')->getTheme($theme);
            }
            catch (UnknownExtensionException $e) {
                $themeObj = null;
            }
        }

        $default = theme_get_setting($theme_setting_key . '.use_default', $theme) ?? 1;
        if(!empty($default)) {
            $extension = 'svg';
            if($theme_setting_key == 'touch_icon') {
                $extension = 'png';
            }
            $fileName = $theme_setting_key . '.' . $extension;
            if(!empty($themeObj)) {
                if(!empty($themeObj->info[$theme_setting_key])) {
                    $fileName = $themeObj->info[$theme_setting_key];
                }
            }
            /** @var ExtensionPathResolver $resolver */
            $resolver = \Drupal::service('extension.path.resolver');
            if($theme == 'system.theme.global') {
                $theme_path = $resolver->getPath('theme', 'sprowt3');
            }
            else {
                $theme_path = $resolver->getPath('theme', $theme);
            }

            $path = $this->validatePath($theme_path . '/' . $fileName);
        }
        else {
            $path = $this->validatePath(theme_get_setting($theme_setting_key . '.path', $theme));
        }

        return $path;
    }

    public function getThemeSetting($key, $theme = null) {

        $sprowtFileSet = [
            'logo_reverse',
            'touch_icon'
        ];
        foreach($sprowtFileSet as $sprowtFileSetKey) {
            $sprowtFileSet[] = $sprowtFileSetKey . '.url';
        }

        $fileSet = array_merge([
            'logo',
            'favicon'
        ]);

        if(in_array($key, $sprowtFileSet)) {
            $path = $this->getThemeImagePath($key, $theme);
            if(!empty($path)) {
                /** @var FileUrlGenerator $generator */
                $generator = \Drupal::service('file_url_generator');
                return $generator->generateString($path);
            }
            return null;
        }

        if(in_array($key, $fileSet)) {
            $key  = "$key.url";
        }

        return theme_get_setting($key, $theme);
    }

}
