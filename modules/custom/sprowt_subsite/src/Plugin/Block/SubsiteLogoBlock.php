<?php

namespace Drupal\sprowt_subsite\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Template\Attribute;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\sprowt_subsite\SettingsManager;
use Drupal\sprowt_subsite\SubsiteService;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides a logo block
 *
 * @Block(
 *   id = "sprowt_subsite_logo_block",
 *   admin_label = @Translation("Subsite logo block"),
 *   forms = {
 *     "settings_tray" = "Drupal\system\Form\SystemBrandingOffCanvasForm",
 *   },
 * )
 */
class SubsiteLogoBlock extends BlockBase implements ContainerFactoryPluginInterface
{

    protected $logoField = 'field_logo';

    protected $imgLinkClasses = [
        'subsite-logo',
        'site-logo'
    ];

    public function __construct(array $configuration, $plugin_id, $plugin_definition)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition
        );
    }

    public function getSubsite() {
        return SettingsManager::getCurrentNodeSubsite();
    }

    public function subsiteHomePageUrl() {
        $subsite = $this->getSubsite();
        if(empty($subsite)) {
            return '/';
        }
        /** @var SubsiteService $service */
        $service = \Drupal::service('sprowt_subsite.service');
        $node = $service->getSubsiteHomePageFromSubsite($subsite);
        if(empty($node)) {
            return '/';
        }

        return $node->toUrl()->toString();
    }

    protected function systemLogo() {
        return theme_get_setting('logo.url');
    }

    public function getLogoUri() {
        /** @var ?Node $subsite */
        $subsite = $this->getSubsite();
        if(empty($subsite)) {
            return $this->systemLogo();
        }

        /** @var EntityReferenceFieldItemList $list */
        $list = $subsite->get($this->logoField);
        if($list->isEmpty()) {
            return $this->systemLogo();
        }
        $medias = $list->referencedEntities();
        /** @var Media $media */
        $media = array_Shift($medias);
        /** @var FileFieldItemList $imgList */
        $imgList = $media->get('field_media_image');
        if($imgList->isEmpty()) {
            return $this->systemLogo();
        }
        /** @var ImageItem $imgItem */
        $imgItem = $imgList->first();
        /** @var File $img */
        $img = $imgItem->entity;
        return $img->createFileUrl();
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheTags() {
        /** @var ?Node $subsite */
        $subsite = $this->getSubsite();
        if(!empty($subsite)) {
            return Cache::mergeTags(
                parent::getCacheTags(),
                $subsite->getCacheTags()
            );
        }

        return parent::getCacheTags();
    }

    public function getCacheMaxAge()
    {
        return 0;
    }

    public function build()
    {
        $build = [
            '#theme' => 'subsite_logo',
            '#subsite_url' => $this->subsiteHomePageUrl(),
            '#site_logo' => $this->getLogoUri()
        ];

        $siteName = \Drupal::config('system.site')->get('name');

        $imgAttributes = new Attribute();
        $imgAttributes->setAttribute('alt', $siteName);

        $subsite = $this->getSubsite();
        if(!empty($subsite)) {
            $imgAttributes->setAttribute('alt', $siteName . ' | ' . $subsite->label());
        }
        $build['#imageAttributes'] = $imgAttributes;

        $imgLinkAttributes = new Attribute();
        $imgLinkAttributes->addClass($this->imgLinkClasses);
        $build['#linkAttributes'] = $imgLinkAttributes;

        return $build;
    }
}
