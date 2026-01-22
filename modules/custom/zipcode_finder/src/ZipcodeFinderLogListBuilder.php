<?php

namespace Drupal\zipcode_finder;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\zipcode_finder\Entity\ZipcodeFinder;
use Drupal\zipcode_finder\Plugin\Field\FieldType\ZipcodeFinderComputedItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the zipcode finder log entity type.
 */
class ZipcodeFinderLogListBuilder extends EntityListBuilder
{

    /**
     * The date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatterInterface
     */
    protected $dateFormatter;

    /**
     * Constructs a new ZipcodeFinderLogListBuilder object.
     *
     * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
     *   The entity type definition.
     * @param \Drupal\Core\Entity\EntityStorageInterface $storage
     *   The entity storage class.
     * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
     *   The date formatter service.
     */
    public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter)
    {
        parent::__construct($entity_type, $storage);
        $this->dateFormatter = $date_formatter;
    }

    /**
     * {@inheritdoc}
     */
    public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type)
    {
        return new static(
            $entity_type,
            $container->get('entity_type.manager')->getStorage($entity_type->id()),
            $container->get('date.formatter')
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
            ->accessCheck(FALSE)
            ->count()
            ->execute();

        $build['summary']['#markup'] = $this->t('Total zipcode finder logs: @total', ['@total' => $total]);
        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHeader()
    {
        $header['zipcode'] = $this->t('Zipcode');
        $header['count'] = $this->t('Count');
        $header['finder'] = $this->t('Zipcode finder');
        $header['created'] = $this->t('Created');
        $header['changed'] = $this->t('Updated');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity)
    {
        /** @var ZipcodeFinderComputedItemList $finderList */
        $finderList = $entity->zipcode_finder;
        $value = $finderList->first();
        $finder = $finderList->entity;
        $finderLink = '--';
        if($finder instanceof ZipcodeFinder) {
            $url = $finder->toUrl('edit-form');
            $finderLink = [
                '#type' => 'link',
                '#title' => $finder->label(),
                '#url' => $url,
                '#attributes' => [
                    'target' => '_blank'
                ]
            ];
        }
        /** @var \Drupal\zipcode_finder\ZipcodeFinderLogInterface $entity */
        $row['zipcode'] = $entity->id();
        $row['count'] = $entity->count->value;
        $row['finder'] = ['data' => $finderLink];
        $row['created'] = $this->dateFormatter->format($entity->get('created')->value);
        $row['changed'] = $this->dateFormatter->format($entity->getChangedTime());
        return $row + parent::buildRow($entity);
    }

}
