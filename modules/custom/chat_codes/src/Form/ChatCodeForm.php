<?php

namespace Drupal\chat_codes\Form;

//sometime this isn't loaded?
require_once DRUPAL_ROOT . '/modules/custom/sprowt_settings/src/EntityVisibilityFormTrait.php';

use Drupal\chat_codes\Entity\ChatCode;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Datetime\Element\Datelist;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_settings\EntityVisibilityFormTrait;
use Drupal\system\Plugin\Condition\RequestPath;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the chat code entity edit forms.
 */
class ChatCodeForm extends ContentEntityForm
{

    use EntityVisibilityFormTrait;

    /** @var ChatCode */
    protected $entity;

    protected $conditionManager;

    protected $sampleSchedules = [
        [
            'timezone' => 'America/New_York',
            'type' => 'everyday',
            'time' => 'allDay'
        ],
        [
            'timezone' => 'America/New_York',
            'type' => 'daysoftheweek',
            'days' => [0,1,2,3,4],
            'time' => [
                'from' => '00:00',
                'to' => '23:59'
            ]
        ],
        [
            'timezone' => 'America/New_York',
            'type' => 'daysofthemonth',
            'days' => [0,2,24,31],
            'time' => [
                'from' => '00:00',
                'to' => '12:00'
            ]
        ],
        [
            'timezone' => 'America/New_York',
            'type' => 'daysoftheyear',
            'days' => [
                '01-01',
                '02-01',
                '07-04'
            ],
            'time' => [
                'from' => '00:00',
                'to' => '12:00'
            ]
        ],
    ];

    public function __construct(
        EntityRepositoryInterface $entity_repository,
        EntityTypeBundleInfoInterface $entity_type_bundle_info,
        TimeInterface $time
    ) {
        parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity.repository'),
            $container->get('entity_type.bundle.info'),
            $container->get('datetime.time')
        );
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);

        /** @var RequestPath $pageCondition */
        $pageCondition = $this->entity->getPageRestriction();

        $form[$this->getVisibilityFormItemKey()] = $this->buildVisibilityInterface([], $form_state);

//        $form['visibility'] = $pageCondition->buildConfigurationForm([
//            '#type' => 'fieldset',
//            '#title' => t('Visibility'),
//            '#weight' => 2
//        ], $form_state);
//        $form['visibility']['negate'] = array_merge($form['visibility']['negate'], [
//            '#type' => 'radios',
//            '#title' => null,
//            '#options' => [
//                0 => t('Show for the listed pages'),
//                1 => t('Hide for the listed pages')
//            ]
//        ]);

        $schedules = $this->entity->getSchedules();
        $errors = $form_state->getValue('schedulesErrors', '{}');

        $template = $this->scheduleForm([], $form_state);

        $tz =  date_default_timezone_get();

        $form['scheduleSet'] = [
            '#type' => 'fieldset',
            '#title' => t('Schedules'),
            '#weight' => 3,
            'schedulesValue' => [
                '#type' => 'hidden',
                '#default_value' => empty($schedules) ? '{}' : json_encode($schedules),
                '#attributes' => [
                    'id' => 'schedule-value-field'
                ]
            ],
            'schedulesErrors' => [
                '#type' => 'hidden',
                '#default_value' => $errors,
                '#attributes' => [
                    'id' => 'schedule-error-field'
                ]
            ],
            'defaultTimezone' => [
                '#type' => 'hidden',
                '#value' => $tz,
                '#attributes' => [
                    'id' => 'default-time-zone'
                ]
            ],
            'monthTemplate' => [
                '#type' => 'html_tag',
                '#tag' => 'script',
                '#attributes' => [
                    'id' => 'monthfield-template',
                    'type' => 'text/template'
                ],
                'monthField' => [
                    '#type' => 'datelist',
                    '#date_part_order' => [
                        'month', 'day'
                    ],
                    '#prefix' => Markup::create('<div class="monthfield-wrap">'),
                    '#suffix' => '</div>',
                    '#process' => [
                        [Datelist::class, 'processDatelist'],
                        function(&$element, FormStateInterface $form_state, &$complete_form) {
                            $element['removeButton'] = [
                                '#type' => 'markup',
                                '#markup' => Markup::create('<button class="remove-month button action-link action-link--danger action-link--icon-trash">Remove</button>')
                            ];
                            return $element;
                        }
                    ],
                    '#value_callback' => null
                ],
            ],
            'scheduleTemplate' => [
                '#type' => 'html_tag',
                '#tag' => 'script',
                '#attributes' => [
                    'id' => 'schedule-template',
                    'type' => 'text/template'
                ],
                'scheduleWrap' => [
                    '#type' => 'fieldset',
                    '#attributes' => [
                        'class' => ['schedule-wrap']
                    ]
                ] + $template
            ],
            'list' => [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                    'id' => 'schedule-list'
                ]
            ],
            'addSchedule' => [
                '#type' => 'button',
                '#value' => 'Add schedule',
                '#attributes' => [
                    'id' => 'add-schedule'
                ]
            ]
        ];

        $form['#attached']['library'][] = 'chat_codes/entity_form';
        return $form;
    }

    public function scheduleForm($form, FormStateInterface $formState) {
        $tz =  date_default_timezone_get();
        $identifiers = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'US');
        $canada = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'CA');
        $identifiers = array_merge($canada, $identifiers);
        $addArray = ['UTC', $tz];
        foreach($addArray as $idx => $addTz) {
            if(in_array($addTz, $identifiers)) {
                unset($addArray[$idx]);
            }
        }
        $identifiers = array_merge(array_values($addArray), $identifiers);


        $form['timezoneValue'] = [
            '#type' => 'select',
            '#title' => t('Time Zone'),
            '#default_value' => $tz,
            '#description' => t('The time zone this schedule will use. Use <a target="_blank" href="https://www.php.net/manual/en/timezones.php">this list</a> to set.'),
            '#attributes' => [
                'class' => ['timezone-field']
            ],
            '#options' => array_combine($identifiers, $identifiers)
        ];

        $dayOptions = [
            'everyday' => 'Every day',
            'daysoftheweek' => 'On certain days of the week',
            'daysofthemonth' => 'On certain days of the month',
            'daysoftheyear' => 'On certain days of the year'
        ];

        $form['type'] = [
            '#type' => 'select',
            '#title' => t('Schedule'),
            '#options' => $dayOptions,
            '#default_value' => 'everyday',
            '#attributes' => [
                'class' => ['schedule-type']
            ]
        ];



        $form['daysoftheweek'] = [
            '#type' => 'checkboxes',
            '#title' => t('Days of the week'),
            '#options' => DateHelper::weekDays(true),
            '#attributes' => [
                'class' => ['daysoftheweek-checkbox']
            ],
        ];

        $monthDayOptions = [];
        $datePeriod = new \DatePeriod(
            new \DateTime('1970-01-01 00:00:00'),
            new \DateInterval('P1D'),
            new \DateTime('1970-01-31 23:59:59')
        );
        foreach($datePeriod as $date) {
            $monthDayOptions[(int) $date->format('j')] = $date->format('jS');
        }

        $form['daysofthemonth'] = [
            '#type' => 'select',
            '#title' => t('Days of the month'),
            '#options' => $monthDayOptions,
            '#description' => t('If you pick a day that the month does not contain (e.g. the 30th for Feb). Then the last day of the month will be used.'),
            '#multiple' => true,
            '#attributes' => [
                'class' => ['daysofthemonth-select']
            ],
        ];

        $monthMap = [];
        for($month = 1; $month <= 12; ++$month) {
            $date = new \DateTime();
            $date->setDate(2020, $month, 1); //2020 is a leap year so feb will be 29
            $monthMap[$month] = (int) $date->format('t');
        }

        $form['daysoftheyear'] = [
            '#type' => 'fieldset',
            '#title' => t('Days of the year'),
            '#attributes' => [
                'class' => ['daysoftheyear-multi-wrap'],
                'data-month-day-map' => json_encode($monthMap),
                '#description' => t('If you pick a day of a month that it does not contain (e.g. 29 for Feb on a non leap year). Then the last day of the month will be used.'),
            ],
            'months' => [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                    'class' => ['month-list']
                ]
            ],
            'addButton' => [
                '#type' => 'button',
                '#value' => t('Add'),
                '#attributes' => [
                    'class' => ['add-month']
                ]
            ]
        ];

        foreach(array_keys($dayOptions) as $dayOption) {
            if(isset($form[$dayOption])) {
                $form[$dayOption]['#prefix'] = Markup::create('<div class="day-option '.$dayOption.'" style="display:none;">');
                $form[$dayOption]['#suffix'] = '</div>';
            }
        }

        $form['timeSet'] = [
            '#type' => 'fieldset',
            '#title' => t('For this time range'),
            'timeAll' => [
                '#type' => 'checkbox',
                '#title' => 'All day',
                '#attributes' => [
                    'class' => ['time-all-check']
                ]
            ],
            'timeRange' => [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#attributes' => [
                    'class' => ['time-range-wrap'],
                    'style' => 'display:none;'
                ],
                'from' => [
                    '#type' => 'date',
                    '#title' => t('From'),
                    '#attributes' => [
                        'class' => ['time-field', 'time-from'],
                        'type' => 'time',
                        'step' => 60
                    ],
                ],
                'to' => [
                    '#type' => 'date',
                    '#title' => t('To'),
                    '#attributes' => [
                        'class' => ['time-field', 'time-to'],
                        'type' => 'time',
                        'step' => 60
                    ],
                ],
            ]
        ];

        $form['removeSchedule'] = [
            '#type' => 'button',
            '#value' => 'Remove',
            '#attributes' => [
                'class' => explode(' ', 'remove-schedule button action-link action-link--danger action-link--icon-trash')
            ]
        ];

        return $form;
    }

    protected function validateSchedule(&$form, FormStateInterface $formState) {
        $scheduleJson = $formState->getValue('schedulesValue', '{}');
        $schedules = json_decode($scheduleJson, true);
        $formState->set('schedules', $schedules);
        $errors = [];
        foreach($schedules as $schedule) {
            $scheduleId = $schedule['id'];
            $error = [];
            if($schedule['type'] == 'daysoftheweek') {
                if(empty($schedule['days'])) {
                    $error['.daysoftheweek-checkbox'] = 'At least one day is required.';
                }
            }
            if($schedule['type'] == 'daysofthemonth') {
                if(empty($schedule['days'])) {
                    $error['.daysofthemonth-select'] = 'At least one day is required.';
                }
            }
            if($schedule['type'] == 'daysoftheyear') {
                if(empty($schedule['days'])) {
                    $error['.daysoftheyear legend .fieldset__label'] = 'At least one day is required.';
                }
            }

            if(is_array($schedule['time'])) {
                if(empty($schedule['time']['from'])) {
                    $error['input.time-from'] = 'From value required.';
                }
                if(empty($schedule['time']['to'])) {
                    $error['input.time-to'] = 'To value required.';
                }
                if(!empty($schedule['time']['from'])
                    && !empty($schedule['time']['to'])
                ) {
                    $now = new \DateTime('1970-01-01 12:00:00');
                    $from = new \DateTime($now->format('Y-m-d') . ' ' . $schedule['time']['from'] . ':00');
                    $to = new \DateTime($now->format('Y-m-d') . ' ' . $schedule['time']['to'] . ':00');
                    if($to <= $from) {
                        $error['input.time-field'] = 'To value must occur after From value.';
                    }
                }
            }

            if(!empty($error)) {
                $errors[$scheduleId] = $error;
            }
        }

        $formState->setValue('schedulesErrors', json_encode($errors, JSON_FORCE_OBJECT));
        $formState->set('schedulesErrors', json_encode($errors, JSON_FORCE_OBJECT));
        $form["scheduleSet"]["schedulesErrors"]["#value"] = json_encode($errors, JSON_FORCE_OBJECT);
        if(!empty($errors)) {
            foreach($errors as $scheduleId => $scheduleErrors) {
                foreach ($scheduleErrors as $class => $errorMsg) {
                    $formState->setErrorByName($scheduleId . '--' . $class, $errorMsg);
                }
            }
            $formState->setRebuild();
        }
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        $this->validateSchedule($form, $form_state);
        $this->validateVisibility($form, $form_state);

        return parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {

        $entity = $this->getEntity();

        $schedules = $form_state->get('schedules') ?? [];
        $entity->setSchedules($schedules);
        $pages = $form_state->getValue('pages', '');
        $negate = $form_state->getValue('negate', "1");
//        $entity->set('page_restrictions', $pages);
//        $entity->set('page_restriction_negate', (int) $negate);

        $this->submitVisibility($form, $form_state);

        $result = $entity->save();
        $link = $entity->toLink($this->t('View'))->toRenderable();

        $message_arguments = ['%label' => $this->entity->label()];
        $logger_arguments = $message_arguments + ['link' => \Drupal::service('renderer')->render($link)];

        if ($result == SAVED_NEW) {
            $this->messenger()->addStatus($this->t('New chat code %label has been created.', $message_arguments));
            $this->logger('chat_codes')->notice('Created new chat code %label', $logger_arguments);
        }
        else {
            $this->messenger()->addStatus($this->t('The chat code %label has been updated.', $message_arguments));
            $this->logger('chat_codes')->notice('Updated new chat code %label.', $logger_arguments);
        }

        //clear caches so chat codes are rendered correctly
        \Drupal::service('cache.entity')->invalidateAll();
        \Drupal::service('cache.render')->invalidateAll();

        $form_state->setRedirect('entity.chat_code.collection');
    }

}
