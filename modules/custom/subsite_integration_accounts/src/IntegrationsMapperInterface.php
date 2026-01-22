<?php
namespace Drupal\subsite_integration_accounts;

interface IntegrationsMapperInterface {

    /**
     * @param \Drupal\subsite_integration_accounts\TemplateBuilder $builder
     *  An ar
     *
     * @return array
     */
    public function getFormTemplate(TemplateBuilder $builder):array;
    public function getButtonTitle():?string;
    public function getDefaultValues():?string;
}