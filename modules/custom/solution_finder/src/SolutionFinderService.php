<?php

namespace Drupal\solution_finder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorage;
use Drupal\solution_finder\Entity\Solution;
use Drupal\views\ResultRow;
use Drupal\views\Views;

/**
 * SolutionFinderService service.
 */
class SolutionFinderService
{

    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    protected $solutionPageTypes = [
        'solution_page_packages',
        'solution_page_package_service',
        'solution_page_services',
        'solution_page_package_alt'
    ];

    /**
     * Constructs a SolutionFinderService object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     */
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }

    public function sortByLabel(EntityInterface $a, EntityInterface $b)
    {
        $aVal = $a->label();
        $bVal = $b->label();
        return strcmp($aVal, $bVal);
    }

    protected function getNodeMap($bundle)
    {
        /** @var NodeStorage $storage */
        $storage = $this->entityTypeManager->getStorage('node');
        $entities = $storage->loadByProperties([
                'type' => $bundle,
                'status' => 1
            ]) ?? [];
        $return = [];
        /** @var Node $entity */
        foreach($entities as $entity) {
            $return[$entity->uuid()] = $entity;
        }
        uasort($return, [$this, 'sortByLabel']);
        return $return;
    }

    public function getConcerns()
    {
        $map = $this->getNodeMap(['concern', 'issue']);
        /**
         * @var  $uuid
         * @var  $concern
         */
        foreach($map as $uuid => $concern) {
            $excluded = $concern->field_exclude_from_solution_find->value;
            if(!(empty($excluded) || $excluded === '0')) {
                unset($map[$uuid]);
            }
        }
        return $map;
    }

    public function getDraggableWeightSortedConcerns() {
        $view = Views::getView('content_sorting');
        $view->setDisplay('concern_sorting');
        $view->setExposedInput([
            'field_subsite_target' => [],
            'status' => '1',
            'field_exclude_from_solution_find_value' => '0'
        ]);
        $view->execute();
        $concernsAndIssues = $view->result;
        $weightMap = [];
        /** @var ResultRow $row */
        foreach($concernsAndIssues as $row) {
            $node = $row->_entity;
            $weightMap[$node->uuid()] = $row->draggableviews_structure_weight ?? 0;
        }
        $map = $this->getConcerns();
        uasort($map, function($a, $b) use ($weightMap) {
            $aVal = $weightMap[$a->uuid()] ?? 0;
            $bVal = $weightMap[$b->uuid()] ?? 0;
            if($aVal == $bVal) {
                $aLabel = $a->label();
                $bLabel = $b->label();
                return strcmp($aLabel, $bLabel);
            }
            return ($aVal < $bVal) ? -1 : 1;
        });
        return $map;
    }

    public function getSolutionPages()
    {
        return $this->getNodeMap($this->solutionPageTypes);
    }

    public function getSolutions($formState = null)
    {
        $solutions = $this->entityTypeManager->getStorage('solution')->loadByProperties([
            'status' => 1
        ]);
        $map = [];
        /** @var Solution $solution */
        foreach($solutions as $solution) {
            if($solution->isEnabled()) {
                $map[$solution->uuid()] = $solution;
            }
        }
        $context = [
            'formState' => $formState
        ];
        /** @var ModuleHandler $moduleHandler */
        $moduleHandler = \Drupal::service('module_handler');
        $moduleHandler->alter('solution_finder_get_solutions', $map, $context);
        uasort($map, [Solution::class, 'sort']);
        return $map;
    }

    public function findSolution(array $concernUuids, ?array $excludedSolutionUuids = [], $formState = null)
    {
        if(empty($concernUuids)) {
            return null;
        }

        $solutions = $this->getSolutions($formState);

        $lowestcount = null;
        $currentSolution = null;
        /** @var Solution $solution */
        foreach($solutions as $solution) {
            if(!in_array($solution->uuid(), $excludedSolutionUuids)) {
                $uuids = $solution->getConcernUuids();
                $diff = array_diff($concernUuids, $uuids);
                if(empty($diff)) {
                    $lowestcount = count($uuids);
                    if($currentSolution instanceof Solution) {
                        $currentcount = count($currentSolution->getConcernUuids());
                        if($currentcount != $lowestcount) {
                            if($lowestcount < $currentcount) {
                                $currentSolution = $solution;
                            }
                        }
                        else {
                            $currentWeight = $currentSolution->getWeight();
                            $newWeight = $solution->getWeight();
                            if($newWeight == $currentWeight) {
                                $currentChanged = $currentSolution->getChangedTime();
                                $solutionChanged = $solution->getChangedTime();
                                if($solutionChanged == $currentChanged) {
                                    $currentLabel = $currentSolution->label();
                                    $solutionLabel = $solution->label();
                                    if(strcmp($solutionLabel, $currentLabel) < 0) {
                                        $currentSolution = $solution;
                                    }
                                }
                                elseif ($solutionChanged > $currentChanged) {
                                    $currentSolution = $solution;
                                }
                            }
                            elseif ($newWeight < $currentWeight) {
                                $currentSolution = $solution;
                            }
                        }
                    }
                    else {
                        $currentSolution = $solution;
                    }
                }
            }
        }

        return $currentSolution;
    }

}
