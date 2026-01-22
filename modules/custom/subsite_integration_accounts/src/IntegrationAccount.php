<?php

namespace Drupal\subsite_integration_accounts;

class IntegrationAccount {

    protected array $account;

    protected array $integrationValues = [];

    public function __construct(array $account, array $integrationValues = []) {
        $this->account = $account;
        $this->integrationValues = $integrationValues;
    }

    public function getAccount():array {
        return $this->account;
    }

    public function getIntegrationValue($key) {
        return $this->integrationValues[$key] ?? null;
    }

    public function getValue(string $id):string {
        foreach($this->getAccount() as $accountVals) {
            if($accountVals['id'] === $id) {
                return $accountVals['value'];
            }
        }
        return "";
    }
}
