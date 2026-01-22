<?php

namespace Drupal\sprowt_subsite;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\sprowt_settings\Form\SprowtSettingsForm;
use Drupal\sprowt_settings\SprowtSettings;
use Drupal\sprowt_subsite\Plugin\DataType\SubsiteSettingsProperty;
use Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItem;
use phpDocumentor\Reflection\DocBlock\StandardTagFactory;

/**
 * Service description.
 */
class SettingsManager
{

    public $fileDirectory = 'public://sprowt';
    public const SUBSITE_FIELD = 'field_subsite';

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $connection;

    /**
     * @var SprowtSettings
     */
    protected $sprowtSettings;

    /**
     * Constructs a SettingsManager object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     */
    public function __construct(
        Connection $connection,
        SprowtSettings $sprowtSettings
    ){
        $this->connection = $connection;
        $this->sprowtSettings = $sprowtSettings;
    }

    public static function getCurrentNodeSubsite() {
        $routeMatch = \Drupal::routeMatch();
        if($routeMatch->getRouteName() == 'entity.node.canonical') {
            $node = $routeMatch->getParameter('node');
            if(!empty($node)) {
                if(!$node instanceof Node) {
                    $node = Node::load($node);
                }
                if(!empty($node)) {
                    return static::getSubsiteFromNode($node);
                }
            }
        }

        return null;
    }

    public static function getSubsiteFromNode(Node $node) {
        if ($node->bundle() == 'subsite') {
            return $node;
        }
        if ($node->hasField(static::SUBSITE_FIELD)) {
            $itemList = $node->get(static::SUBSITE_FIELD);
            if (!$itemList->isEmpty()) {
                /** @var SubsiteReferenceItem $item */
                $item = $itemList->first();
                $subsiteNid = $item->target_id;
                if(!empty($subsiteNid)) {
                    return Node::load($subsiteNid);
                }
            }
        }
        return null;
    }


    public function deleteSettingsByNid($nid) {
        $this->connection->delete('subsite_settings')
            ->condition('nid', $nid)
            ->execute();
    }

    public function getSubsiteSettingsFromNid($nid) {
        $settings = $this->connection->query("
            SELECT setting_key, setting_value
            FROM subsite_settings
            WHERE nid = :nid
        ", [
            'nid' => $nid
        ])->fetchAllKeyed();

        array_walk($settings, function(&$value) {
            $value = unserialize($value);
        });
        return $settings;
    }

    public function getSubsiteNid(Node $node) {
        if($node->bundle() == 'subsite') {
            return $node->id();
        }
        if($node->hasField(static::SUBSITE_FIELD)) {
            $itemList = $node->get(static::SUBSITE_FIELD);
            if(!$itemList->isEmpty()) {
                /** @var SubsiteReferenceItem $item */
                $item = $itemList->first();
                return $item->target_id;
            }
        }
    }

    public function getSubsiteSettings(Node $subsite) {
        $nid = $this->getSubsiteNid($subsite);
        if(!empty($nid)) {
            return $this->getSubsiteSettingsFromNid($nid);
        }

        return [];
    }

    public function setSettingsByNid($nid, $settings) {
        $transactions = [];
        $currentSettings = $this->getSubsiteSettingsFromNid($nid);
        $keys = array_keys($settings);
        $currentKeys = array_keys($currentSettings);
        $deleted = [];
        foreach($currentKeys as $currentKey) {
            if(!in_array($currentKey, $keys)) {
                $transactions[] = $this->connection->delete('subsite_settings')
                    ->condition('nid', $nid)
                    ->condition('setting_key', $currentKey);
                $deleted[] = $currentKey;
            }
        }

        foreach($settings as $setting => $value) {
            if(isset($currentSettings[$setting])) {
                if($value != $currentSettings[$setting]) {
                    $transactions[] = $this->connection->update('subsite_settings')
                        ->fields([
                            'setting_value' => serialize($value)
                        ])
                        ->condition('nid', $nid)
                        ->condition('setting_key', $setting);
                }
            }
            else {
                $transactions[] = $this->connection->insert('subsite_settings')
                    ->fields([
                        'nid' => $nid,
                        'setting_key' => $setting,
                        'setting_value' => serialize($value)
                    ]);
            }
        }

        $trans = $this->connection->startTransaction();
        try {
            foreach($transactions as $t) {
                $t->execute();
            }
        }
        catch (\Exception $e) {
            $trans->rollBack();
            \Drupal::logger('sprowt_subsite')->error(t('Error setting settings: ', $e->getMessage()), [
                'exception' => $e
            ]);
            throw $e;
        }

        unset($trans);
    }

    public function setSettingByNid($nid, $key, $value) {
        if($value instanceof File) {
            if(strpos($value->getFileUri(), $this->fileDirectory) !== 0) {
                /** @var \Drupal\Core\File\FileSystem $fileSystem */
                $fileSystem = \Drupal::service('file_system');
                $fileSystem->prepareDirectory($this->fileDirectory, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
                /** @var \Drupal\file\FileRepositoryInterface $file_repository */
                $file_repository = \Drupal::service('file.repository');
                $value = $file_repository->move($value, $this->fileDirectory . '/' . $value->getFilename(), FileSystemInterface::EXISTS_REPLACE);
                if(!$value->isPermanent()) {
                    $value->setPermanent();
                    $value->save();
                }
            }
            $value = $value->uuid();
        }
        $currentSettings = $this->getSubsiteSettingsFromNid($nid);
        if(isset($currentSettings[$key])) {
            if($value != $currentSettings[$key]) {
                $transaction = $this->connection->update('subsite_settings')
                    ->fields([
                        'setting_value' => serialize($value)
                    ])
                    ->condition('nid', $nid)
                    ->condition('setting_key', $key);
            }
        }
        else {
            $transaction = $this->connection->insert('subsite_settings')
                ->fields([
                    'nid' => $nid,
                    'setting_key' => $key,
                    'setting_value' => serialize($value)
                ]);
        }

        if(!empty($transaction)) {
            $transaction->execute();
        }
    }

    public function deleteSettingByNid($nid, $key) {
        $this->connection->delete('subsite_settings')
            ->condition('nid', $nid)
            ->condition('setting_key', $key)
            ->execute();
    }

    public function deleteSetting(EntityInterface $subsite, $key) {
        if(!$subsite instanceof Node || $subsite->bundle() != 'subsite') {
            return $subsite;
        }
        $this->deleteSettingByNid($subsite->id(), $key);
        return $subsite;
    }

    public function setSetting(EntityInterface $subsite, $key, $value) {
        if(!$subsite instanceof Node || $subsite->bundle() != 'subsite') {
            return $subsite;
        }
        $this->setSettingByNid($subsite->id(), $key, $value);
        return $subsite;
    }

    public function setSettings(EntityInterface $subsite, $settings = []) {
        if(!$subsite instanceof Node || $subsite->bundle() != 'subsite') {
            return $subsite;
        }
        $this->setSettingsByNid($subsite->id(), $settings);
        return $subsite;
    }


    /**
     * mimics Drupal\sprowt_settings\SprowtSettings::getSetting
     * but tries to grab the setting from the subsite first
     *
     * @param Node $subsite
     * @param $key
     * @param $default
     * @return array|EntityInterface|File|mixed|string|null
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function getSetting(Node $subsite, $key, $default = null, $getMain = true) {
        if($key == 'webform_mail' || $key == 'webform_email') {
            $key = 'webform_from_email';
        }
        if($key == 'years_in_business') {
            $yearStarted = $this->getSetting($subsite, 'year_started');
            if(!empty($yearStarted)) {
                $now = new \DateTime();
                $years = $now->format('Y') - $yearStarted;
                return $years;
            }
            return $default;
        }

        if($key == 'state_name') {
            $abbr = $this->getSetting($subsite, 'state');
            return SprowtSettings::getStateName($abbr);
        }
        $settings = $this->getSubsiteSettings($subsite);
        /** @var SprowtSettings $sprowtSettings */
        $sprowtSettings = $this->sprowtSettings;
        $entityTypeManager = \Drupal::entityTypeManager();
        $sprowtSettingsContext = [
            'doNotAlter' => true
        ];

        $returnDefault = function($default) use ($key, $sprowtSettingsContext, $sprowtSettings, $getMain) {
            if($getMain) {
                return $sprowtSettings->getSetting($key, $default, $sprowtSettingsContext);
            }
            else {
                return $default;
            }
        };

        if(in_array($key, $sprowtSettings->getFileSettings())) {
            $uuid = $settings[$key] ?? null;
            if(empty($uuid)) {
                return $returnDefault($default);
            }
            if($uuid instanceof File) {
                $file = $uuid;
            }
            else {
                $file = $entityTypeManager->getStorage('file')->loadByProperties([
                    $entityTypeManager->getDefinition('file')->getKey('uuid') => $uuid
                ]);
            }
            $ret = is_array($file) ? array_pop($file) : $file;
            if($ret instanceof File) {
                return $ret;
            }
            return $returnDefault($default);
        }


        $defaultValues = SprowtSettingsForm::defaultSettings();
        if(in_array($key, array_keys($defaultValues))) {
            if(!isset($default) && isset($defaultValues[$key])) {
                $default = $defaultValues[$key];
            }
            $value = $settings[$key] ?? null;
            return $value ?? $returnDefault($default);
        }

        $value = $settings[$key] ?? null;
        if(!isset($value)) {
            $ctm = $settings['ctm'] ?? [];
            if(!empty($ctm[$key])) {
                return $ctm[$key];
            }
            if($this->isConditionalToken($subsite, $key)) {
                return $this->returnConditionalToken($subsite, $key);
            }
            $custom = $settings['custom'] ?? [];
            return $custom[$key] ?? $returnDefault($default);
        }

        return $value;
    }

    public function returnConditionalToken(Node $subsite, $key, $default = null)
    {
        if(!$this->isConditionalToken($subsite, $key)) {
            return $default;
        }
        $tokens = $this->getConditionalTokens($subsite, [$key]);
        $tokenInfo = $tokens[$key] ?? [];
        if(empty($tokenInfo['value'])) {
            return $default;
        }
        $visibility = $tokenInfo['visibility'] ?? [];
        if($this->sprowtSettings->isVisible($visibility)) {
            return $tokenInfo['value'] ?? $default;
        }

        return $default;
    }

    public function isConditionalToken(Node $subsite, $key)
    {
        $conditionalTokens = $this->getConditionalTokens($subsite);
        return isset($conditionalTokens[$key]);
    }

    public function getConditionalTokens( Node $subsite, $keys = []) {
        $settings = $this->getSubsiteSettings($subsite);
        $allTokens = $settings['conditional_tokens'] ?? [];
        if(!empty($keys) && !empty($allTokens)) {
            $allTokens = array_filter($allTokens, function ($key) use ($keys) {
                return in_array($key, $keys);
            }, ARRAY_FILTER_USE_KEY);
        }
        return $allTokens;
    }

    public function setConditionalToken(Node $subsite, $key, $value, $visibility = []) {
        $conditionalTokens = $this->getConditionalTokens($subsite);
        $conditionalTokens[$key] = [
            'key' => $key,
            'value' => $value,
            'visibility' => $visibility
        ];
        $this->setSetting($subsite, 'conditional_tokens', $conditionalTokens);
    }

    public function deleteConditionalToken(Node $subsite, $key) {
        $conditionalTokens = $this->getConditionalTokens($subsite);
        if(isset($conditionalTokens[$key])) {
            unset($conditionalTokens[$key]);
            $this->setSetting($subsite, 'conditional_tokens', $conditionalTokens);
        }
    }

}
