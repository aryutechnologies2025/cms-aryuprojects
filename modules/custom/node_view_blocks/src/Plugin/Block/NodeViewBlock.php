<?php

namespace Drupal\node_view_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'NodeViewBlock' block.
 *
 * @Block(
 *  id = "node_view_block",
 *  admin_label = @Translation("Node view block"),
 * )
 */
class NodeViewBlock extends BlockBase implements ContainerFactoryPluginInterface {

    /**
     * Drupal\Core\Routing\CurrentRouteMatch definition.
     *
     * @var \Drupal\Core\Routing\CurrentRouteMatch
     */
    protected $currentRouteMatch;

    /**
     * Drupal\Core\Entity\EntityTypeManagerInterface definition.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
     */
    protected $displayRepository;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        $instance = new static($configuration, $plugin_id, $plugin_definition);
        $instance->currentRouteMatch = $container->get('current_route_match');
        $instance->entityTypeManager = $container->get('entity_type.manager');
        $instance->displayRepository = $container->get('entity_display.repository');
        return $instance;
    }

    public function defaultConfiguration() {
        return [
            'view_mode' => 'full'
        ];
    }

    public function blockForm($form, FormStateInterface $form_state) {
        $viewModes = $this->displayRepository->getViewModes('node');

        $options = [];
        foreach($viewModes as $viewMode => $vArray) {
            $options[$viewMode] = $vArray['label'];
        }
        asort($options);

        $form['viewMode'] = [
            '#type' => 'select',
            '#title' => 'View Mode',
            '#options' => $options,
            '#default_value' => $this->configuration['view_mode']
        ];

        return $form;
    }

    public function blockSubmit($form, FormStateInterface $form_state) {
        if (!$form_state->getErrors()) {
            $this->configuration['view_mode'] = $form_state->getValue('viewMode');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        $build = [];
        $node = $this->currentRouteMatch->getParameter('node');
        if(!empty($node)){
            $display = $this->configuration['view_mode'] ?? 'full';
            $build = $this->entityTypeManager
                ->getViewBuilder('node')
                ->view($node, $display);
        }

        return $build;
    }

}
