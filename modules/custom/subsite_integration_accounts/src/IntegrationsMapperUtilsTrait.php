<?php

namespace Drupal\subsite_integration_accounts;

trait IntegrationsMapperUtilsTrait {

    public function addLabelsAsKeys(array $accounts):array{
        $updated = [];
        foreach($accounts as $account) {
            $label = "";
            foreach($account as $item) {
                if($item['id'] === 'label') {
                    $label = $item['value'];
                }
            }
            $updated[$this->cleanString($label)] = $account;
        }
        return $updated;
    }
    public function hasDuplicateLabels(array $accounts):bool {
        if(empty($accounts)){
            return false;
        }
        $labels = [];
        foreach($accounts as $account) {
            foreach($account as $accountVals) {
                if($accountVals['id'] === 'label') {
                    if(in_array($accountVals['value'], $labels)) {
                        return true;
                    }
                    $labels[] = $accountVals['value'];
                }
            }
        }
        return false;
    }

    public function getLabelOptions(array $accounts):array {
        $labels = [];
        foreach($accounts as $key => $account) {
            foreach($account as $accountVals) {
                if($accountVals['id'] === 'label') {
                    $labels[$key] = $accountVals['value'];
                }
            }
        }
        return $labels;
    }

    public function cleanString(string $input):string {
        $cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $input);
        $cleaned = trim($cleaned);
        $cleaned = str_replace(' ', '_', $cleaned);
        return strtolower($cleaned);
    }
}