<?php declare(strict_types=1);

namespace Drupal\sprowt_ai;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Utility\Error;
use Drupal\sprowt_ai\Entity\AiSystem;
use Drupal\sprowt_ai\Event\Claude3ErrorEvent;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Client\ClientInterface;

/**
 * @todo Add class description.
 */
class Claude3Service
{

    protected ClientInterface $httpClient;

    protected Config $config;

    protected $baseUrl = 'https://api.anthropic.com';

    protected $anthropicVersion = '2023-06-01';

    protected $model = 'claude-3-7-sonnet-20250219';

    /**
     * Constructs a Claude3Service object.
     */
    public function __construct(
        ClientInterface $httpClient,
        ConfigFactoryInterface $configFactory
    )
    {
        $this->httpClient = $httpClient;
        $this->config = $configFactory->get('sprowt_ai.settings');
    }

    public function getAnthropicVersion(): string
    {
        return $this->anthropicVersion;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getApiKey(): ?string
    {
        return $this->config->get('claude3.api_key') ?? null;
    }


    public function isEnabled() {
        $apiKey = $this->getApiKey();
        return !empty($apiKey);
    }

    public function request(string $method, string $endpoint, array $data = [], array $clientOptions = [])
    {
        $method = strtoupper($method);
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return;
        }
        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => $this->anthropicVersion,
        ];

        if(!empty($clientOptions['headers'])) {
            $headers = array_merge($headers, $clientOptions['headers']);
        };

        $clientOptions['headers'] = $headers;
        if($method == 'GET') {
            $clientOptions['query'] = $data;
        }
        else {
            $clientOptions['json'] = $data;
        }

        $url = $this->baseUrl . $endpoint;

        try {
            $response = $this->httpClient->request($method, $url, $clientOptions);
        }
        catch (\Exception $e) {
            /** @var ContainerAwareEventDispatcher $dispatcher */
            $dispatcher = \Drupal::service('event_dispatcher');
            $dispatcher->dispatch(new Claude3ErrorEvent($e), Claude3ErrorEvent::EVENT_NAME);
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        $json = $response->getBody()->getContents();
        $result = json_decode($json, true);
        return $result;
    }

    public static function postTokenReplacement(&$replacements, $data, $options, $bubbleable_metadata)
    {

        foreach($replacements as $token => $replacement) {
            $tokenInfo = \Drupal::token()->scan($token);
            $types = array_keys($tokenInfo);
            $type = $types[0];
            if($type != 'sprowt_ai') {
                //keep all tokens but sprowt_ai tokens
                unset($replacements[$token]);
            }
        }
    }

    public function preparePrompt(string $promptText, array $options = [])
    {
        $tokenData = [];
        if(!empty($options['tokenData'])) {
            $tokenData = $options['tokenData'];
            foreach($tokenData as $key => $datum) {
                if(!is_object($datum)) {
                    $tokenData[$key] = \Drupal::entityTypeManager()->getStorage($key)->load($datum);
                }
                if(!isset($tokenData[$key])) {
                    unset($tokenData[$key]);
                }
            }
        }

        $tokenOptions = [
            'callback' => [static::class, 'postTokenReplacement'],
        ];

        $references = $options['references'] ?? [];
        $tokenOptions['sprowt_ai.references'] = $references;

        $promptText = \Drupal::service('token')->replace($promptText, $tokenData, $tokenOptions);
        return $promptText;
    }

    public function generateContent(string $promptText, array $options = []) {

        $promptText = $this->preparePrompt($promptText, $options);
        if(!empty($options['preprompt'])) {
            $promptText = $options['preprompt'] . "\n" . $promptText;
        }

        $params = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $promptText
                ],
            ],
            'max_tokens' => 4096,
        ];
        if(!empty($options['max_tokens'])) {
            $params['max_tokens'] = (int) $options['max_tokens'] + 5; //the 5 is to handle the response tags
        }

        if(!empty($options['systemId'])) {
            /** @var AiSystem $systemUser */
            $systemUser = AiSystem::load($options['systemId']);
            $params['system'] = $systemUser->get('description');
        }
         else {
            $params['system'] = '';
        }

        $params['system'] .= "\n" .
            "Always return each response wrapped in <response></response> tags.";

        if(!empty($options['temperature'])) {
            $params['temperature'] = (float) $options['temperature'];
        }

        if($_SERVER['SPROWTHQ_ENVIRONMENT'] == 'local') {
            $domain = gethostname() . '::' . "{$_SERVER['SPROWTHQ_SITE_NAME']}.test";
        }
        else {
            $domain = "{$_SERVER['SPROWTHQ_SITE_NAME']}.{$_SERVER['SPROWTHQ_ENVIRONMENT']}.sprowt.us";
        }
        $params['metadata'] = [
            'user_id' => $domain
        ];

        $return = $this->request('POST', '/v1/messages', $params);
        if(!empty($return['usage'])) {
            $state = \Drupal::state();
            $state->set('claude3.last_usage',  $return['usage']);
            $currentSiteUsage = $state->get('claude_3.site_usage', []);
            $now = new \DateTime();
            $times = [
                'year' => $now->format('Y'),
                'month' => $now->format('Y-m'),
                'day' => $now->format('Y-m-d'),
                'minute' => $now->format('Y-m-d H:i') . ':00'
            ];
            foreach ($times as $timeType => $time) {
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
                    $currentSiteUsage[$timeType] = $time;
                }
                $usage['input_tokens'] += $return['usage']['input_tokens'] ?? 0;
                $usage['output_tokens'] += $return['usage']['output_tokens'] ?? 0;
                $usage['total'] = $usage['input_tokens'] + $usage['output_tokens'];
                $currentSiteUsage[$timeType . '-usage'] = $usage;
            }
            $state->set('claude_3.site_usage', $currentSiteUsage);
        }
        else {
            $state = \Drupal::state();
            $state->delete('claude3.last_usage');
        }
        return $return;
    }

    public function generateContentValue(string $promptText, array $options = [])
    {
        $return = $this->generateContent($promptText, $options);
        if(!empty($return['content'])) {
            return $this->extractContentsFromReturn($return, [
                'options' => $options,
                'promptText' => $promptText
            ]);
        }
        return [];
    }

    public function extractContentsFromReturn($return, $context = []) {
        $options = $context['options'] ?? [];
        $messages = $return['content'] ?? [];
        $contents = [];
        foreach($messages as $message) {
            $content = $message['text'];
            if(strpos($content, '&nbsp;') !== false) {
                $content = str_replace('&nbsp;', ' ', $content);
            }
            $matches = [];
            if(preg_match("#<response>#", $content)) {
                if(preg_match("#</response>#", $content)) {
                    $matches = [];
                    preg_match_all("#<response>(.*)</response>#sm", $content, $matches);
                    $ints = $matches[1];
                    foreach ($ints as $int) {
                        $contents[] = trim($int);
                    }
                }
                else {
                    $context['returnedContent'] = $content;
                    \Drupal::logger('sprowt_ai')->error(Markup::create(sprintf("Incomplete result returned:\n <pre>%s</pre>", json_encode($context, JSON_PRETTY_PRINT))), $context);
                    $currentUser = \Drupal::currentUser();
                    if ($currentUser->isAuthenticated()) {
                        $errorText = t('Incomplete result returned:<br><pre>@context</pre>', ['@context' => json_encode($context, JSON_PRETTY_PRINT)]);
                        $errorMarkup = Markup::create($errorText);
                        \Drupal::messenger()->addError($errorMarkup);
                    }
                }
            }
            else {
                $contents[] = $content;
            }
        }

        if(!empty($options['charLimit'])) {
            $charLimit = (int) $options['charLimit'];
            if($charLimit > 0) {
                $return = [];
                foreach ($contents as $content) {
                    if (strlen($content) > $charLimit) {
                        \Drupal::logger('sprowt_ai')->error(Markup::create(sprintf("Result is too long:\n <pre>%s</pre>", json_encode($context, JSON_PRETTY_PRINT))), $context);
                        $currentUser = \Drupal::currentUser();
                        if ($currentUser->isAuthenticated()) {
                            $errorText = t('Result is too long:<br><pre>@context</pre>', ['@context' => json_encode($context, JSON_PRETTY_PRINT)]);
                            $errorMarkup = Markup::create($errorText);
                            \Drupal::messenger()->addError($errorMarkup);
                        }
                    }
                    else {
                        $return[] = $content;
                    }
                }
                $contents = $return;
            }
        }

        return $contents;
    }
}
