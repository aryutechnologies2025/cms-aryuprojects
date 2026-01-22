<?php

namespace Drupal\color_variables\Form;

use Drupal\color_variables\ColorVariablesService;
use Drupal\color_variables\Entity\ColorVariableItem;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\TwigEnvironment;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the theme color variables entity edit forms.
 */
class ColorVariableItemForm extends ContentEntityForm
{

    /**
     * The entity being used by this form.
     *
     * @var ColorVariableItem
     */
    protected $entity;

    /**
     * @var ThemeHandlerInterface
     */
    protected $themeHandler;

    /**
     * @var ColorVariablesService
     */
    protected $colorVariableService;

    /**
     * @var TwigEnvironment
     */
    protected $twig;

    public static function create(ContainerInterface $container)
    {
        $entity = parent::create($container);
        $entity->themeHandler = $container->get('theme_handler');
        $entity->colorVariableService = $container->get('color_variables.service');
        $entity->twig = $container->get('twig');
        return $entity;
    }

    public function form(array $form, FormStateInterface $form_state)
    {
        if($this->entity->isNew()) {
            //TODO: make sure this is grabbing the right theme
            $routeMatch = \Drupal::routeMatch();
            $theme = $routeMatch->getParameter('theme');
            $this->entity->set('theme', $theme);
        }

        /** @var Extension $themeObj */
        $themeObj = $this->themeHandler->getTheme($this->entity->getTheme());
        $fields = [];
        $form['theme_name'] = [
            '#type' => 'item',
            '#title' => 'Theme',
            '#markup' => $themeObj->info['name']
        ];

        $fields['colorVariableWrap'] = [
            '#type' => 'fieldset',
            '#title' => 'Color variables',
            '#attributes' => [
                'class' => ['color-variable-wrap']
            ]
        ];

        $colors = $this->colorVariableService->getAllColorVariablesFromEntity($this->entity);
        $defaults = $this->colorVariableService->themeInfoColors($this->entity->getTheme());
        foreach($colors as $var => $val) {
            $fields['colorVariableWrap'][$var] = [
                '#type' => 'textfield',
                '#title' => $var,
                '#default_value' => $val,
                '#open' => true,
                '#attributes' => [
                    'class' => ['color-field', 'color-var-bg'],
                    'data-variable' => $var
                ],
                '#prefix' => '<div class="color-field-form-item-wrap">',
                '#suffix' => '</div>',
                '#field_suffix' => [
                    '#type' => 'html_tag',
                    '#tag' => 'div',
                    'button' => [
                        '#type' => 'html_tag',
                        '#tag' => 'button',
                        '#attributes' => [
                            'type' => 'button',
                            'class' => ['button', 'color-picker-button']
                        ],
                        '#value' => 'Color picker'
                    ],
                    'picker' => [
                        '#type' => 'color',
                        '#attributes' => [
                            'class' => ['color-picker']
                        ],
                        '#prefix' => Markup::create('<div class="color-picker-input-wrap" style="display:none;">'),
                        '#suffix' => '</div>'
                    ],
                    'clear' => [
                        '#type' => 'html_tag',
                        '#tag' => 'button',
                        '#attributes' => [
                            'type' => 'button',
                            'class' => ['button', 'button--danger', 'clear-field'],
                            'data-default-color' => $defaults[$var] ?? ''
                        ],
                        '#value' => 'Revert'
                    ]
                ]
            ];
        }

        if(empty($colors)) {
            $fields['colorVariableWrap']['message'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('<strong>No color variable set for this theme</strong>')
            ];
        }

        $form['formWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['form-wrap']
            ],
        ];
        $form['formWrap']['fieldsWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['fields-wrap']
            ],
        ] + $fields;

        $form['formWrap']['previewWrap'] = $this->getPreviewElement();

        $form['#attached']['library'][] = 'color_variables/color_variable_item_form';

        $form = parent::form($form, $form_state);

        $form["revision"]["#default_value"] = true;
        $form['revision']['#access'] = false;

        return $form;
    }

    public function getPreviewElement() {
        $element = [
            '#title' => 'Preview',
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['preview-wrap'],
                'id' => 'preview'
            ]
        ];
        $colors = $this->colorVariableService->getAllColorVariablesFromEntity($this->entity);
        $vars = [];
        foreach ($colors as $var => $val) {
            $vars['color-' . $var] = $val;
        }
        $css = $this->colorVariableService->getCssMarkup($vars, '#preview');
        $element['colorVariableStyle'] = [
            '#type' => 'html_tag',
            '#tag' => 'style',
            '#attributes' => [
                'class' => ['color-variable-style-block'],
                'id' => 'color-variable-style-block',
                'data-original-vars' => json_encode($colors),
                'data-theme' => $this->entity->getTheme()
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
            '#value' => Markup::create($this->previewMarkup())
        ];

        return $element;
    }

    public function previewMarkup() {
        $colors = $this->colorVariableService->getAllColorVariablesFromEntity($this->entity);
        $file = $this->colorVariableService->getThemePreviewTemplateFile($this->entity->getTheme());
        $twigCode = file_get_contents($file);
        return $this->twig->renderInline($twigCode, [
            'theme' => $this->entity->getTheme(),
            'colors' => $colors
        ]);
    }

    public function gatherOverrides(array $form, FormStateInterface $form_state) {
        $colors = $this->colorVariableService->getAllColorVariablesFromEntity($this->entity);
        $themeColors = $this->colorVariableService->themeInfoColors($this->entity->getTheme());
        $return = [];
        foreach($colors as $var => $originalColor) {
            $formColor = $form_state->getValue($var);
            $themeColor = $themeColors[$var] ?? null;
            if(isset($themeColor)) {
                if($themeColor != $formColor) {
                    $return[$var] = $formColor;
                }
            }
            else {
                $return[$var] = $formColor;
            }
        }

        return $return;
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if($this->entity->isNew()) {
            $theme = $this->entity->getTheme();
            $savedEntities = $this->entityTypeManager->getStorage('color_variable_item')->loadByProperties([
                'theme' => $theme
            ]);
            if(!empty($savedEntities)) {
                /** @var ColorVariableItem $oldEntity */
                $oldEntity = array_pop($savedEntities);
                if(!empty($savedEntities)) {
                    foreach ($savedEntities as $extraEntity) {
                        $extraEntity->delete();
                    }
                }
                $link = $oldEntity->toLink('Edit variables', 'edit-form')->toString();
                $form_state->setErrorByName('exists', Markup::create('Color variable item already exists for this theme! ' . $link));
            }
        }
        return parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {

        /** @var ColorVariableItem $entity */
        $entity = $this->getEntity();
        $entity->setNewRevision();

        $overrides = $this->gatherOverrides($form, $form_state);
        $entity->set('variables', $overrides);
        $result = $entity->save();
        $link = $entity->toLink($this->t('View'), 'edit-form')->toString();

        $message_arguments = ['%label' => $this->entity->label()];
        $logger_arguments = $message_arguments + ['link' => $link];

        if ($result == SAVED_NEW) {
            $this->messenger()->addStatus($this->t('New theme color variables for theme %label has been created.', $message_arguments));
            $this->logger('color_variables')->notice('Created new theme color variables for theme %label', $logger_arguments);
        } else {
            $this->messenger()->addStatus($this->t('The theme color variables form theme %label has been updated.', $message_arguments));
            $this->logger('color_variables')->notice('Updated new theme color variables for theme %label.', $logger_arguments);
        }

        $form_state->setRedirect('entity.color_variable_item.edit_form', [
            'color_variable_item' => $entity->id(),
            'color_variable_theme' => $entity->getTheme()
        ]);
    }

}
