<?php

namespace Drupal\sprowt_ai\Event;

use Drupal\Component\EventDispatcher\Event;
use GuzzleHttp\Exception\GuzzleException;

class Claude3ErrorEvent extends Event
{

    const EVENT_NAME = 'sprowt_ai.claude3_error';

    /**
     * @var GuzzleException
     */
    protected $error;


    public function __construct($error)
    {
        $this->error = $error;
    }

    public function getError(): GuzzleException
    {
        return $this->error;
    }

    public function setError(GuzzleException $error): self
    {
        $this->error = $error;
        return $this;
    }

}
