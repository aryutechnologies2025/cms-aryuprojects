<?php

declare(strict_types=1);

namespace Drupal\layout_builder_hide\Form;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_hide\LayoutBuilderHideService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Layout Builder Hide form.
 */
class BulkHideComponentform extends FormBase
{

    use AjaxFormHelperTrait;
    use LayoutBuilderHighlightTrait;
    use LayoutRebuildTrait;

    /**
     * The section storage.
     *
     * @var \Drupal\layout_builder\SectionStorageInterface
     */
    protected $sectionStorage;

    /**
     * The Layout Tempstore.
     *
     * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
     */
    protected $layoutTempstore;

    /**
     *
     * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
     *   The layout tempstore.
     */
    public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
        $this->layoutTempstore = $layout_tempstore_repository;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('layout_builder.tempstore_repository')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'layout_builder_hide_bulk_hide_componentform';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL): array
    {
        $this->sectionStorage = $section_storage;

        $sections = $section_storage->getSections();

        $stop = true;
        /**
         * @var int $sectionDelta
         * @var Section $section
         */
        foreach($sections as $sectionDelta => $section) {
            $sectionKey = 'section.' . $sectionDelta;
            $layout_settings = $section->getLayoutSettings();
            $section_label = !empty($layout_settings['label']) ? $layout_settings['label'] : $this->t('Section @section', ['@section' => $sectionDelta + 1]);
            $form[$sectionKey] = [
                '#type' => 'fieldset',
                '#title' => 'Section: ' . $section_label,
            ];
            $components = $section->getComponents();
            /** @var SectionComponent $component */
            foreach ($components as $component) {
                $isHidden = $component->get('layout_builder_hide') ?? false;
                $componentConfig = $component->get('configuration');
                $form[$sectionKey][$sectionDelta . '::' . $component->getUuid()] = [
                    '#type' => 'checkbox',
                    '#title' => $componentConfig['label'] ?? $componentConfig['id'],
                    '#default_value' => $isHidden,
                ];
            }
        }

        $form['actions'] = [
            '#type' => 'actions',
            'submit' => [
                '#type' => 'submit',
                '#value' => $this->t('Submit'),
            ],
        ];

        if ($this->isAjax()) {
            $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // @todo Validate the form here.
        // Example:
        // @code
        //   if (mb_strlen($form_state->getValue('message')) < 10) {
        //     $form_state->setErrorByName(
        //       'message',
        //       $this->t('Message should be at least 10 characters.'),
        //     );
        //   }
        // @endcode
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        /** @var LayoutBuilderHideService $service */
        $service = \Drupal::service('layout_builder_hide.service');
        $values = $form_state->getValues();
        $changed = false;
        foreach ($values as $key => $value) {
            if(strpos($key, '::') !== false) {
                $parts = explode('::', $key);
                $sectionDelta = $parts[0];
                $componentUuid = $parts[1];
                $hide = (bool) $value;
                $component = $this->sectionStorage->getSection($sectionDelta)->getComponent($componentUuid);
                $isHidden = $component->get('layout_builder_hide') ?? false;
                if($hide != $isHidden) {
                    $service->hideBlockComponent($component, $hide);
                    $changed = true;
                }
            }
        }
        if($changed) {
            $this->layoutTempstore->set($this->sectionStorage);
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
        $response = $this->rebuildLayout($this->sectionStorage);
        $response->addCommand(new CloseDialogCommand());
        return $response;
    }
}
