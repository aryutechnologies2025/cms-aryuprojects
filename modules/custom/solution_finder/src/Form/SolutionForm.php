<?php

namespace Drupal\solution_finder\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\solution_finder\Entity\Solution;
use Drupal\sprowt_subsite\Plugin\Field\FieldType\SubsiteReferenceItem;
use Drupal\sprowt_subsite\SettingsManager;

/**
 * Form controller for the solution entity edit forms.
 */
class SolutionForm extends ContentEntityForm
{

    protected $destinationOptionsObject;

    protected $destinationOptions;

    protected $subsites;

    protected $destinationBundles = [
        'page' => 'Basic Page',
        'blog' => 'Blog',
        'career_page' => 'Career Page',
        'city_page' => 'City Page',
        'landing_page' => 'Landing Page',
        'ltp_page' => 'LTP Page',
        'region_page' => 'Region Page',
        'service' => 'Service',
        'solution_page_package_alt' => 'Solution Page',
        'solution_page_packages' => 'Solution Page',
        'solution_page_services' => 'Solution Page',
        'solution_page_package_service' => 'Solution Page',
        'standalone_landing_page' => 'Standalone Landing Page',
    ];

    public function form(array $form, FormStateInterface $form_state)
    {
        $form = parent::form($form, $form_state);

        $subsites = $this->subsites();

        $subsiteTargets = [
            '_main' => 'Main site'
        ];
        foreach ($subsites as $subsite) {
            $subsiteTargets[$subsite->uuid()] = $subsite->label();
        }


        $solutionPages = $this->getEntity()->getDestinations();

        $form['solution_pages'] = [
            '#type' => 'fieldset',
            '#title' => t('Destinations'),
            '#description' => 'List of destinations to go to when submitted from subsites (and main site)',
            '#description_display' => 'before'
        ];
        foreach($subsiteTargets as $subsite => $title) {
            $form['solution_pages']['destination__' . $subsite] = $this->destinationElement($solutionPages[$subsite] ?? null);
            $form['solution_pages']['destination__' . $subsite]['#title'] = 'Destination for: ' . $title;
        }


        $form['#attached']['library'][] = 'solution_finder/entity_form';
        return $form;
    }

    protected function subsites() {
        if(isset($this->subsites)) {
            return $this->subsites;
        }

        $subsites = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => 'subsite',
        ]);
        uasort($subsites, function($a, $b) {
            $aVal = $a->label();
            $bVal = $b->label();
            return $aVal <=> $bVal;
        });
        $this->subsites = $subsites;
        return $this->subsites;
    }

    protected function getSubsiteFromNode($node) {
        $subsites = $this->subsites();
        if(!$node->hasField(SettingsManager::SUBSITE_FIELD)) {
            return null;
        }
        $itemList = $node->get(SettingsManager::SUBSITE_FIELD);
        if ($itemList->isEmpty()) {
            return null;
        }
        /** @var SubsiteReferenceItem $item */
        $item = $itemList->first();
        $subsiteNid = $item->target_id;

        foreach($subsites as $subsite) {
            if($subsite->id() == $subsiteNid) {
                return $subsite;
            }
        }

        return null;
    }

    protected function destinationOptionsObject() {
        if(isset($this->destinationOptionsObject)) {
            return $this->destinationOptionsObject;
        }

        $options = [];
        $subsites = $this->subsites();
        $subsiteTargets = [
            '_main' => 'Main site'
        ];
        foreach ($subsites as $subsite) {
            $subsiteTargets[$subsite->uuid()] = $subsite->label();
        }

        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
            'type' => array_keys($this->destinationBundles),
        ]);
        uasort($nodes, function($a, $b) {
            $aVal = $a->label();
            $bVal = $b->label();
            return $aVal <=> $bVal;
        });

        foreach($nodes as $node) {
            $subsite = $this->getSubsiteFromNode($node);
            if(empty($subsite)) {
                $subsiteUuid = '_main';
            }
            else {
                $subsiteUuid = $subsite->uuid();
            }
            $bundle = $node->bundle();
            $bundleLabel = $this->destinationBundles[$bundle];
            $obj = [
                'id' => $node->id(),
                'uuid' => $node->uuid(),
                'label' => $node->label(),
                'bundle' => $bundle,
                'bundle_label' => $bundleLabel,
                'subsite' => $subsiteTargets[$subsiteUuid],
                'subsite_uuid' => $subsiteUuid,
            ];
            $options[] = $obj;
        }

        $this->destinationOptionsObject = $options;
        return $this->destinationOptionsObject;
    }

    protected function destinationOptions() {
        if(isset($this->destinationOptions)) {
            return $this->destinationOptions;
        }

        $options = [];
        $objects = $this->destinationOptionsObject();
        foreach ($objects as $array) {
            $options[$array['id']] = "{$array['subsite']}: {$array['bundle']} -> {$array['label']}";
        }

        $this->destinationOptions = $options;
        return $this->destinationOptions;
    }

    protected function destinationElement($defaultValue = null)
    {
        $options = $this->destinationOptions();
        $objects = $this->destinationOptionsObject();
        $element = [
            '#type' => 'select',
            '#options' => $options,
            '#default_value' => $defaultValue,
            '#empty_option' => '-- Select --',
            '#empty_value' => '',
            '#attributes' => [
                'class' => ['destination-element'],
                'data-options-definition' => json_encode($objects),
            ]
        ];

        return $element;
    }


    protected function getDefaultWeight() {
        $solutions = $this->entityTypeManager->getStorage('solution')->loadMultiple();
        $return = 0;
        /** @var Solution $solution */
        foreach($solutions as $solution) {
            $weight = $solution->getWeight();
            if($weight > $return) {
                $return = $weight;
            }
        }
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {
        $entity = $this->getEntity();

        $values = $form_state->getValues();
        $solution_pages = [];
        foreach ($values as $key => $value) {
            if(strpos($key, 'destination__') === 0) {
                $subsiteKey = str_replace('destination__', '', $key);
                if(!empty($value)) {
                    $solution_pages[$subsiteKey] = $value;
                }
            }
        }

        $entity->set('solution_pages', $solution_pages);

        $weight = $entity->getWeight();
        if(!isset($weight)) {
            $entity->setWeight($this->getDefaultWeight());
        }
        $result = $entity->save();
        $link = $entity->toLink($this->t('View'))->toRenderable();

        $message_arguments = ['%label' => $this->entity->label()];
        $logger_arguments = $message_arguments + ['link' => \Drupal::service('renderer')->render($link)];

        if ($result == SAVED_NEW) {
            $this->messenger()->addStatus($this->t('New solution %label has been created.', $message_arguments));
            $this->logger('solution_finder')->notice('Created new solution %label', $logger_arguments);
        }
        else {
            $this->messenger()->addStatus($this->t('The solution %label has been updated.', $message_arguments));
            $this->logger('solution_finder')->notice('Updated new solution %label.', $logger_arguments);
        }

        $form_state->setRedirect('entity.solution.collection');
    }

}
