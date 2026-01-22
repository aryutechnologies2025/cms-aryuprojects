<?php

namespace Drupal\subsite_integration_accounts\Exceptions;

use Exception;
use Throwable;
use Drupal\subsite_integration_accounts\Notifications\SlackNotification;
abstract class IntegrationsException extends Exception{

    protected string $integrationType = "System";
    protected array $dontNotify = [];
    public function __construct(string $message = "", int $code = 0, ? Throwable $previous = NULL) {
        parent::__construct($message, $code, $previous);
    }
    abstract public function report():void;

    public function canNotify(string $message):bool {
        //filter through dontNotify[] for any matching cases.
       foreach($this->dontNotify as $item){
           $message = (is_string($message)) ? $message : "";
           if(stripos($message,$item) !== false){
               return false; //message contains a dontNotify item
           }
       }
       return true;
    }

    public function notify(array $details): void {
        $notify = new SlackNotification($this->integrationType,$this->getMessage(),$details);
        $notify->sendMessage();
    }

}