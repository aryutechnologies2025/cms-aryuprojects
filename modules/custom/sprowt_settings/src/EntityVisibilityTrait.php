<?php

namespace Drupal\sprowt_settings;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\node\Entity\Node;
use Drupal\system\Plugin\Condition\RequestPath;

trait EntityVisibilityTrait
{
    /**
     * The visibility collection.
     *
     * @var \Drupal\Core\Condition\ConditionPluginCollection
     */
    protected $visibilityCollection;

    /**
     * {@inheritdoc}
     */
    public function getPluginCollections() {
        return [
            'visibility' => $this->getVisibilityConditions(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility() {
        return $this->getVisibilityConditions()->getConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibilityConfig($instance_id, array $configuration) {
        $conditions = $this->getVisibilityConditions();
        if (!$conditions->has($instance_id)) {
            $configuration['id'] = $instance_id;
            $conditions->addInstanceId($instance_id, $configuration);
        }
        else {
            $conditions->setInstanceConfiguration($instance_id, $configuration);
        }
        $this->set('visibility', $conditions->getConfiguration());
        return $this;
    }

    public function removeVisibilityConfig($instance_id) {
        $visibility = $this->getVisibility();
        if(isset($visibility[$instance_id])) {
            unset($visibility[$instance_id]);
            $this->set('visibility', $visibility);
        }

        return $this;
    }

    public function getVisibilityValue() {
        $val = $this->get('visibility');
        if(empty($val)) {
            return [];
        }

        if($val instanceof MapFieldItemList) {
            if($val->isEmpty()) {
                return [];
            }
            return $val->first()->getValue() ?? [];
        }

        //if field has deltas return first value
        if(array_keys($val) === range(0, count($val) - 1)) {
            return array_shift($val);
        }

        return $val;
    }

    /**
     * Gets the condition plugin manager.
     *
     * @return \Drupal\Core\Executable\ExecutableManagerInterface
     *   The condition plugin manager.
     */
    protected function conditionPluginManager() {
        if (!isset($this->conditionPluginManager)) {
            $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
        }
        return $this->conditionPluginManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibilityConditions() {
        if (!isset($this->visibilityCollection)) {
            $this->visibilityCollection = new ConditionPluginCollection($this->conditionPluginManager(), $this->getVisibilityValue());
        }
        return $this->visibilityCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibilityCondition($instance_id) {
        return $this->getVisibilityConditions()->get($instance_id);
    }

    public function isVisible($node = null) {
        $conditions = $this->getVisibilityConditions();
        $show = true;
        /** @var ContextRepositoryInterface $contextRepo */
        $contextRepo = \Drupal::service('context.repository');
        $contextHandler = \Drupal::service('context.handler');
        /** @var ConditionInterface $condition */
        foreach ($conditions as $condition) {
            $missing_value = false;
            $missing_context = false;
            if($condition instanceof ContextAwarePluginInterface) {
                try {
                    $contexts = $contextRepo->getRuntimeContexts(array_values($condition->getContextMapping()));
                    $contextHandler->applyContextMapping($condition, $contexts);
                }
                catch (MissingValueContextException $e) {
                    $missing_value = true;
                }
                catch (ContextException $e) {
                    $missing_context = true;
                }
            }
            if($node instanceof Node && $condition instanceof ConditionPluginBase) {
                //apply provided node as a context
                $nodeContext = EntityContext::fromEntity($node, 'entity:node');
                $condition->setContext('node', $nodeContext);
            }
            if($condition instanceof RequestPath) {
                $config = $condition->getConfig();
                if(empty($config['pages']) && !empty($config['negate'])) {
                    //empty pages and negate (hide for listed) not working working correctly for some reason.
                    //so set pages to some random value
                    $config['pages'] = '/' . sha1(time() . rand() . 'show all pages');
                    $condition->setConfiguration($config);
                }
            }
            try {
                $show = $show && $condition->execute();
            }
            catch (ContextException $e) {
                // If a condition is missing context and is not negated, consider that a
                // fail.
                $show = $condition->isNegated();
            }
            catch (\Exception $e) {
                \Drupal::logger('entity_visibility')->error("Visibility condition error: " . $e->getMessage() . ' @backtrace_string', [
                    '@backtrace_string' => $e->getTraceAsString()
                ]);
                $show = $condition->isNegated();
            }
        }

        return $show;
    }
}
