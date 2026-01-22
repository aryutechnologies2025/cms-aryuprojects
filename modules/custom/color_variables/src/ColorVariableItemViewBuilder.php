<?php

namespace Drupal\color_variables;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Theme\Registry;

/**
 * Provides a view controller for a theme color variables entity type.
 */
class ColorVariableItemViewBuilder extends EntityViewBuilder
{



    public function previewMarkup($entity) {
        $twig = \Drupal::service('twig');
        $colorVariableService = \Drupal::service('color_variables.service');
        $colors = $colorVariableService->getAllColorVariablesFromEntity($entity);
        $file = $colorVariableService->getThemePreviewTemplateFile($entity->getTheme());
        $twigCode = file_get_contents($file);
        return $twig->renderInline($twigCode, [
            'theme' => $entity->getTheme(),
            'colors' => $colors
        ]);
    }

    public function getPreviewElement($entity) {
        $colorVariableService = \Drupal::service('color_variables.service');
        $element = [
            '#title' => 'Preview',
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['preview-wrap'],
                'id' => 'preview'
            ]
        ];
        $colors = $colorVariableService->getAllColorVariablesFromEntity($entity);
        $vars = [];
        foreach ($colors as $var => $val) {
            $vars['color-' . $entity->getTheme() . '-' . $var] = $val;
        }
        $css = $colorVariableService->getCssMarkup($vars, '#preview');
        $element['colorVariableStyle'] = [
            '#type' => 'html_tag',
            '#tag' => 'style',
            '#attributes' => [
                'class' => ['color-variable-style-block'],
                'id' => 'color-variable-style-block',
                'data-original-vars' => json_encode($colors),
                'data-theme' => $entity->getTheme()
            ],
            '#value' => Markup::create($css)
        ];

        $element['previewMarkup'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['color-variable-preview-markup'],
                'id' => 'color-variable-preview-markup'
            ],
            '#value' => Markup::create($this->previewMarkup($entity))
        ];

        return $element;
    }

    /**
     * {@inheritdoc}
     */
    protected function getBuildDefaults(EntityInterface $entity, $view_mode)
    {
        $build = parent::getBuildDefaults($entity, $view_mode);
        // The theme color variables has no entity template itself.
        unset($build['#theme']);

        $build['summary'] = [
            '#type' => 'fieldset',
            '#title' => 'Overridden Colors',
            'ul' => $entity->getSummary()
        ];

        $build['preview'] = $this->getPreviewElement($entity);
        return $build;
    }

}
