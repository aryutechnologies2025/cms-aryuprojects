<?php

namespace Drupal\sprowt_settings\Form;

use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sprowt\Form\DownloadInstallFileForm;

class SprowtSettingsInstallFileForm extends FormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'sprowt_settings_install_file_form';
    }

    public function getInstallInfo() {
        if(isset($this->installInfo)) {
            return $this->installInfo;
        }
        $info = \Drupal::state()->get('install_info', []);
        if(empty($info)) {
            return [];
        }
        $this->modifyInstallInfo($info);
        $yaml = \Drupal\Core\Serialization\Yaml::encode($info);
        /** @var FileSystem $fileSystem */
        $fileSystem = \Drupal::service('file_system');
        $configuration = $info['configuration'];
        $siteName = $configuration['site_name'];
        $fileifiedName = trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower($siteName)), '-');
        $filename = "$fileifiedName--sprowt-setup.yml";
        $file = 'private://' . $filename;
        $fileSystem->saveData($yaml, $file, FileSystemInterface::EXISTS_REPLACE);
        $this->installInfo = $info;
        return $this->installInfo;
    }

    public function modifyInstallInfo(&$info) {
        $branding = $info['branding'] ?? [];
        /** @var FileSystem $fileSystem */
        $fileSystem = \Drupal::service('file_system');
        if(!empty($branding)) {
            if (!empty($branding['logo']) && !empty($branding['logo']['location'])) {
                $content = file_get_contents($fileSystem->realpath($branding['logo']['location']));
                if(!empty($content)) {
                    $branding['logo']['content'] = base64_encode($content);
                }
            }
            $info['branding'] = $branding;
        }
        if(!empty($info['affiliations'])) {
            foreach($info['affiliations'] as &$item) {
                if(!empty($item['image'])) {
                    $array = [
                        'location' => $item['image'],
                    ];
                    $content = file_get_contents($fileSystem->realpath($item['image']));
                    if(!empty($content)) {
                        $array['content'] = base64_encode($content);
                    }
                    $item['image'] = $array;
                }
            }
        }
    }


    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['#title'] = $this->t('Download install form');

        $info = $this->getInstallInfo();
        if(empty($info)) {
            $form['descriptionWrap'] = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                'description' => [
                    '#type' => 'markup',
                    '#markup' => \Drupal\Core\Render\Markup::create('
                    <p>No install info exists.</p>
                ')
                ]
            ];
            return $form;
        }
        $rawData = \Drupal\Core\Serialization\Yaml::encode($info);
        $data = base64_encode($rawData);
        $url = 'data: application/x-yaml;base64,' . $data;
        $configuration = $info['configuration'];
        $siteName = $configuration['site_name'];
        $fileifiedName = trim(preg_replace('/[^a-z0-9\-]+/', '-', strtolower($siteName)), '-');
        $filename = "$fileifiedName--sprowt-setup.yml";

        $form['descriptionWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            'description' => [
                '#type' => 'markup',
                '#markup' => \Drupal\Core\Render\Markup::create('
                    <p>Download the file below to use in any future installs to bypass the forms. This file contains all install info used in this installer instance.</p>
                    <p><a download="'.$filename.'" href="'.$url.'" target="_blank">Download File</a></p>
                ')
            ]
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        //do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        //do nothing
    }
}
