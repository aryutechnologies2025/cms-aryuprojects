<?php

namespace Drupal\aggregate_reviews_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\sprowt_subsite\SettingsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'AggregateReviewsBlock' block.
 *
 * @Block(
 *  id = "aggregate_reviews_block",
 *  admin_label = @Translation("Aggregate reviews block"),
 * )
 */
class AggregateReviewsBlock extends BlockBase implements ContainerFactoryPluginInterface
{

    /**
     * @var StateInterface
     */
    protected $state;

    /**
     * @var TwigEnvironment
     */
    protected $twig;

    protected $aggregateData = [];

    public function __construct(array $configuration, $plugin_id, $plugin_definition, $state, $twig)
    {
        $this->twig = $twig;
        $this->state = $state;
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        $state = $container->get('state');
        $twig = $container->get('twig');
        $instance = new static($configuration, $plugin_id, $plugin_definition, $state, $twig);
        return $instance;
    }


    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
                'rating_override' => null,
                'text_override' => null,
                'my_business_limit' => [],
                'my_business_subsite_limit' => false,
                'my_business_subsite_external_URL' => false,
                'external_url' => [],
                'hideOnThreshold' => true,
                'hideThreshold' => 4.5
            ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state)
    {
        $form['rating_override'] = [
            '#type' => 'number',
            '#title' => $this->t('Rating Override'),
            '#description' => $this->t('Override the generated star rating'),
            '#default_value' => $this->configuration['rating_override'] ?? null,
            '#weight' => '0',
            '#step' => '0.1',
            '#min' => 0,
            '#max' => 5
        ];
        $form['text_override'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Text Override'),
            '#description' => $this->t('Override text on block. Takes 2 tokens: {{reviews}} = total number of reviews. {{rating}} = the rating number'),
            '#default_value' => $this->configuration['text_override'] ?? null,
            '#maxlength' => 255,
            '#size' => 64,
            '#weight' => '0',
        ];

        $form['hideOnThreshold'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Hide this block (including schema) if the rating is below a threshold'),
            '#default_value' => $this->configuration['hideOnThreshold'] ?? true,
            '#attributes' => [
                'class' => ['hide-on-threshold'],
            ]
        ];

        $form['hideThreshold'] = [
            '#type' => 'number',
            '#title' => $this->t('Threshold for hiding this block'),
            '#default_value' => $this->configuration['hideThreshold'] ?? 4.5,
            '#step' => 0.1,
            '#min' => 0,
            '#max' => 5,
            '#states' => [
                'visible' => [
                    '.hide-on-threshold' => [
                        'checked' => true
                    ]
                ]
            ]
        ];

        $myBusinessConfig = \Drupal::configFactory()->get('sprowt_my_business.settings');
        if (!empty($myBusinessConfig)) {
            $locationDetails = $myBusinessConfig->get('mapped_location_details') ?? [];
            $locationOpts = [];
            foreach ($locationDetails as $locationId => $details) {
                $locationOpts[$locationId] = "{$details['name']} - {$details['addressString']}";
            }
            if (!empty($locationOpts)) {
                asort($locationOpts);
                $form['my_business_limit'] = [
                    '#type' => 'select',
                    '#options' => $locationOpts,
                    '#multiple' => true,
                    '#empty_option' => 'No limit',
                    '#empty_value' => '',
                    '#title' => 'Limit Google Reviews to these Businesses',
                    '#description' => 'Select one or more businesses to limit the aggregation to. Leave empty to aggregate ALL businesses.',
                    '#default_value' => $this->configuration['my_business_limit'] ?? []
                ];

                $form['external_link_location'] = [
                    '#type' => 'select',
                    '#options' => $locationOpts,
                    '#empty_value' => '',
                    '#title' => 'Location to use as external link',
                    '#description' => 'If the external link field is empty and this is not, it will fill with a direct link to the business page in google maps for this location',
                ];
            }

            $form['my_business_subsite_limit'] = [
                '#type' => 'checkbox',
                '#title' => 'Limit to business(es) mapped to the node\'s subsite.',
                '#description' => 'If no previous limit is set, limit to only GBP businesses with mappings to the detected subsite of the node',
                '#default_value' => $this->configuration['my_business_subsite_limit'] ?? false
            ];

            $form['my_business_subsite_external_URL'] = [
                '#type' => 'checkbox',
                '#title' => 'Link to the first business mapped to the node\'s subsite',
                '#description' => 'Use the GBP profile of the first mapped business to link to',
                '#default_value' => $this->configuration['my_business_subsite_external_URL'] ?? false
            ];
        }

        $form['external_url'] = [
            '#type' => 'url',
            '#title' => 'External URL',
            '#description' => 'If filled, the block will link to this external URL',
            '#default_value' => $this->configuration['external_url'] ?? []
        ];


        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state)
    {
        $block_id = $form_state->getCompleteFormState()->getValue('id');
        $this->configuration['instance_id'] = $block_id;
        $this->configuration['rating_override'] = $form_state->getValue('rating_override');
        $this->configuration['text_override'] = $form_state->getValue('text_override');
        $this->configuration['my_business_limit'] = array_values($form_state->getValue('my_business_limit', []));
        $this->configuration['my_business_subsite_limit'] = $form_state->getValue('my_business_subsite_limit', false);
        $this->configuration['my_business_subsite_external_URL'] = $form_state->getValue('my_business_subsite_external_URL', false);
        $this->configuration['hideOnThreshold'] = $form_state->getValue('hideOnThreshold', false);
        $this->configuration['hideThreshold'] = $form_state->getValue('hideThreshold', 4.5);
        $externalUrl = $form_state->getValue('external_url');
        $locationIdToLink = $form_state->getValue('external_link_location');
        if (empty($externalUrl) && !empty($locationIdToLink)) {
            $myBusinessConfig = \Drupal::configFactory()->get('sprowt_my_business.settings');
            if (!empty($myBusinessConfig)) {
                $locationDetails = $myBusinessConfig->get('mapped_location_details') ?? [];
                $locationArray = $locationDetails[$locationIdToLink];
                if (!empty($locationArray) && !isset($locationArray['place_id'])) {

                }
                if (!empty($locationArray) && !empty($locationArray['maps_uri'])) {
                    $externalUrl = $locationArray['maps_uri'];
                }
            }
        }
        $this->configuration['external_url'] = $externalUrl;
    }

    public function getSubsiteLocations()
    {
        $node = SettingsManager::getCurrentNodeSubsite();
        if (empty($node)) {
            return [];
        }
        $myBusinessConfig = \Drupal::configFactory()->get('sprowt_my_business.settings');
        $locationDetails = $myBusinessConfig->get('mapped_location_details') ?? [];
        $mappedLocations = $myBusinessConfig->get('mapped_locations');
        $subsiteLocations = [];
        foreach ($mappedLocations as $locationId => $mapping) {
            if (!is_array($mapping)) {
                $mapping = [
                    'branch' => $mapping ?? 'unmapped',
                    'subsite' => 'unmapped'
                ];
            }
            if (!empty($mapping['subsite']) && $mapping['subsite'] != 'unmapped') {
                if ($mapping['subsite'] == $node->id()) {
                    $subsiteLocation = $locationDetails[$locationId] ?? [];
                    if (!empty($subsiteLocation)) {
                        $subsiteLocations[$locationId] = $subsiteLocation;
                    }
                }
            }
        }
        return $subsiteLocations;
    }

    public function subsiteLocationUrl()
    {
        $subsiteLocations = $this->getSubsiteLocations();
        if (empty($subsiteLocations)) {
            return null;
        }
        $first = array_pop($subsiteLocations);
        return $first['maps_uri'] ?? null;
    }

    public function setAggregateData()
    {
        $subsiteLimit = $this->configuration['my_business_subsite_limit'] ?? false;
        $myBusinessLimit = $this->configuration['my_business_limit'] ?? [];
        if (empty($myBusinessLimit) && !empty($subsiteLimit)) {
            $subsiteLocations = $this->getSubsiteLocations();
            if (!empty($subsiteLocations)) {
                $myBusinessLimit = array_keys($subsiteLocations);
            } else {
                $myBusinessLimit = [
                    'not_found'
                ];
            }
        }
        $myBusinessKey = 'sprowt_my_business.aggregate_review_data';
        $myBusinessData = $this->state->get($myBusinessKey) ?? [];
        $totalReviews = 0;
        $totalRating = 0;
        $sources = 0;
        $aggregateRating = 0;
        foreach ($myBusinessData as $locationId => $myBusinessDatum) {
            if (empty($myBusinessLimit) || in_array($locationId, $myBusinessLimit)) {
                $totalReviews += $myBusinessDatum['totalReviews'];
                $totalRating += $myBusinessDatum['totalRating'];
                $aggregateRating = $myBusinessDatum['aggregateRating'];
                ++$sources;
            }
        }
        if ($sources > 1) { //more than one source? calculate ourselves.
            if (!empty($sources) && !empty($totalRating)) { // don't divide by zero
                $totalAggregate = round($totalRating / $totalReviews, 1);
            } else {
                $totalAggregate = 0;
            }
        } else { // just the 1 source? Use google's number
            $totalAggregate = $aggregateRating;
        }

        if (empty($totalAggregate)) {
            $totalAggregate = 0; // if there are no reviews this can be null. Setting to 0 for the round function
        }

        $this->aggregateData = [
            'reviews' => $totalReviews,
            'rating' => round($totalAggregate, 1) //rounding again just in cases
        ];

        return $this->aggregateData;
    }

    public function getReviews()
    {
        if (empty($this->aggregateData)) {
            $this->setAggregateData();
        }
        return $this->aggregateData['reviews'];
    }

    public function getRating()
    {
        if (!empty($this->configuration['rating_override'])) {
            return $this->configuration['rating_override'];
        }

        if (empty($this->aggregateData)) {
            $this->setAggregateData();
        }

        return $this->aggregateData['rating'];
    }

    public function getReviewText()
    {
        $text = "{{reviews}} Reviews";
        if (!empty($this->configuration['text_override'])) {
            $text = $this->configuration['text_override'];
        }

        $reviews = $this->getReviews();
        if ($reviews >= 10000) {
            $reviews = number_format($reviews, 0);
        }

        $tokens = [
            'reviews' => $reviews,
            'rating' => $this->getRating()
        ];
        return $this->twig->renderInline($text, $tokens);
    }

    public function isHidden()
    {
        if (empty($this->configuration['hideOnThreshold'] ?? false)) {
            return false;
        }
        if (\Drupal::service('router.admin_context')->isAdminRoute()) {
            return false;
        }

        $currentUser = \Drupal::currentUser();
        if (!$currentUser->isAnonymous()) {
            return false;
        }

        $threshold = $this->configuration['hideThreshold'] ?? 4.5;
        $rating = $this->getRating();

        return $rating < $threshold;
    }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        if ($this->isHidden()) {
            return [
                '#type' => 'markup',
                '#markup' => Markup::create("<!-- aggregate review block hidden because rating is below threshold -->"),
                '#hidden' => true
            ];
        }
        $rating = $this->getRating();
        $build = [
            '#hidden' => false
        ];
        $build['#theme'] = 'aggregate_reviews_block';
        $build['#rating'] = $this->getRating();
        $build['#text'] = $this->getReviewText();
        $build['#reviews'] = $this->getReviews();

        $config = \Drupal::config('system.site');
        $site_name = $config->get('name');
        $build['#site_name'] = $site_name;

        $build['#classes'] = [
            'star-ratings-wrap'
        ];
        $build['#stars'] = [];
        for ($i = 0; $i <= 4; ++$i) {
            $starValue = $rating - $i;
            if ($starValue >= 1) {
                $build['#stars'][] = 'star';
            } elseif ($starValue <= 0) {
                $build['#stars'][] = 'star-empty';
            } else {
                $build['#stars'][] = 'star-half';
            }
        }

        if (!empty($this->configuration['external_url'])) {
            $build['#external_url'] = $this->configuration['external_url'];
        }
        if (empty($build['#external_url']) && !empty($this->configuration['my_business_subsite_external_URL'])) {
            $subsiteUrl = $this->subsiteLocationUrl();
            if (!empty($subsiteUrl)) {
                $build['#external_url'] = $subsiteUrl;
            }
        }

        $build['#block'] = $this;


        return $build;
    }

    //let's never have this cache
    public function getCacheMaxAge()
    {
        return 0;
    }

}
