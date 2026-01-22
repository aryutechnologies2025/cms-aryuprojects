<?php

namespace Drupal\solution_finder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\solution_finder\Entity\Solution;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;

/**
 * Provides a list controller for the solution entity type.
 */
class SolutionListBuilder extends EntityListBuilder implements FormInterface
{

    /**
     * The date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatterInterface
     */
    protected $dateFormatter;

    /**
     * The redirect destination service.
     *
     * @var \Drupal\Core\Routing\RedirectDestinationInterface
     */
    protected $redirectDestination;

    /**
     * Name of the entity's weight field or FALSE if no field is provided.
     *
     * @var string|bool
     */
    protected $weightKey = 'weight';

    /**
     * The key to use for the form element containing the entities.
     *
     * @var string
     */
    protected $entitiesKey = 'solutions';

    /**
     * The form builder.
     *
     * @var \Drupal\Core\Form\FormBuilderInterface
     */
    protected $formBuilder;

    protected $entities = [];

    public function getFormId()
    {
        return 'solutions_collection';
    }

    /**
     * Constructs a new SolutionListBuilder object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param \Drupal\Core\Entity\EntityStorageInterface $storage
     *   The entity storage class.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
     *   The date formatter service.
     * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
     *   The redirect destination service.
     */
    public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter, RedirectDestinationInterface $redirect_destination)
    {
        parent::__construct($entity_type, $storage);
        $this->dateFormatter = $date_formatter;
        $this->redirectDestination = $redirect_destination;
    }

    /**
     * {@inheritdoc}
     */
    public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type)
    {
        return new static(
            $entity_type,
            $container->get('entity_type.manager')->getStorage($entity_type->id()),
            $container->get('date.formatter'),
            $container->get('redirect.destination')
        );
    }

    public function load()
    {
        $entities = parent::load();
        uasort($entities, [Solution::class, 'sort']);
        return $entities;
    }

//    /**
//     * {@inheritdoc}
//     */
//    public function render()
//    {
//        $build['table'] = parent::render();
//
//        $total = $this->getStorage()
//            ->getQuery()
//            ->count()
//            ->execute();
//
//        $build['summary']['#markup'] = $this->t('Total solutions: @total', ['@total' => $total]);
//        return $build;
//    }

    /**
     * {@inheritdoc}
     */
    public function buildHeader()
    {
        $header['id'] = $this->t('ID');
        $header['labelText'] = $this->t('Label');
        $header['status'] = $this->t('Status');
        $header['created'] = $this->t('Created');
        $header['changed'] = $this->t('Updated');
        $header['weight'] = t('Weight');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity)
    {
        /* @var $entity \Drupal\solution_finder\SolutionInterface */
        $row['id'] = $entity->id();
        $row['labelText'] = $entity->label();
        $row['status'] = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
        $row['created'] = $this->dateFormatter->format($entity->getCreatedTime());
        $row['changed'] = $this->dateFormatter->format($entity->getChangedTime());
        foreach($row as $key => $val) {
            $row[$key] = [
                '#type' => 'markup',
                '#markup' => Markup::create($val)
            ];
        }

        $row['#attributes']['class'][] = 'draggable';
        $row['#weight'] = $entity->getWeight();
        // Add weight column.
        $row['weight'] = [
            '#type' => 'weight',
            '#title' => t('Weight for @title', ['@title' => $entity->label()]),
            '#title_display' => 'invisible',
            '#default_value' => $entity->getWeight(),
            '#attributes' => ['class' => ['weight']],
        ];

        return $row + parent::buildRow($entity);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOperations(EntityInterface $entity)
    {
        $operations = parent::getDefaultOperations($entity);
        $destination = $this->redirectDestination->getAsArray();
        foreach ($operations as $key => $operation) {
            $operations[$key]['query'] = $destination;
        }
        return $operations;
    }

    /**
     * {@inheritdoc}
     */
    public function render() {
        return $this->formBuilder()->getForm($this);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form[$this->entitiesKey] = [
            '#type' => 'table',
            '#header' => $this->buildHeader(),
            '#empty' => t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
            '#tabledrag' => [
                [
                    'action' => 'order',
                    'relationship' => 'sibling',
                    'group' => 'weight',
                ],
            ],
        ];

        $this->entities = $this->load();
        $delta = 10;
        // Change the delta of the weight field if have more than 20 entities.
        if (!empty($this->weightKey)) {
            $count = count($this->entities);
            if ($count > 20) {
                $delta = ceil($count / 2);
            }
        }
        foreach ($this->entities as $entity) {
            $row = $this->buildRow($entity);
            if (isset($row['label'])) {
                $row['label'] = ['#plain_text' => $row['label']];
            }
            if (isset($row['weight'])) {
                $row['weight']['#delta'] = $delta;
            }
            $form[$this->entitiesKey][$entity->id()] = $row;
        }

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => t('Save'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
     * Returns the form builder.
     *
     * @return \Drupal\Core\Form\FormBuilderInterface
     *   The form builder.
     */
    protected function formBuilder() {
        if (!$this->formBuilder) {
            $this->formBuilder = \Drupal::formBuilder();
        }
        return $this->formBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // No validation.
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        foreach ($form_state->getValue($this->entitiesKey) as $id => $value) {
            if (isset($this->entities[$id]) && $this->entities[$id]->getWeight() != $value['weight']) {
                // Save entity only when its weight was changed.
                $this->entities[$id]->setWeight($value['weight']);
                $this->entities[$id]->save();
            }
        }
    }

}
