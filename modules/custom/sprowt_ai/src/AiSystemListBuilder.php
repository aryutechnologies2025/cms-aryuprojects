<?php declare(strict_types=1);

namespace Drupal\sprowt_ai;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of ai systems.
 */
final class AiSystemListBuilder extends ConfigEntityListBuilder
{

    /**
     * {@inheritdoc}
     */
    public function buildHeader(): array
    {
        $header['label'] = $this->t('Label');
        $header['id'] = $this->t('Machine name');
        $header['is_default'] = $this->t('Default');
        $header['status'] = $this->t('Status');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity): array
    {
        /** @var \Drupal\sprowt_ai\AiSystemInterface $entity */
        $row['label'] = $entity->label();
        $row['id'] = $entity->id();
        $row['is_default'] = $entity->isDefault() ? $this->t('Yes') : $this->t('No');
        $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
        return $row + parent::buildRow($entity);
    }

}
