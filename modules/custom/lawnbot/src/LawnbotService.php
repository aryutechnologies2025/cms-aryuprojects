<?php

namespace Drupal\lawnbot;

use Drupal\Core\Url;
use Drupal\sprowt_settings\StateTrait;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * LawnbotService service.
 */
class LawnbotService
{

    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * Constructs a LawnbotService object.
     *
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     */
    public function __construct(RequestStack $request_stack)
    {
        $this->requestStack = $request_stack;
    }

    public static function getLawnbotUrl($query) {
        return Url::fromRoute('lawnbot.instantquote', [], [
            'query' => $query
        ]);
    }

    public function getLawnBotModalUrlFromId($id)
    {
        $state = \Drupal::state();
        $expireArray = $state->get('lawnbot.expires', []);
        $keyBase = 'lawnbot.' . $id;
        $queryKey = $keyBase . '.query';
        $urlKey = $keyBase . '.modalUrl';
        if(!empty($expireArray[$id]) && $expireArray[$id] < time()) {
            $state->delete($queryKey);
            $state->delete($urlKey);
            unset($expireArray[$id]);
            $state->set('lawnbot.expires', $expireArray);
            return null;
        }

        return $state->get($urlKey, null);
    }

    public function getLawnbotQueryFromId($id) {
        $state = \Drupal::state();
        $expireArray = $state->get('lawnbot.expires', []);
        $keyBase = 'lawnbot.' . $id;
        $queryKey = $keyBase . '.query';
        $urlKey = $keyBase . '.modalUrl';
        if(!empty($expireArray[$id]) && $expireArray[$id] < time()) {
            $state->delete($queryKey);
            $state->delete($urlKey);
            unset($expireArray[$id]);
            $state->set('lawnbot.expires', $expireArray);
            return null;
        }

        return $state->get($queryKey, null);
    }

    public function showModal() {
        $request = $this->requestStack->getCurrentRequest();
        $queryParam = $request->query->get('servicebot');
        if(!empty($queryParam)) {
            $testUrl = $this->getLawnBotModalUrlFromId($queryParam);
            if(!empty($testUrl)) {
                $testUrlParts = parse_url($testUrl);
                $testUrl = Url::fromUri('internal://default' . $testUrlParts['path']);
                $currentUrl = Url::createFromRequest($request);
                $testPath = $testUrl->getInternalPath();
                $currentPath = $currentUrl->getInternalPath();
                if($testPath == $currentPath) {
                    return true;
                }
            }
        }
        return false;
    }

    public function buildModal($build = []) {
        if($this->showModal()) {
            $request = $this->requestStack->getCurrentRequest();
            $queryParam = $request->query->get('servicebot');
            if(!empty($queryParam)) {
                $pageQuery = $request->query->all();
                unset($pageQuery['servicebot']);
                $query = array_merge($pageQuery, $this->getLawnbotQueryFromId($queryParam));
                if (!empty($query['source'])) {
                    $url = static::getLawnbotUrl($query);
                    $build['lawnbot'] = [
                        '#theme' => 'instantquote_modal',
                        '#iframeUrl' => $url->toString(),
                        '#attached' => [
                            'library' => [
                                'lawnbot/modal'
                            ]
                        ],
                        '#cache' => [
                            'max-age' => 0
                        ]
                    ];
                }
            }
        }

        return $build;
    }

    public function clearExpiry() {
        $state = \Drupal::state();
        $expireArray = $state->get('lawnbot.expires', []);
        $changed = false;
        foreach($expireArray as $id => $time) {
            $keyBase = 'lawnbot.' . $id;
            $queryKey = $keyBase . '.query';
            $urlKey = $keyBase . '.modalUrl';
            if($time < time()) {
                $state->delete($queryKey);
                $state->delete($urlKey);
                unset($expireArray[$id]);
                $changed = true;
            }
        }
        if($changed) {
            $state->set('lawnbot.expires', $expireArray);
        }
    }

}
