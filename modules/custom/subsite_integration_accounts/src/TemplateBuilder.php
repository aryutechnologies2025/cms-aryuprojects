<?php

namespace Drupal\subsite_integration_accounts;

class TemplateBuilder {

    protected array $template;

    public function __construct() {
        $this->reset();
    }

    protected function reset(): void {
        $this->template = [];
    }

    public function addDefaultLabel():void {
        $label = [
            'id' => 'label',
            'name' => 'Label for this account',
            'value' => '',
        ];
        if(empty($this->template)){
            $this->template[] = $label;
        }
        array_unshift($this->template, $label);
    }

    public function hasLabel():bool {
        if(empty($this->template)){
            return false;
        }
        foreach($this->template as $item) {
            if($item['id'] === 'label') {
                return true;
            }
        }
        return false;
    }

    public function addField(string $id, string $name, string $value): TemplateBuilder {
        $this->template[] = [
            'id' => $id,
            'name' => $name,
            'value' => $value,
        ];
        return $this;
    }

    public function getTemplate(): array {
        if(!$this->hasLabel()) {
            $this->addDefaultLabel();
        }
        return $this->template;
    }
}