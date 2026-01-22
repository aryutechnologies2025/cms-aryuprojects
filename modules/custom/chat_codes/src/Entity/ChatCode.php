<?php

namespace Drupal\chat_codes\Entity;

//sometime this isn't loaded?
require_once DRUPAL_ROOT . '/modules/custom/sprowt_settings/src/EntityVisibilityTrait.php';

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\chat_codes\ChatCodeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\sprowt_settings\EntityVisibilityTrait;
use Drupal\webform\Plugin\WebformElement\DateTime;

/**
 * Defines the chat code entity class.
 *
 * @ContentEntityType(
 *   id = "chat_code",
 *   label = @Translation("Chat Code"),
 *   label_collection = @Translation("Chat Codes"),
 *   handlers = {
 *     "view_builder" = "Drupal\chat_codes\ChatCodeViewBuilder",
 *     "list_builder" = "Drupal\chat_codes\ChatCodeListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\chat_codes\Form\ChatCodeForm",
 *       "edit" = "Drupal\chat_codes\Form\ChatCodeForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "chat_code",
 *   admin_permission = "administer chat codes",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/chat-codes/add",
 *     "canonical" = "/admin/structure/chat-codes/{chat_code}",
 *     "edit-form" = "/admin/structure/chat-codes/{chat_code}/edit",
 *     "delete-form" = "/admin/structure/chat-codes/{chat_code}/delete",
 *     "collection" = "/admin/structure/chat-codes"
 *   },
 * )
 */
class ChatCode extends ContentEntityBase implements ChatCodeInterface, EntityWithPluginCollectionInterface
{

    use EntityChangedTrait;
    use EntityVisibilityTrait;

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return $this->get('label')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setLabel($title)
    {
        $this->set('label', $title);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return (bool)$this->get('status')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($status)
    {
        $this->set('status', $status);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedTime()
    {
        return $this->get('created')->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedTime($timestamp)
    {
        $this->set('created', $timestamp);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
    {

        $fields = parent::baseFieldDefinitions($entity_type);

        $fields['label'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Label'))
            ->setDescription(t('The label for the chat.'))
            ->setRequired(TRUE)
            ->setSetting('max_length', 255)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => -5,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'label' => 'hidden',
                'type' => 'string',
                'weight' => -5,
            ])
            ->setDisplayConfigurable('view', TRUE);

        $fields['status'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Status'))
            ->setDescription(t('A boolean indicating whether the chat code is enabled.'))
            ->setDefaultValue(TRUE)
            ->setSetting('on_label', 'Enabled')
            ->setDisplayOptions('form', [
                'type' => 'boolean_checkbox',
                'settings' => [
                    'display_label' => FALSE,
                ],
                'weight' => 0,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayOptions('view', [
                'type' => 'boolean',
                'label' => 'above',
                'weight' => 0,
                'settings' => [
                    'format' => 'enabled-disabled',
                ],
            ])
            ->setDisplayConfigurable('view', TRUE);

        $fields['created'] = BaseFieldDefinition::create('created')
            ->setLabel(t('Authored on'))
            ->setDescription(t('The time that the chat code was created.'));

        $fields['changed'] = BaseFieldDefinition::create('changed')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the chat code was last edited.'));

        $fields['code'] = BaseFieldDefinition::create('string_long')
            ->setLabel(t('Code'))
            ->setDescription(t('Embed code for the chat code'))
            ->setRequired(TRUE)
            ->setDisplayOptions('form', [
                'type' => 'string_textarea',
                'label' => 'above',
                'weight' => 0,
            ]);

        $fields['page_restriction_negate'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Page restriction type'))
            ->setDescription(t('Page restriction type'));

        $fields['page_restrictions'] = BaseFieldDefinition::create('string_long')
            ->setLabel(t('Page restriction type'))
            ->setDescription(t('Page restriction type'));

        $fields['schedules'] = BaseFieldDefinition::create('map')
            ->setLabel(t('Schedules'))
            ->setDescription(t('Schedules for which the code should appear'))
            ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

        $fields['visibility'] = BaseFieldDefinition::create('map')
            ->setLabel(t('Visibility'))
            ->setDescription(t('Chat code visibility'));

        return $fields;
    }

    public function getSchedules() {
        $schedules = $this->get('schedules');
        $return = [];
        foreach($schedules as $scheduleItem) {
            $schedule = $scheduleItem->value;
            $return[$schedule['id']] = $schedule;
        }
        return $return;
    }

    public function setSchedules(array $schedules) {
        $values = array_values($schedules);
        $items = [];
        foreach($values as $value) {
            if(isset($value['value'])) {
                $items[] = $value;
            }
            else {
                $items[] = [
                    'value' => $value
                ];
            }
        }
        $this->set('schedules', $items);
        return $this;
    }

    public function getPageRestriction($pages = null, $negate = null)
    {
        /** @var ConditionManager $conditionManager */
        $conditionManager = \Drupal::service('plugin.manager.condition');
        $pages = isset($pages) ?  $pages : $this->get('page_restrictions')->value ?? '';
        $negate = isset($negate) ? $negate : $this->get('page_restriction_negate')->value ?? true;
        $negate = is_string($negate) ? (int) $negate : $negate;
        return $conditionManager->createInstance('request_path', [
            'pages' => $pages,
            'negate' => !empty($negate)
        ]);
    }

    public function restrictedByPage()
    {
        $pages = $this->get('page_restrictions')->value ?? '';
        if(empty(trim($pages))) {
            $hide = $this->get('page_restriction_negate')->value ?? true;
            $hide = is_string($hide) ? (int) $hide : $hide;
            return !$hide;
        }
        $condition = $this->getPageRestriction();
        $show = $condition->execute();
        return !$show;
    }

    protected function setTimeOnDate(\DateTimeInterface $date, string $time, $end = false) {
        $timeParts = explode(':', $time);
        $secs = 0;
        if($end) {
            $secs = 59;
        }
        $date->setTime($timeParts[0], $timeParts[1], $secs, 0);
        return $date;
    }

    protected function setDayRange(array $schedule, \DateTimeInterface $day) {
        $startDate = new \DateTime($day->format('c'));
        $endDate = new \DateTime($day->format('c'));
        if(is_array($schedule['time'])) {
            $startDate = $this->setTimeOnDate($startDate, $schedule['time']['from']);
            $endDate = $this->setTimeOnDate($endDate, $schedule['time']['to'], true);
        }
        else {
            $startDate = $this->setTimeOnDate($startDate, '00:00');
            $endDate = $this->setTimeOnDate($endDate, '23:59', true);
        }
        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    protected function restrictedBySchedule(array $schedule, \DateTimeInterface $date) {
        $tz = new \DateTimeZone($schedule['timezone']);
        $ranges = [];
        if($schedule['type'] == 'everyday') {
            $day = new \DateTime($date->format('Y-m-d') . ' 12:00:00', $tz);
            $ranges[] = $this->setDayRange($schedule, $day);
        }
        else if($schedule['type'] == 'daysoftheweek') {
            $days = $schedule['days'];
            foreach($days as $day) {
                if($date->format('w') == '0') {
                    $weekdate = new \DateTime($date->format('Y-m-d') . ' 12:00:00', $tz);
                }
                else {
                    $weekdate = new \DateTime('sunday last week');
                    $weekdate = new \DateTime($weekdate->format('Y-m-d') . ' 12:00:00', $tz);
                }

                if(!empty($day)) {
                    $weekdate->add(new \DateInterval('P' . $day . 'D'));
                }
                $ranges[] = $this->setDayRange($schedule, $weekdate);
            }
        }
        else if($schedule['type'] == 'daysofthemonth') {
            $days = $schedule['days'];
            foreach($days as $day) {
                $monthDate = new \DateTime();
                if($day > $monthDate->format('t')) {
                    $day = $monthDate->format('t');
                }
                if($day < 10) {
                    $day = '0' . $day;
                }
                $monthDate = new \DateTime($monthDate->format('Y-m') . '-' . $day .' 12:00:00', $tz);
                $ranges[] = $this->setDayRange($schedule, $monthDate);
            }
        }
        else if($schedule['type'] == 'daysoftheyear') {
            $days = $schedule['days'];
            foreach($days as $day) {
                $yearDate = new \DateTime();
                $yearDate = new \DateTime($yearDate->format('Y-') . $day .' 12:00:00', $tz);
                $ranges[] = $this->setDayRange($schedule, $yearDate);
            }
        }

        $restricted = true;
        foreach($ranges as $range) {
            $inRange = $date >= $range['start'] && $date <= $range['end'];
            $restricted &= !$inRange;
        }

        return $restricted;
    }

    public function restrictedBySchedules(?\DateTimeInterface $date = null) {
        if(empty($date)) {
            $date = new \DateTime();
        }
        $schedules = $this->getSchedules();
        if(empty($schedules)) {
            return false;
        }

        $restricted = true;
        foreach($schedules as $schedule) {
            $restricted &= $this->restrictedBySchedule($schedule, $date);
        }

        return $restricted;
    }


    public function render() {
        $label = $this->getLabel();
        $code = $this->get('code')->value;
        if(empty($code)) {
            return [];
        }
        return [
            'commentStart' => [
                '#type' => 'markup',
                '#markup' => Markup::create("\n<!-- START chat code: $label -->\n")
            ],
            'code' => [
                '#type' => 'markup',
                '#markup' => Markup::create($code)
            ],
            'commentEnd' => [
                '#type' => 'markup',
                '#markup' => Markup::create("\n<!-- END chat code: $label -->\n")
            ]
        ];
    }
}
