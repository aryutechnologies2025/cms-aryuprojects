<?php

namespace Drupal\script_inserter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the script entity type.
 */
class ScriptListBuilder extends EntityListBuilder
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
     * Constructs a new ScriptListBuilder object.
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

        $build['summary']['#markup'] = $this->t('Total scripts: @total', ['@total' => $total]);
        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHeader()
    {
        $header['label'] = $this->t('Label');
        $header['status'] = $this->t('Status');
        $header['changed'] = $this->t('Updated');
        $header['location'] = $this->t('Location');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity)
    {
        /* @var $entity \Drupal\script_inserter\ScriptInterface */
        $row['label'] = $entity->label();
        $row['status'] = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
        $row['changed'] = $this->dateFormatter->format($entity->getChangedTime());
        $row['location'] = $entity->getLocationLabel();
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

}
