<?php

namespace Drupal\subsite_integration_accounts\Notifications;

class SlackNotification {

    protected $webhookUrl = 'https://hooks.slack.com/services/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';
    protected string $errorMessage;
    protected string $integrationType;
    protected array $details;
    public function __construct(string $integrationType,string $errorMessage, array $details) {
        $this->errorMessage = $errorMessage;
        $this->details = $details;
        $this->integrationType = $integrationType;
    }

    public function getSiteName():string {
        return \Drupal::config('system.site')->get('name');
    }

    public function getSiteUrl():string {
        return \Drupal::request()->getSchemeAndHttpHost();
    }
    public function sendMessage(): void {
        $message = $this->formatSlackMessage();
        $message = json_encode($message);
        $client = \Drupal::getContainer()->get('http_client');
        try{
            $client->request('POST',$this->webhookUrl,[
                'headers' => [
                    'Content-type' => 'application/json'
                ],
                'body' => $message
            ]);
        } catch(\Exception $e) {
            \Drupal::logger('SlackNotification')->error($e->getMessage());
        }

    }

    public function formatSlackMessage():array {
        $time = new \DateTime('now', new \DateTimeZone('UTC'));
        $errorHeader = "🔴 " . $this->getSiteName() . " - " . $this->integrationType . " Error";
        return [
            "blocks"=>[
                [
                    "type"=>"section",
                    "text"=>[
                        "type"=>"mrkdwn",
                        "text"=>"*" . $errorHeader . "*"
                    ]
                ],
                [
                    "type" => "divider"
                ],
                [
                    "type"=>"context",
                    "elements"=>[
                        [
                            "type"=>"mrkdwn",
                            "text"=>"`" . $this->errorMessage . "`"
                        ]
                    ]
                ],
                [
                    "type"=>"context",
                    "elements"=>[
                        [
                            "type"=>"mrkdwn",
                            "text"=>"*Site:* " . "<" . $this->getSiteUrl() . ">"
                        ]
                    ]
                ],
                [
                    "type"=>"context",
                    "elements"=>[
                        [
                            "type"=>"mrkdwn",
                            "text"=>"*Time:* " . $time->format('Y-m-d H:i:s') . " UTC"
                        ]
                    ]
                ],
                [
                    "type"=>"context",
                    "elements"=>[
                        [
                            "type"=>"mrkdwn",
                            "text"=>"*Details:* " . "`" . json_encode($this->details) . "`"
                        ]
                    ]
                ]
            ]
        ];
    }
}