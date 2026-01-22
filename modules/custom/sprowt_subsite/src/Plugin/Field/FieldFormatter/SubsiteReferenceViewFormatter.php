<?php

namespace Drupal\sprowt_subsite\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Subsite Reference View' formatter.
 *
 * @FieldFormatter(
 *   id = "sprowt_subsite_reference_view",
 *   label = @Translation("Subsite Reference View"),
 *   field_types = {
 *     "sprowt_subsite_reference"
 *   }
 * )
 */
class SubsiteReferenceViewFormatter extends FormatterBase
{

    /**
     * The number of times this formatter allows rendering the same entity.
     *
     * @var int
     */
    const RECURSIVE_RENDER_LIMIT = 20;

    /**
     * An array of counters for the recursive rendering protection.
     *
     * Each counter takes into account all the relevant information about the
     * field and the referenced entity that is being rendered.
     *
     * @see ::viewElements()
     *
     * @var array
     */
    protected static $recursiveRenderDepth = [];

    /**
     * {@inheritdoc}
     */
    public static function defaultSettings()
    {
        return [
                'view_mode' => 'default',
                'link' => FALSE,
            ] + parent::defaultSettings();
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(array $form, FormStateInterface $form_state)
    {
        /** @var EntityDisplayRepository $entityDisplayRepository */
        $entityDisplayRepository = \Drupal::service('entity_display.repository');
        $elements['view_mode'] = [
            '#type' => 'select',
            '#options' => $entityDisplayRepository->getViewModeOptionsByBundle('node', 'subsite'),
            '#title' => $this->t('View mode'),
            '#default_value' => $this->getSetting('view_mode'),
            '#required' => true,
        ];

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function settingsSummary() {
        /** @var EntityDisplayRepository $entityDisplayRepository */
        $entityDisplayRepository = \Drupal::service('entity_display.repository');
        $summary = [];

        $view_modes = $entityDisplayRepository->getViewModeOptionsByBundle('node', 'subsite');
        $view_mode = $this->getSetting('view_mode');
        $summary[] = $this->t('Rendered as @mode', ['@mode' => $view_modes[$view_mode] ?? $view_mode]);

        return $summary;
    }

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode)
    {
        $view_mode = $this->getSetting('view_mode');
        $elements = [];
        foreach ($items as $delta => $item) {
            if($item->target == '_main') {
                $elements[$delta] = [
                    '#type' => 'fieldset',
                    'link' => [
                        '#type' => 'link',
                        '#title' => 'To main site',
                        '#url' => Url::fromUri('internal:/')
                    ]
                ];
            }
            else {
                $entity = $item->entity;
                if($entity instanceof Node) {
                    $recursive_render_id = 'node'
                        . 'subsite'
                        . $items->getName()
                        . $items->getEntity()->id()
                        . $entity->getEntityTypeId()
                        . $entity->uuid();

                    if (isset(static::$recursiveRenderDepth[$recursive_render_id])) {
                        static::$recursiveRenderDepth[$recursive_render_id]++;
                    }
                    else {
                        static::$recursiveRenderDepth[$recursive_render_id] = 1;
                    }

                    // Protect ourselves from recursive rendering.
                    if (static::$recursiveRenderDepth[$recursive_render_id] > static::RECURSIVE_RENDER_LIMIT) {
                        \Drupal::logger('sprowt_subsite')->error('Recursive rendering detected when rendering entity %entity_type: %entity_id, using the %field_name field on the %parent_entity_type:%parent_bundle %parent_entity_id entity. Aborting rendering.', [
                            '%entity_type' => $entity->getEntityTypeId(),
                            '%entity_id' => $entity->id(),
                            '%field_name' => $items->getName(),
                            '%parent_entity_type' => $items->getEntity()->getEntityTypeId(),
                            '%parent_bundle' => $items->getEntity()->bundle(),
                            '%parent_entity_id' => $items->getEntity()->id(),
                        ]);
                        return $elements;
                    }

                    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
                    $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

                    // Add a resource attribute to set the mapping property's value to the
                    // entity's url. Since we don't know what the markup of the entity will
                    // be, we shouldn't rely on it for structured data such as RDFa.
                    if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
                        $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
                    }
                }
            }
        }

        return $elements;
    }

}
