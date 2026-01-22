<?php declare(strict_types=1);

namespace Drupal\sprowt_ai;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the example entity type.
 */
final class SprowtAiExampleListBuilder extends EntityListBuilder
{

    /**
     * {@inheritdoc}
     */
    public function buildHeader(): array
    {
        $header['id'] = $this->t('ID');
        $header['label'] = $this->t('Label');
        $header['created'] = $this->t('Created');
        $header['changed'] = $this->t('Updated');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity): array
    {
        /** @var \Drupal\sprowt_ai\SprowtAiExampleInterface $entity */
        $row['id'] = $entity->id();
        $row['label'] = $entity->label();
        $row['created']['data'] = $entity->get('created')->view(['label' => 'hidden']);
        $row['changed']['data'] = $entity->get('changed')->view(['label' => 'hidden']);
        return $row + parent::buildRow($entity);
    }

}
