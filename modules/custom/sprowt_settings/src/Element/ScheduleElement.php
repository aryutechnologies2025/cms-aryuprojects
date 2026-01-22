<?php

namespace Drupal\sprowt_settings\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Datetime\Element\Datelist;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\template_field\Element\TemplateField;
use Yasumi\Holiday;
use Yasumi\Yasumi;

/**
 * Provides a render element to display an entity.
 * @\Drupal\Core\Render\Annotation\FormElement("schedule_element")
 */
class ScheduleElement extends TemplateField
{
    /**
     * {@inheritdoc}
     */
    public function getInfo()
    {
        $class = get_class($this);
        $return = parent::getInfo();
        $return['#wrapper_attributes']['class'][] = 'schedule-element';
        $process = $return['#process'];
        $process = array_merge([
            [$class, 'setTemplate']
        ], $process);
        $return['#process'] = $process;
        return array_merge($return, [
            '#object_name' => 'schedule',
            '#negateTrueText' => t('Hide on this schedule'),
            '#negateFalseText' => t('Show on this schedule'),
            '#showNegate' => true,
        ]);
    }

    public static function showOnSchedule($schedule, \DateTimeInterface $givenDate)
    {
        $show = true;
        $schedule['type'] ??= 'everyDay';
        $date = new \DateTime($givenDate->format('c'));
        $tzStr = $schedule['timezone'] ?? date_default_timezone_get();
        $date->setTimezone(new \DateTimeZone($tzStr));
        switch ($schedule['type']) {
            case 'daysoftheweek':
                $days = $schedule['days'] ?? [];
                $dateDay = $date->format('w');
                $show &= in_array($dateDay, $days);
                break;
            case 'daysofthemonth':
                $days = $schedule['days'] ?? [];
                $dateDay = $date->format('j');
                $show &= in_array($dateDay, $days);
                break;
            case 'daysoftheyear':
                $days = $schedule['days'] ?? [];
                $dateDay = $date->format('m-d');
                $show &= in_array($dateDay, $days);
                break;
            case 'monthsoftheyear':
                $months = $schedule['days'] ?? [];
                array_walk($months, function (&$month) {
                    $month = (int) $month;
                });
                $dateMonth = (int) $date->format('n');
                $show &= in_array($dateMonth, $months);
                break;
            case 'holidays':
                $providerName = $schedule['holidayProvider'] ?? 'USA';
                $providerHolidays = Yasumi::create($providerName, (int) $date->format('Y'));
                $days = $schedule['days'] ?? [];
                $testDays = [];
                foreach ($days as $day) {
                    $holiday = $providerHolidays->getHoliday($day);
                    $testDays[] = $holiday->format('Y-m-d');
                }
                $dateDay = $date->format('Y-m-d');
                $show &= in_array($dateDay, $testDays);
                break;
        }
        $schedule['time'] ??= 'allDay';
        if($schedule['time'] != 'allDay') {
            $from = new \DateTime($date->format('Y-m-d') . ' ' . $schedule['time']['from'] . ':00');
            $to = new \DateTime($date->format('Y-m-d') . ' ' . $schedule['time']['to'] . ':00');
            $show &= $date >= $from && $date <= $to;
        }

        if(!empty($schedule['negate'])) {
            $show = !$show;
        }
        return $show;
    }

    public static function showOnSchedules($schedules, \DateTimeInterface $date) {
        $show = true;
        foreach($schedules as $schedule) {
            $show &= static::showOnSchedule($schedule, $date);
        }
        return $show;
    }

    public static function scheduleTextSummary($schedule) {
        $schedule['type'] ??= 'everyDay';

        $days = '';
        switch($schedule['type']) {
            case 'daysoftheweek':
                $days = [];
                $weekdays = DateHelper::weekDaysAbbr();
                foreach($schedule['days'] as $day) {
                    $days[] = $weekdays[$day];
                }
                if(count($days) > 1) {
                    $lastDay = array_pop($days);
                    $days = implode(', ', $days);
                    $days .= ' and ' . $lastDay;
                }
                else {
                    $days = $days[0] ?? '';
                }
                $str = 'every @days';
                if(empty($days)) {
                    $str = 'on no days of the week';
                }
                break;
            case 'daysofthemonth':
                $days = [];
                foreach($schedule['days'] as $day) {
                    $dayObj = new \DateTime();
                    $dayObj->setDate(2025, 1, $day);
                    $days[] = $dayObj->format('jS');
                }
                if(count($days) > 1) {
                    $lastDay = array_pop($days);
                    $days = implode(', ', $days);
                    $days .= ' and ' . $lastDay;
                }
                else {
                    $days = $days[0] ?? '';
                }
                $str = 'every month on the @days';
                if(empty($days)) {
                    $str = 'on no days of the month';
                }
                break;
            case 'daysoftheyear':
                $days = [];
                foreach($schedule['days'] as $day) {
                    $dayObj = new \DateTime('2025-' . $day);
                    $days[] = $dayObj->format('M jS');
                }
                if(count($days) > 1) {
                    $lastDay = array_pop($days);
                    $days = implode(', ', $days);
                    $days .= ' and ' . $lastDay;
                }
                else {
                    $days = $days[0] ?? '';
                }
                $str = 'every year on @days';
                if(empty($days)) {
                    $str = 'on no days of the year';
                }
                break;
            case 'monthsoftheyear':
                $days = [];
                foreach($schedule['days'] as $day) {
                    $dayObj = new \DateTime('2025-' . $day);
                    $days[] = $dayObj->format('F');
                }
                if(count($days) > 1) {
                    $lastDay = array_pop($days);
                    $days = implode(', ', $days);
                    $days .= ' and ' . $lastDay;
                }
                else {
                    $days = $days[0] ?? '';
                }
                $str = 'every day in @days';
                if(empty($days)) {
                    $str = 'on no months of the year';
                }
                break;
            case 'holidays':
                $providerName = $schedule['holidayProvider'] ?? 'USA';
                $providerHolidays = Yasumi::create($providerName, (int) date('Y'));
                $days = [];
                foreach($schedule['days'] as $day) {
                    $dayObj = $providerHolidays->getHoliday($day);
                    $days[] = '"' . $dayObj->getName() . '"';
                }
                if(count($days) > 1) {
                    $lastDay = array_pop($days);
                    $days = implode(', ', $days);
                    $days .= ' and ' . $lastDay;
                }
                else {
                    $days = $days[0] ?? '';
                }
                $str = 'every year on @days';
                if(empty($days)) {
                    $str = 'on no holidays of the year';
                }
                break;
            default:
                $str = 'every day';
        }
        $schedule['time'] ??= 'allDay';
        if($schedule['time'] == 'allDay') {
            $str .= ' all day';
        }
        else {
            $fromObj = new \DateTime('1970-01-01 ' . $schedule['time']['from'] . ':00');
            $toObj = new \DateTime('1970-01-01 ' . $schedule['time']['to'] . ':00');
            $str .= ' at ' . $fromObj->format('h:ia') . ' to ' . $toObj->format('h:ia');
        }

        if(!empty($schedule['negate'])) {
            $str = 'Hide ' . $str;
        }
        else {
            $str = 'Show ' . $str;
        }

        return t($str, ['@days' => $days]);
    }

    public static function setTemplate(&$element, FormStateInterface $formState, &$form)
    {

        $template = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['schedule-item'],
                'style' => 'display:none;'
            ]
        ];


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


        $template['timezoneValue'] = [
            '#type' => 'select',
            '#title' => t('Time Zone'),
            '#default_value' => $tz,
            '#description' => t('The time zone this schedule will use. Use <a target="_blank" href="https://www.php.net/manual/en/timezones.php">this list</a> to set.'),
            '#attributes' => [
                'class' => ['timezone-field'],
                'data-default-timezone' => $tz
            ],
            '#options' => array_combine($identifiers, $identifiers)
        ];

        $dayOptions = [
            'everyday' => 'Every day',
            'daysoftheweek' => 'On certain days of the week',
            'daysofthemonth' => 'On certain days of the month',
            'daysoftheyear' => 'On certain days of the year',
            'monthsoftheyear' => 'On certain months of the year',
            'holidays' => 'On certain holidays',
        ];

        $template['type'] = [
            '#type' => 'select',
            '#title' => t('Schedule'),
            '#options' => $dayOptions,
            '#default_value' => 'everyday',
            '#attributes' => [
                'class' => ['schedule-type'],
                'style' => 'display:none;'
            ]
        ];



        $template['daysoftheweek'] = [
            '#type' => 'checkboxes',
            '#title' => t('Days of the week'),
            '#options' => DateHelper::weekDays(true),
            '#attributes' => [
                'class' => ['daysoftheweek-checkbox', 'daysoftheweek-wrapper', 'schedule-type-wrapper']
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

        $template['daysofthemonth'] = [
            '#type' => 'select',
            '#title' => t('Days of the month'),
            '#options' => $monthDayOptions,
            '#description' => t('If you pick a day that the month does not contain (e.g. the 30th for Feb). Then the last day of the month will be used.'),
            '#multiple' => true,
            '#attributes' => [
                'class' => ['daysofthemonth-select']
            ],
            '#wrapper_attributes' => [
                'class' => ['daysofthemonth-wrapper', 'schedule-type-wrapper']
            ]
        ];

        $monthMap = [];
        for($month = 1; $month <= 12; ++$month) {
            $date = new \DateTime();
            $date->setDate(2020, $month, 1); //2020 is a leap year so feb will be 29
            $monthMap[$month] = (int) $date->format('t');
        }

        $template['daysoftheyear'] = [
            '#type' => 'fieldset',
            '#title' => t('Days of the year'),
            '#attributes' => [
                'class' => ['daysoftheyear-multi-wrap', 'daysoftheyear-wrapper', 'schedule-type-wrapper'],
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

        $template['monthsoftheyear'] = [
            '#type' => 'checkboxes',
            '#title' => t('Months of the year'),
            '#options' => DateHelper::monthNames(true),
            '#attributes' => [
                'class' => ['monthsoftheyear-checkbox', 'monthsoftheyear-wrapper', 'schedule-type-wrapper']
            ],
        ];

        $holidayProviders = Yasumi::getProviders();
        $providerMap = [];
        $providerOpts = [];
        foreach($holidayProviders as $providerKey => $providerName) {
            $providerOpts[$providerName] = $providerName;
            $providerHolidays = Yasumi::create($providerName, (int) date('Y'));
            $providerMap[$providerName] = [];
            /** @var Holiday $providerHoliday */
            foreach($providerHolidays as $providerHoliday) {
                $providerMap[$providerName][$providerHoliday->getKey()] =  $providerHoliday->getName() . " ({$providerHoliday->format('M jS')})";
            }
        }


        $template['holidays'] = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['holidays-multi-wrap', 'holidays-wrapper', 'schedule-type-wrapper'],
                '#description' => t('Select a provider and then select holiday'),
            ],
            'provider' => [
                '#type' => 'select',
                '#title' => t('Country/Region'),
                '#options' => $providerOpts,
                '#attributes' => [
                    'class' => ['holiday-provider-select'],
                    'data-provider-map' => json_encode($providerMap),
                ]
            ],
            'holidayWrap' => [
                '#type' => 'item',
                '#title' => t('Holidays'),
                '#attributes' => [
                    'class' => ['holiday-wrapper']
                ],
                'holidaySelectWrapper' => [
                    '#type' => 'container',
                    '#attributes' => [
                        'class' => ['holiday-select-wrapper']
                    ]
                ]
            ]
        ];

        $stop = true;

        foreach(array_keys($dayOptions) as $dayOption) {
            if(isset($form[$dayOption])) {
                $form[$dayOption]['#prefix'] = Markup::create('<div class="day-option '.$dayOption.'" style="display:none;">');
                $form[$dayOption]['#suffix'] = '</div>';
            }
        }

        $template['timeSet'] = [
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

        if(!empty($element['#showNegate'])) {
            $negateOptions = [
                0 => $element['#negateFalseText'] ?? t('False'),
                1 => $element['#negateTrueText'] ?? t('True')
            ];

            $template['negate'] = [
                '#type' => 'radios',
                '#options' => $negateOptions,
                '#default_value' => 0,
                '#attributes' => [
                    'class' => ['negate-radio']
                ]
            ];
        }

        $templateWrapper = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['schedule-item-wrapper']
            ],
        ];

        $templateWrapper['item'] = $template;

        $templateWrapper['summary'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'class' => ['summary-wrap']
            ],
            'summaryText' => [
                '#type' => 'html_tag',
                '#tag' => 'span',
                '#attributes' => [
                    'class' => ['summary-text']
                ]
            ],
            'editButton' => [
                '#type' => 'html_tag',
                '#tag' => 'button',
                '#value' => t('Edit'),
                '#attributes' => [
                    'class' => ['schedule-edit-button', 'button', 'button--extrasmall', 'project__action_button', 'action-link'],
                    'type' => 'button'
                ]
            ],
            'doneButton' => [
                '#type' => 'html_tag',
                '#tag' => 'button',
                '#value' => t('Done'),
                '#attributes' => [
                    'class' => ['schedule-done-button', 'button', 'button--extrasmall', 'project__action_button', 'action-link'],
                    'type' => 'button',
                    'style' => 'display:none;'
                ]
            ]
        ];

        $element['holidayTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'class' => ['holidayfield-template'],
                'type' => 'text/template'
            ],
            'holidayField' => [
                '#type' => 'checkbox',
                '#title' => 'Example Holiday',
                '#value' => 'example',
                '#attributes' => [
                    'class' => ['holiday-select-checkbox']
                ]
            ]
        ];


        $element['monthTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'class' => ['monthfield-template'],
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
                '#value_callback' => null,
            ],
        ];

        $element['#template'] = $templateWrapper;

        $carryOver = $element['#carryOver'] ?? [];
        $carryOver[] = 'timezone';
        $element['#carryOver'] = $carryOver;
        $element['#object_def'] = [
            'extractValueCallback' => 'Drupal.behaviors.scheduleElement.extractValue',
            'setCallBack' => 'Drupal.behaviors.scheduleElement.setInputs',
        ];

        $element['#attached']['library'][] = 'sprowt_settings/schedule_element';

        return $element;
    }

    public static function validateElement(&$element, FormStateInterface $form_state, &$complete_form)
    {

        parent::validateElement($element, $form_state, $complete_form);
        $input_exists = false;
        $input = NestedArray::getValue($form_state
            ->getValues(), $element['#parents'], $input_exists);
        if($input_exists) {
            if(is_array($input) && isset($input['monthTemplate'])) {
                unset($input['monthTemplate']);
                $form_state->setValueForElement($element, $input);
            }
            if(is_array($input) && isset($input['holidayTemplate'])) {
                unset($input['holidayTemplate']);
                $form_state->setValueForElement($element, $input);
            }
        }
        if(empty($input)) {
            $form_state->setValueForElement($element, null);
        }
    }
}
