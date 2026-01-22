<?php

namespace Drupal\color_variables;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the theme color variables entity type.
 */
class ColorVariableItemListBuilder extends EntityListBuilder
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
     * Constructs a new ColorVariableItemListBuilder object.
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

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        $build['table'] = parent::render();

        $total = $this->getStorage()
            ->getQuery()
            ->accessCheck(false)
            ->count()
            ->execute();

        $build['summary']['#markup'] = $this->t('Total theme color variableses: @total', ['@total' => $total]);
        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHeader()
    {
        $header['id'] = $this->t('Theme');
        $header['created'] = $this->t('Created');
        $header['changed'] = $this->t('Updated');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity)
    {
        /* @var $entity \Drupal\color_variables\ColorVariableItemInterface */
        $row['id'] = $entity->toLink(null, 'edit-form');
        $row['created'] = $this->dateFormatter->format($entity->getCreatedTime());
        $row['changed'] = $this->dateFormatter->format($entity->getChangedTime());
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
        $operations['revision'] = [
            'title' => $this->t('Revisions'),
            'weight' => 100,
            'url' => $this->ensureDestination($entity->toUrl('version-history')),
        ];
        return $operations;
    }

}
