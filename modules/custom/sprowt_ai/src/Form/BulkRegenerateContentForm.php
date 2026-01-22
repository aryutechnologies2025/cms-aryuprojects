<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\sprowt_ai\AiService;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a Sprowt AI form.
 */
final class BulkRegenerateContentForm extends ConfirmFormBase
{

    protected $ids = [];

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_bulk_regenerate_content';
    }

    public function getQuestion(): string
    {
        $count = count($this->ids);
        return "Bulk regenerate content for {$count} node(s)?";
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {

        $currentUser = $this->currentUser();
        $ids = \Drupal::service('tempstore.private')->get('bulk_regenerate_nodes')->get('bulk_regenerate_nodes.' . $currentUser->id(), []);
        $this->ids = $ids;

        $form = parent::buildForm($form, $form_state);

        $form['description']['nodes'] = [
            '#type' => 'html_tag',
            '#tag' => 'ul',
            '#prefix' => '<p>The following nodes will have their content regenerated:</p>'
        ];

        $nodeBundleLabels = [];
        $bundleType = \Drupal::entityTypeManager()->getDefinition('node')->getBundleEntityType();
        $bundles = \Drupal::entityTypeManager()->getStorage($bundleType)->loadMultiple();
        foreach ($bundles as $bundle) {
            $nodeBundleLabels[$bundle->id()] = $bundle->label();
        }
        $nodes = Node::loadMultiple($ids);
        foreach ($nodes as $node) {
            $listLabel = $nodeBundleLabels[$node->bundle()] . ": " .  $node->label();
            $form['description']['nodes']['node_' . $node->id()] = [
                '#type' => 'html_tag',
                '#tag' => 'li',
                '#value' => $listLabel,
            ];
        }
        return $form;
    }

    public function getCancelUrl()
    {
        return Url::fromUserInput('/admin/content');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $nodes = Node::loadMultiple($this->ids);
        $total = count($nodes);

        $batchBuilder = new BatchBuilder();
        $batchBuilder->setTitle("Regenerating content...");
        foreach ($nodes as $node) {
            $batchBuilder->addOperation([static::class, 'regenerateNodeContent'], [
                $node,
                $total,
            ]);
        }

        $batchBuilder->setFinishCallback([static::class, 'finishCallback']);
        batch_set($batchBuilder->toArray());
    }

    public static function regenerateNodeContent($node, $total, &$context) {
        $storage = &$context['sandbox'];
        if(!empty($storage['processing'])) {
            $context['finished'] = 0;
            return;
        }
        $storage['processing'] = true;
        /** @var AiService $sprowtAiService */
        $sprowtAiService = \Drupal::service('sprowt_ai.service');
        if($sprowtAiService->entityHasPrompts($node)) {
            $sprowtAiService->generateContentForEntity($node);
        }

        if(empty($context['results'])) {
            $context['results'] = [];
        }
        $context['results'][] = $node->id();
        $done = count($context['results']);
        $context['message'] = "$done out of $total node(s) processed";
        $storage['processing'] = false;
        $context['finished'] = 1;
    }

    public static function finishCallback($success, $results, $operations) {
        if ($success) {
            // Here we do something meaningful with the results.
            $message = t('@count node(s) successfully processed', array('@count' => count($results)));
            \Drupal::messenger()->addStatus($message);
        }
        else {
            // An error occurred.
            // $operations contains the operations that remained unprocessed.
            $error_operation = reset($operations);
            $message = t('An error occurred while processing %error_operation with arguments: @arguments', array('%error_operation' => $error_operation[0], '@arguments' => print_r($error_operation[1], TRUE)));
            \Drupal::messenger()->addError($message);
        }



        $currentUser = \Drupal::currentUser();
        \Drupal::service('tempstore.private')->get('bulk_regenerate_nodes')->delete('bulk_regenerate_nodes.' . $currentUser->id());
        $url = Url::fromUserInput('/admin/content');
        return new RedirectResponse($url->toString());
    }

}
