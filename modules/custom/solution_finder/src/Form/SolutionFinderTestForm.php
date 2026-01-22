<?php

namespace Drupal\solution_finder\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\solution_finder\Entity\Solution;
use Drupal\solution_finder\SolutionFinderService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Solution Finder form.
 */
class SolutionFinderTestForm extends FormBase
{

    /**
     * @var SolutionFinderService
     */
    protected $solutionFinderService;

    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    public function __construct(SolutionFinderService $solutionFinderService, EntityTypeManagerInterface $entityTypeManager) {
        $this->solutionFinderService = $solutionFinderService;
        $this->entityTypeManager = $entityTypeManager;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('solution_finder.service'),
            $container->get('entity_type.manager')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'solution_finder_solution_finder_test';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $concerns = $this->solutionFinderService->getDraggableWeightSortedConcerns();

        $concernOpts = [];
        foreach($concerns as $concern) {
            $concernOpts[$concern->uuid()] = $concern->label();
        }

        $logic = [
            'First, the finder gets all solutions using all of the selected concerns.',
            'Out of those it finds the solution(s) with the least number of concerns attached.',
            'If there\'s more than one of those, then it selects the solution with the highest priority (determined <a href="/admin/structure/solution/sort" target="_blank">here</a>).',
            'If the priority is not set or it is equal, then it uses the most recently updated solution.',
            'If the solutions were updated at the same time, then it uses the alphabetical order of the solution labels.',
            'If all of that is equal, then it uses the first solution it finds.'
        ];

        $form['description'] = [
            'intro' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#value' => $this->t('Use this form to test the solution finder logic. It will return the solution found using the selected concerns.'),
            ],
            'logicHeader' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#value' => $this->t('The logic for finding a solution is as follows:'),
            ],
            'logicList' => [
                '#type' => 'html_tag',
                '#tag' => 'ul'
            ]
        ];

        foreach($logic as $itemIdx => $item) {
            $form['description']['logicList']['item--' . $itemIdx] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                '#value' => $this->t($item)
            ];
        }

        $form['concerns'] = [
            '#type' => 'checkboxes',
            '#title' => t('Concerns'),
            '#options' => $concernOpts
        ];

        $form['result'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attrubutes' => [
                'class' => ['result-placeholder']
            ],
            '#prefix' => '<div id="result-wrap">',
            '#suffix' => '</div>'
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Send'),
            '#ajax' => [
                'callback' => [$this, 'getResult'],
                'event' => 'click',
                'wrapper' => 'result-wrap',
                'progress' => [
                    'type' => 'throbber',
                    'message' => 'fetching result'
                ]
            ]
        ];

        $form['#attached']['library'][] = 'solution_finder/test';

        return $form;
    }

    public function getResult(&$form, FormStateInterface $formState) {
        $concerns = $formState->getValue('concerns', []);
        $uuids = [];
        foreach($concerns as $uuid => $selected) {
            if(!empty($selected)) {
                $uuids[] = $uuid;
            }
        }
        $result = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['result-result']
            ],
            'result' => [
                '#type' => 'markup',
                '#markup' => Markup::create('<p>No solution found. Default destination will be used.</p>')
            ],
            '#prefix' => '<div id="result-wrap">',
            '#suffix' => '</div>'
        ];
        $solution = $this->solutionFinderService->findSolution($uuids);
        if($solution instanceof Solution) {
            $result['result']['#markup'] = Markup::create('<h2>Solution found!</h2>');
            $viewBuilder = $this->entityTypeManager->getViewBuilder('solution');
            $build = $viewBuilder->view($solution);
            $result['result']['solution'] = $build;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
    }

}
