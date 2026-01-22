<?php

namespace Drupal\solution_finder\Plugin\Block;

use Drupal\block\Entity\Block;
use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\example\ExampleInterface;
use Drupal\node\Entity\Node;
use Drupal\solution_finder\Entity\Solution;
use Drupal\solution_finder\SolutionInterface;
use Drupal\solution_finder\SolutionFinderService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a solution finder block.
 *
 * @Block(
 *   id = "solution_finder_solution_finder",
 *   admin_label = @Translation("Solution Finder"),
 *   category = @Translation("Solution Finder")
 * )
 */
class SolutionFinderBlock extends BlockBase implements ContainerFactoryPluginInterface, FormInterface
{

    /**
     * The solution_finder.service service.
     *
     * @var SolutionFinderService
     */
    protected $solutionFinderService;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var FormBuilderInterface
     */
    protected $formBuilder;

    /**
     * @var UuidInterface
     */
    protected $uuidGenerator;

    /**
     * Constructs a new SolutionFinderBlock instance.
     *
     * @param array $configuration
     *   The plugin configuration, i.e. an array with configuration values keyed
     *   by configuration option name. The special key 'context' may be used to
     *   initialize the defined contexts by setting it to an array of context
     *   values keyed by context names.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\example\ExampleInterface $solution_finder_service
     *   The solution_finder.service service.
     */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        SolutionFinderService $solution_finder_service,
        EntityTypeManagerInterface $entityTypeManager,
        FormBuilderInterface $formBuilder,
        UuidInterface $uuid
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->solutionFinderService = $solution_finder_service;
        $this->entityTypeManager = $entityTypeManager;
        $this->formBuilder = $formBuilder;
        $this->uuidGenerator = $uuid;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('solution_finder.service'),
            $container->get('entity_type.manager'),
            $container->get('form_builder'),
            $container->get('uuid')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'excludedConcernUuids' => [],
            'excludedSolutionUuids' => [],
            'defaultDestination' => '/contact',
            'heading' => 'What kind of <strong>pest problem</strong> are you having?<strong>Select all that apply.</strong>',
            'heading_format' => null,
            'optionButtonText' => 'More Options',
            'hideOptionsText' => 'Less Options',
            'submitButtonText' => 'Get My Solution',
            'minimizedConcernNumber' => 8,
            'uniqueFormId' => null
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state)
    {
        $form['heading'] = [
            '#type' => 'text_format',
            '#title' => $this->t('Heading'),
            '#default_value' => $this->configuration['heading'],
            '#format' => $this->configuration['heading_format']
        ];

        $form['minimizedConcernNumber'] = [
            '#type' => 'number',
            '#min' => 1,
            '#title' => $this->t('Maximum shown concerns'),
            '#description' => $this->t('Number of concerns showing when "less options" is chosen.'),
            '#default_value' => $this->configuration['minimizedConcernNumber']
        ];

        $form['submitButtonText'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Submit button text'),
            '#default_value' => $this->configuration['submitButtonText'],
            '#required' => true
        ];

        $form['optionButtonText'] = [
            '#type' => 'textfield',
            '#title' => $this->t('More Options button text'),
            '#default_value' => $this->configuration['optionButtonText'],
            '#required' => true
        ];

        $form['hideOptionsText'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Less Options button text'),
            '#default_value' => $this->configuration['hideOptionsText'],
            '#required' => true
        ];

        $concerns = $this->solutionFinderService->getConcerns();
        $concernOpts = [];
        /** @var Node $concern */
        foreach($concerns as $concern) {
            $concernOpts[$concern->uuid()] = $concern->label() . " [{$concern->id()}]";
        }

        $solutions = $this->solutionFinderService->getSolutions();
        $solutionOpts = [];
        /** @var SolutionInterface $solution */
        foreach($solutions as $solution) {
            $solutionOpts[$solution->uuid()] = $solution->label();
        }

        $form['excludedConcernUuids'] = [
            '#type' => 'select',
            '#multiple' => true,
            '#options' => $concernOpts,
            '#title' => $this->t('Excluded concerns'),
            '#description' => $this->t('Exclude these concerns from the checklist.'),
            '#default_value' => $this->configuration['excludedConcernUuids']
        ];


        $form['excludedSolutionUuids'] = [
            '#type' => 'select',
            '#multiple' => true,
            '#options' => $solutionOpts,
            '#title' => $this->t('Excluded solutions'),
            '#description' => $this->t('Exclude these solutions from being used to determine where this finder goes.'),
            '#default_value' => $this->configuration['excludedSolutionUuids']
        ];

        $form['defaultDestination'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Default destination'),
            '#description' => $this->t('The default destination this finder will go to if no solution is found.'),
            '#required' => true,
            '#default_value' => $this->configuration['defaultDestination']
        ];

        return $form;
    }

    public function blockValidate($form, FormStateInterface $form_state)
    {
        $destination = $form_state->getValue('defaultDestination');
        if(empty($destination)) {
            $form_state->setError($form['defaultDestination'], $this->t('A default destination is required.'));
        }
        else {
            $destinationTest = $destination;
            if(strpos($destinationTest, '[') !== false){
                $destinationTest = \Drupal::token()->replace($destinationTest);
                if(empty($destinationTest)) {
                    $destinationTest = '/'; //assume the token will work
                }
            }
            /** @var PathValidatorInterface $pathValidator */
            $pathValidator = \Drupal::service('path.validator');
            $valid = $pathValidator->isValid($destinationTest);
            if(empty($valid)) {
                $form_state->setError($form['defaultDestination'], $this->t('A valid destination is required.'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state)
    {
        if(!isset($this->configuration['uniqueFormId'])) {
            $this->configuration['uniqueFormId'] = $this->uuidGenerator->generate();
        }
        $default = $this->defaultConfiguration();
        foreach ($default as $key => $defaultValue) {
            if($key == 'heading') {
                $array = $form_state->getValue($key, []);
                $this->configuration[$key] = trim($array['value']) ?? null;
            }
            else if($key == 'heading_format') {
                $array = $form_state->getValue('heading', []);
                $this->configuration[$key] = $array['format'] ?? null;
            }
            else if($key != 'uniqueFormId') {
                $this->configuration[$key] = $form_state->getValue($key);
            }
        }
    }

    protected function getFormConcerns() {
        $concerns = $this->solutionFinderService->getDraggableWeightSortedConcerns();
        $uuids = $this->configuration['excludedConcernUuids'];
        $return = [];
        foreach($concerns as $uuid => $concern) {
            if(!in_array($uuid, $uuids)) {
                $return[$uuid] = $concern;
            }
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        if(is_array($this->configuration['heading'])) {
            $this->configuration['heading'] = $this->configuration['heading']['value'] ?? '';
        }
        if(!empty($this->configuration['heading'])) {
            $build['heading'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('<div class="heading">' . $this->configuration['heading'] . '</div>')
            ];
        }
        $build['form'] = $this->formBuilder->getForm($this);
        return $build;
    }

    public function getFormId()
    {
        $uuid = $this->configuration['uniqueFormId'] ?? 'temp';
        return 'solution_finder__' . $uuid;
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $routeMatch = \Drupal::routeMatch();
        $node = $routeMatch->getParameter('node');
        if(!empty($node)) {
            if(!$node instanceof Node) {
                $node = Node::load($node);
            }
        }

        $form['nid'] = [
            '#type' => 'value',
            '#value' => $node instanceof Node ? $node->id() : '',
        ];

        $form['solution_finder_finder_form'] = [
            '#type' => 'value',
            '#value' => true
        ];
        $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
        $list = [
            '#type' => 'html_tag',
            '#tag' => 'ul',
            '#attributes' => [
                'class' => ['concern-list']
            ]
        ];
        $i = 0;
        $concerns = $this->getFormConcerns();
        $countConcerns = count($concerns);
        if(empty($countConcerns)) {
            return [];
        }
        /** @var Node $concern */
        foreach($concerns as $concern) {
            ++$i;
            $countConcerns = $i;
            $listItem = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                '#attributes' => [
                    'class' => [
                        'concern-list-item',
                        'index-' . $i,
                        'concern-list-item--' . $concern->uuid()
                    ]
                ]
            ];
            if($i > $this->configuration['minimizedConcernNumber']) {
                $listItem['#attributes']['class'][] = 'hide-default';
            }
            $inputId = 'checkbox--' . $concern->uuid();
            $label = [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                    'data-for' => $inputId,
                    'class' => ['solution-finder-item-wrap']
                ]
            ];
            $label[$inputId] = [
                '#type' => 'checkbox',
                '#default_value' => false,
                '#attributes' => [
                    'id' => $inputId,
                    'class' => ['concern-check']
                ]
            ];
            $label['concern--' . $concern->uuid()] = $viewBuilder->view($concern, 'solution_finder');
            $listItem['label--' . $concern->uuid()] = $label;
            $list['item--' . $concern->uuid()] = $listItem;
        }

        $form['list'] = $list;
        $form['actions'] = [
            '#attributes' => [
                'class' => ['solution-finder-actions']
            ]
        ];
        if($countConcerns > $this->configuration['minimizedConcernNumber']) {
            $form['actions']['more'] = [
                '#type' => 'submit',
                '#value' => $this->configuration['optionButtonText'],
                '#attributes' => [
                    'class' => ['more-options-button', 'button'],
                    'data-hide-options-text' => $this->configuration['hideOptionsText'],
                    'data-show-options-text' => $this->configuration['optionButtonText']
                ]
            ];
        }

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->configuration['submitButtonText'],
            '#attributes' => [
                'class' => ['submit-button', 'button']
            ]
        ];

        $form['#attributes'] = [
            'class' => ['solution-finder-concerns']
        ];

        $form['#attached']['library'][] = 'solution_finder/solution_finder';

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $uuids = [];
        foreach($values as $name => $val) {
            if(strpos($name, 'checkbox--') === 0 && $val) {
                $uuids[] = str_replace('checkbox--', '', $name);
            }
        }
        if(empty($uuids)) {
            $form_state->setError($form, $this->t('At least one item must be checked.'));
        }
        else {
            $form_state->set('concernUuids', $uuids);
        }

    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $nid = $form_state->getValue('nid');
        $node = null;
        if(!empty($nid)) {
            $node = Node::load($nid);
        }
        $concernUuids = $form_state->get('concernUuids');
        $map = $this->getFormConcerns();
        $concernIds = [];
        /** @var Node $concern */
        foreach($map as $concern) {
            if(in_array($concern->uuid(), $concernUuids)) {
                $concernIds[] = $concern->id();
            }
        }
        $solution = $this->solutionFinderService->findSolution($concernUuids, $this->configuration['excludedSolutionUuids'] ?? [], $form_state);
        /** @var PathValidatorInterface $pathValidator */
        $pathValidator = \Drupal::service('path.validator');
        $url = $pathValidator->getUrlIfValid($this->configuration['defaultDestination']);
        if($solution instanceof Solution) {
            $solutionPage = $solution->getSolutionPage($node);
            if($solutionPage instanceof Node) {
                $url = Url::fromRoute('entity.node.canonical', [
                    'node' => $solutionPage->id()
                ]);
            }
        }
        if($url instanceof Url) {
            $url->setOptions([
                'query' => [
                    'c' => $concernIds
                ]
            ]);
            $form_state->setRedirectUrl($url);
        }
    }

    /**
     * Disable block if not enabled in sprowt settings
     * @param AccountInterface $account
     * @return AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
     */
    public function blockAccess(AccountInterface $account)
    {
        /** @var ModuleHandler $moduleHandler */
        $moduleHandler = \Drupal::service('module_handler');
        if($moduleHandler->moduleExists('sprowt_settings')) {
            /** @var \Drupal\sprowt_settings\SprowtSettings $service */
            $service = \Drupal::service('sprowt_settings.manager');
            return AccessResult::allowedIf($service->getSetting('solution_finder_enabled', false));
        }
        return parent::blockAccess($account);
    }
}
