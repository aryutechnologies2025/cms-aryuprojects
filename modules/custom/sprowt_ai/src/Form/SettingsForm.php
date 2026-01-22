<?php declare(strict_types=1);

namespace Drupal\sprowt_ai\Form;

use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Configure Sprowt AI settings for this site.
 */
final class SettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return 'sprowt_ai_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array
    {
        return ['sprowt_ai.settings'];
    }

    public function claudeSettings(array $form, FormStateInterface $form_state) {
        $config = $this->config('sprowt_ai.settings');
        $claudeSettings = $config->get('claude3') ?? [];
        $service = \Drupal::service('sprowt_ai.claude_3');

        $form['ClaudeVersion'] = [
            '#type' => 'item',
            '#title' => 'Claude model',
            '#markup' => $service->getModel()
        ];

        $form['api_key'] = [
            '#type' => 'textfield',
            '#title' => 'Api key',
            '#default_value' => $claudeSettings['api_key'] ?? '',
        ];

        $form['usage'] = [
            '#type' => 'fieldset',
            '#title' => 'Site api usage'
        ];

        $state = \Drupal::state();
        $currentSiteUsage = $state->get('claude_3.site_usage', []);
        $now = new \DateTime();
        $times = [
            'year' => $now->format('Y'),
            'month' => $now->format('Y-m'),
            'day' => $now->format('Y-m-d'),
            'minute' => $now->format('Y-m-d H:i') . ':00'
        ];
        foreach($times as $timeType => $time) {
            $usage = $currentSiteUsage[$timeType . '-usage'] ?? [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total' => 0
            ];
            if(empty($currentSiteUsage[$timeType]) || $currentSiteUsage[$timeType] != $time) {
                $usage = [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total' => 0
                ];
            }
            $form['usage'][$timeType] = [
                '#type' => 'item',
                '#title' => 'Used per ' . $timeType
            ];
            $markup = '';
            $markup .= '<div style="font-size: .8em"><strong>Input tokens:</strong><span style="margin-left:15px">';
            $markup .= (string) $usage['input_tokens'] ?? '0';
            $markup .= '</span></div>';

            $markup .= '<div style="font-size: .8em"><strong>Output tokens:</strong><span style="margin-left:15px">';
            $markup .= (string) $usage['output_tokens'] ?? '0';
            $markup .= '</span></div>';

            $markup .= '<div style="font-size: .8em"><strong>Total:</strong><span style="margin-left:15px">';
            $markup .= (string) $usage['total'] ?? '0';
            $markup .= '</span></div>';
            $markup .= '';
            $form['usage'][$timeType]['#markup'] = Markup::create($markup);
        }


        if(!empty($claudeSettings['api_key'])) {
            $form['tester'] = [
                '#type' => 'link',
                '#url' => Url::fromRoute('sprowt_ai.claude_tester'),
                '#title' => 'Test api',
                '#attributes' => [
                    'class' => ['button'],
                    'target' => '_blank'
                ]
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        /** @var FieldTypePluginManager $fieldTypeManager */
        $fieldTypeManager = \Drupal::service('plugin.manager.field.field_type');

        $definitions = $fieldTypeManager->getDefinitions();
        $fieldTypes = [];
        foreach ($definitions as $definition) {
            $fieldTypes[$definition['id']] = $definition['label'];
        }

        $form['supported_field_types'] = [
            '#type' => 'select',
            '#title' => 'Supported field types',
            '#description' => 'Field types that will take generated content',
            '#options' => $fieldTypes,
            '#multiple' => true,
            '#default_value' => $this->config('sprowt_ai.settings')->get('supported_field_types') ?? [],
        ];



        $form['claude3'] = [
            '#type' => 'fieldset',
            '#title' => 'Claude 3 Settings',
            '#tree' => true,
        ];
        $form['claude3'] = $this->claudeSettings($form['claude3'], $form_state);


        $form = parent::buildForm($form, $form_state);
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // @todo Validate the form here.
        // Example:
        // @code
        //   if ($form_state->getValue('example') === 'wrong') {
        //     $form_state->setErrorByName(
        //       'message',
        //       $this->t('The value is not correct.'),
        //     );
        //   }
        // @endcode
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $config = $this->config('sprowt_ai.settings');
        $claudeSettings = $form_state->getValue('claude3');
        unset($claudeSettings['ClaudeVersion']);
        $fieldTypes = $form_state->getValue('supported_field_types');
        $config->set('supported_field_types', array_values($fieldTypes));
        $config->set('claude3', $claudeSettings);
        $config->save();



        parent::submitForm($form, $form_state);
    }

}
