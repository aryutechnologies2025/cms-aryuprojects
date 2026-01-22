<?php


namespace Drupal\lawnbot\Plugin\WebformHandler;

use Cassandra\Uuid;
use Drupal\Core\Form\ConfigFormBaseTrait;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\lawnbot\Entity\Servicebot;
use Drupal\lawnbot\LawnbotService;
use Drupal\sprowt_settings\StateTrait;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformMessageManagerInterface;
use Drupal\webform\WebformSubmissionInterface;
use http\Client\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 *
 * @WebformHandler(
 *  id = "Lawnbot integration",
 *  label = @Translation("ServiceBot integration"),
 *  category = @Translation("ServiceBot"),
 *  description = @Translation("Integrates this webform with ServiceBot"),
 *  cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *  results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *  submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED
 * )
 *
 */
class LawnbotWebformHandler extends WebformHandlerBase
{

    use StateTrait;
    use MessengerTrait;

    protected $lawnBotConfigName = 'lawnbot.settings';

    protected $defaultLawnbotUrl = '/instantquote';

    protected $lawnBotValues = [];

    protected $webformSubmissionId;

    protected function getGlobalConfig() {
        return $this->configFactory->get($this->lawnBotConfigName);
    }

    protected function testAddressElement($key) {
        $element = $this->getWebform()->getElement($key);
        if($element['#type'] != 'webform_address') {
            return false;
        }
        $requiredFields = [
            'address',
            'postal_code'
        ];
        $return = true;
        foreach($requiredFields as $field) {
            if(isset($element['#' . $field . '__access']) && empty( $element['#' . $field . '__access'])) {
                $return = false;
            }
        }
        return $return;
    }

    protected function handlerEnabled() {
        $enabled = $this->getSetting('enable_lawnbot_integration');
        return !empty($enabled);
    }

    public function getLinkedServiceBot() {
        $state = \Drupal::state();
        $key = 'servicebot.webform-link.' . $this->getWebform()->id();
        $uuid = $state->get($key);
        if(!isset($uuid)) {
            return null;
        }
        $storage = \Drupal::entityTypeManager()->getStorage('servicebot');
        $bots = $storage->loadByProperties([
            'uuid' => $uuid
        ]);
        if(empty($bots)) {
            return null;
        }
        return array_shift($bots);
    }

    public function setLinkedServiceBot($botOrUuid) {
        if($botOrUuid instanceof Servicebot) {
            $botOrUuid = $botOrUuid->uuid();
        }
        $state = \Drupal::state();
        $key = 'servicebot.webform-link.' . $this->getWebform()->id();
        return $state->set($key, $botOrUuid);
    }

    public function botEnabled() {
        $bot = $this->getLinkedServiceBot();
        if($bot instanceof Servicebot) {
            return $bot->isEnabled();
        }
        return false;
    }

    public function getSummary() {
        $globalEnabled = $this->botEnabled();
        $bot = $this->getLinkedServiceBot();
        if(!$this->handlerEnabled() || !$globalEnabled) {
            $values = [
                'Integration enabled' => 'False',
            ];
            if($bot instanceof Servicebot) {
                $values['ServiceBot'] = $bot->label();
                if(!$bot->isEnabled()) {
                    $values['ServiceBot'] .= ' (Disabled)';
                }
            }
        }
        else {
            $values = [
                'Integration enabled' => 'True'
            ];
            if($bot instanceof Servicebot) {
                $values['ServiceBot'] = $bot->label();
            }
            $keys = [
                'open_in_modal',
                'add_opt_in',
                'first_or_full_name_key',
                'last_name_key',
                'phone_key',
                'email_key',
                'address_key',
                'zip_key',
                'city_key',
                'state_key'
            ];

            $form = $this->buildConfigurationForm([], new FormState());
            foreach($keys as $key) {
                $configFormElement = $form[$key];
                $elementKey = $this->getSetting($key);
                if($key == 'open_in_modal' || $key == 'add_opt_in') {
                    $values[(string) $configFormElement['#title']] = !empty($elementKey) ? 'True' : 'False';
                }
                else {
                    $element = null;
                    if (!empty($elementKey)) {
                        $element = $this->getWebform()->getElement($elementKey);
                    }
                    $values[(string) $configFormElement['#title']] = !empty($element) ? (string) $element['#title'] : ' -- ';
                }
            }
        }
        $list = [];
        foreach($values as $label => $value) {
            $list[] = "<strong>$label:</strong> $value";
        }
        $markup = "<div>" . implode('<br>', $list) . '</div>';
        return [
            'summary' => [
                '#type' => 'markup',
                '#markup' => Markup::create($markup)
            ]
        ];
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $storage = \Drupal::entityTypeManager()->getStorage('servicebot');
        $bots = $storage->loadMultiple();

        if(empty($bots)) {
            $form['message'] = [
                '#type' => 'markup',
                '#markup' => Markup::create('
                    <p>You must <a href="/admin/config/services/servicebot">add at least one servicebot</a> before you can edit these settings.</p>
                ')
            ];
            return $form;
        }

        $botOptions = [];
        /** @var Servicebot $bot */
        foreach($bots as $bot) {
            $botOptions[$bot->uuid()] = $bot->label();
            if(!$bot->isEnabled()) {
                $botOptions[$bot->uuid()] .= ' (Disabled)';
            }
        }
        $defaultBot = $this->getLinkedServiceBot();
        if(count($bots) == 1 && empty($defaultBot)) {
            $keys = array_keys($bots);

            $defaultBot = $bots[$keys[0]];
        }

        $form['servicebot'] = [
            '#weight' => 1,
            '#type' => 'select',
            '#title' => 'ServiceBot to Use',
            '#options' => $botOptions,
            '#required' => true,
            '#empty_value' => '',
            '#default_value' => isset($defaultBot) ? $defaultBot->uuid() : null
        ];


        $webform = $this->getWebform();
        $elements = $webform->getElementsDecodedAndFlattened();
        $elementOptions = [];
        $addressFields = [];
        foreach($elements as $key => $element) {
            switch($element['#type']) {
                case 'webform_markup':
                case 'captcha':
                case 'webform_actions':
                case 'fieldset':
                    //do nothing
                    break;
                case 'webform_address':
                    $addressFields[$key] = $element;
                    break;
                default:
                    $elementOptions[$key] = $element['#title'];
            }
        }

        $addressOptions = array_merge([
            '' => '- Select -'
        ],$elementOptions);

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#address__access']) && empty($addressField['#address__access']))) {
                $addressOptions[$key] = $addressField['#title'];
            }
        }

        $zipOptions = array_merge([
            '' => '- Select -'
        ],$elementOptions);

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#postal_code__access']) && empty($addressField['#postal_code__access']))) {
                $zipOptions[$key] = $addressField['#title'];
            }
        }

        $cityOptions = array_merge([
            '' => '- Select -'
        ],$elementOptions);

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#city__access']) && empty($addressField['#city__access']))) {
                $cityOptions[$key] = $addressField['#title'];
            }
        }


        $stateOptions = array_merge([
            '' => '- Select -'
        ],$elementOptions);

        foreach($addressFields as $key => $addressField) {
            if(!(isset($addressField['#state_province__access']) && empty($addressField['#state_province__access']))) {
                $stateOptions[$key] = $addressField['#title'];
            }
        }

        $form['enable_lawnbot_integration'] = [
            '#title' => t('Enable ServiceBot Integration'),
            '#type' => 'checkbox',
            '#weight' => -1,
            '#default_value' => $this->getSetting('enable_lawnbot_integration') ?? false,
            '#attributes' => [
                'class' => ['lawnbot-enable-check']
            ],
        ];

        $form['lawnbot_url'] = [
            '#title' => t('ServiceBot URL'),
            '#type' => 'textfield',
            '#weight' => 1,
            '#required' => true,
            '#default_value' => !empty($this->getSetting('lawnbot_url')) ? $this->getSetting('lawnbot_url') : $this->defaultLawnbotUrl,
            '#field_suffix' => '<a href="#" type="button" class="button enable-url-button">Edit</a>',
            '#description' => 'URL where the ServiceBot is located. Don\'t change unless you want to redirect away from the site to another ServiceBot.',
            '#attributes' => [
                'class' => ['lawnbot-url-field'],
                'disabled' => 'disabled'
            ]
        ];

        $exclusions = $this->getSetting('exclusions') ?? [];
        $excludeTemplate = [
            '#type' => 'fieldset',
            '#attributes' => [
                'class' => ['exclusion']
            ]
        ];
        $excludeTemplate['excludeField'] = [
            '#type' => 'select',
            '#title' => 'Exclude Field',
            '#options' => $elementOptions,
            '#attributes' => [
                'class' => ['exclude-field']
            ]
        ];
        $excludeTemplate['excludeValue'] = [
            '#type' => 'textfield',
            '#title' => 'Exclude Value',
            '#attributes' => [
                'class' => ['exclude-value']
            ]
        ];
        $excludeTemplate['excludeRemove'] = [
            '#type' => 'html_tag',
            '#tag' => 'button',
            '#value' => 'Remove',
            '#attributes' => [
                'class' => ['exclude-remove', 'button', 'button--danger'],
                'type' => 'button'
            ]
        ];

        $form['excludeTemplate'] = [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
                'id' => 'excludeTemplate',
                'type' => 'text/html+template'
            ],
            'exclude' => $excludeTemplate
        ];

        $form['excludeCheck'] = [
            '#type' => 'checkbox',
            '#title' => 'Use a field to exclude the use of this servicebot',
            '#attributes' => [
                'id' => 'excludeCheck'
            ],
            '#default_value' => !empty($exclusions)
        ];

        $form['exclusions'] = [
            '#type' => 'hidden',
            '#default_value' => empty($exclusions) ? '[]' : json_encode($exclusions),
            '#attributes' => [
                'id' => 'exclusions'
            ]
        ];

        $form['excludeFieldset'] = [
            '#type' => 'fieldset',
            '#title' => 'Exclude options',
            '#description' => 'Add fields and values that when submitted will prevent the ServiceBot from popping up. NOTE: If the field has options then the value should be the option VALUE and not the option TEXT.',
            '#attributes' => [
                'id' => 'excludeFieldset',
                'style' => 'display:none;'
            ],
            'addExclusion' => [
                '#type' => 'html_tag',
                '#tag' => 'button',
                '#value' => 'Add',
                '#attributes' => [
                    'id' => 'addExclusion',
                    'class' => ['button'],
                    'type' => 'button'
                ]
            ]
        ];
        $form['excludeFieldset']['excludeWrap'] = [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
                'id' => 'excludeWrap'
            ]
        ];

        $form['open_in_modal'] = [
            '#title' => t('Open ServiceBot in a modal'),
            '#type' => 'checkbox',
            '#weight' => 1,
            '#default_value' => $this->getSetting('open_in_modal') ?? true,
            '#description' => 'If checked, the ServiceBot will open in a modal on the thank you page. If not, the form will redirect to the ServiceBot in full screen.'
        ];

        $form['add_opt_in'] = [
            '#title' => t('Add a field for opting into ServiceBot'),
            '#type' => 'checkbox',
            '#weight' => 1,
            '#default_value' => $this->getSetting('add_opt_in') ?? false,
            '#description' => 'This will add a checkbox in the form which will allow the user to opt into ServiceBot.',
        ];

        $addressSendOptions = [
            'components' => 'As address components',
            'geolocate' => 'As geolocation string'
        ];

        $form['address_send'] = [
            '#title' => t('Method to use to send the address'),
            '#type' => 'select',
            '#weight' => 1,
            '#options' => $addressSendOptions,
            '#required' => true,
            '#default_value' => $this->getSetting('address_send') ?? 'components',
            '#description' => 'Servicebot has 2 methods to receive the address. One is as separate address components, the other is as a single string which servicebot geolocates with google (picking the first response)',
        ];


        $form['first_or_full_name_key'] = [
            '#weight' => 2,
            '#title' => 'First or Full Name Field',
            '#type' => 'select',
            '#description' => 'Select a value for either the first name or full name component',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $elementOptions
            ),
            '#default_value' => $this->getSetting('first_or_full_name_key'),
            '#attributes' => [
                'class' => ['component-field', 'map-required']
            ]
        ];

        $form['last_name_key'] = [
            '#weight' => 2,
            '#title' => 'Last Name Field',
            '#type' => 'select',
            '#description' => 'Leave empty if there is only a full name',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $elementOptions
            ),
            '#default_value' => $this->getSetting('last_name_key'),
            '#attributes' => [
                'class' => ['component-field']
            ]
        ];

        $form['phone_key'] = [
            '#weight' => 2,
            '#title' => 'Phone number Field',
            '#type' => 'select',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $elementOptions
            ),
            '#default_value' => $this->getSetting('phone_key'),
            '#attributes' => [
                'class' => ['component-field', 'map-required']
            ]
        ];

        $form['email_key'] = [
            '#weight' => 2,
            '#title' => 'Email Field',
            '#type' => 'select',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $elementOptions
            ),
            '#default_value' => $this->getSetting('email_key'),
            '#attributes' => [
                'class' => ['component-field', 'map-required']
            ]
        ];

        $form['address_key'] = [
            '#weight' => 2,
            '#title' => 'Street Address Element',
            '#type' => 'select',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $addressOptions
            ),
            '#default_value' => $this->getSetting('address_key'),
            '#description' => 'This could be a text field element or an address element with the street address field enabled',
            '#attributes' => [
                'class' => ['component-field', 'map-required', 'no-option-disable']
            ],
        ];

        $form['zip_key'] = [
            '#weight' => 2,
            '#title' => 'Zip code Element',
            '#type' => 'select',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $zipOptions
            ),
            '#default_value' => $this->getSetting('zip_key'),
            '#description' => 'This could be a text field element/number element or an address element with the zip/postal code field enabled',
            '#attributes' => [
                'class' => ['component-field', 'map-required', 'no-option-disable']
            ],
        ];

        $form['city_key'] = [
            '#weight' => 2,
            '#title' => 'City Element',
            '#type' => 'select',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $cityOptions
            ),
            '#default_value' => $this->getSetting('city_key'),
            '#description' => 'This could be a text field element or an address element with the city field enabled',
            '#attributes' => [
                'class' => ['component-field', 'no-option-disable']
            ],
        ];

        $form['state_key'] = [
            '#weight' => 2,
            '#title' => 'State Element',
            '#type' => 'select',
            '#options' => array_merge(
                array(
                    '' => '- Select -'
                ), $stateOptions
            ),
            '#default_value' => $this->getSetting('state_key'),
            '#description' => 'This could be a text field element or an address element with the state/province field enabled',
            '#attributes' => [
                'class' => ['component-field', 'no-option-disable']
            ],
        ];

        $form['#attached']['library'][] = 'lawnbot/handler_settings';

        return $form;
    }

    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $requiredKeys = [
            'first_or_full_name_key',
            'phone_key',
            'email_key',
            'address_key',
            'zip_key',
            'address_send'
        ];

        $enabled = $form_state->getValue('enable_lawnbot_integration') ?? false;
        if($enabled) {
            foreach($requiredKeys as $key) {
                $value = $form_state->getValue($key);
                if(empty($value) && !empty($form[$key])) {
                    $form_state->setError($form[$key], 'This field is required');
                }
                elseif (empty($value)) {
                    $form_state->setErrorByName($key, 'The "'.$key.'" field is required');
                }
            }
        }

        parent::validateConfigurationForm($form, $form_state);
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $settingKeys = [
            'enable_lawnbot_integration',
            'lawnbot_url',
            'open_in_modal',
            'address_send',
            'add_opt_in',
            'first_or_full_name_key',
            'last_name_key',
            'phone_key',
            'email_key',
            'address_key',
            'zip_key',
            'city_key',
            'state_key'
        ];
        foreach($settingKeys as $settingKey) {
            $val = $form_state->getValue($settingKey);
            if(isset($val) && $val !== '') {
                $this->setSetting($settingKey, $val);
            }
            else {
                $this->setSetting($settingKey, null);
            }
        }

        $botUuid = $form_state->getValue('servicebot');
        $this->setLinkedServiceBot($botUuid);

        $excludeCheck = $form_state->getValue('excludeCheck');
        if(!empty($excludeCheck)) {
            $exclusions = $form_state->getValue('exclusions');
        }
        else {
            $exclusions = null;
        }
        if(!empty($exclusions)) {
            $exclusions = json_decode($exclusions, true);
        }
        if(!empty($exclusions)) {
            $this->setSetting('exclusions', $exclusions);
        }
        else {
            $this->setSetting('exclusions', []);
        }

        parent::submitConfigurationForm($form, $form_state);
    }

    protected function searchForFormElement(&$form, $key) {
        foreach($form as $formKey => &$element) {
            if($key === $formKey) {
                return $element;
            }
            if(is_array($element)) {
                $return = $this->searchForFormElement($element, $key);
                if(!empty($return)) {
                    return $return;
                }
            }
        }
        return null;
    }

    protected function updateFormElement(&$form, $key, $element) {
        foreach($form as $formKey => &$formElement) {
            if($formKey === $key) {
                $form[$formKey] = $element;
                return $element;
            }
            if(is_array($formElement)) {
                $return = $this->updateFormElement($formElement, $key, $element);
                if(!empty($return)) {
                    return $return;
                }
            }
        }
        return false;
    }

    protected function searchForOptIn(&$form) {
        return $this->searchForFormElement($form, 'instant_quote');
    }

    public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        if(!$this->handlerEnabled()) {
            return;
        }
        $optInSetting = $this->getSetting('add_opt_in');
        if(!empty($optInSetting)) {
            // add optin if necessary
            $optIn = $this->searchForOptIn($form);
            if(empty($optIn)) {
                if(!isset($form['elements']['submission'])) {
                    $form['elements']['submission'] = [];
                }
                $form['elements']['submission']['instant_quote'] = [
                    '#type' => 'checkbox',
                    '#title' => "Check this if you'd like to instantly receive a quote using our automated service.",
                    '#weight' => -50
                ];
            }
        }

        // force required elements to be required
        $requiredKeys = [
            'first_or_full_name_key',
            'last_name_key',
            'phone_key',
            'email_key'
        ];

        foreach($requiredKeys as $settingsKey) {
            $formKey = $this->getSetting($settingsKey);
            if(!empty($formKey)) {
                $element = $this->searchForFormElement($form, $formKey);
                $element['#required'] = true;
                $this->updateFormElement($form, $formKey, $element);
            }
        }

        $addressKey = $this->getSetting('address_key');
        $addressElement = $this->searchForFormElement($form, $addressKey);

        if($addressElement['#type'] == 'webform_address') {
            $addressElement['#address__required'] = true;
            $addressElement['#webform_composite_elements']['address']['#required'] = true;
            $this->updateFormElement($form, $addressKey, $addressElement);
        }
        elseif($addressElement['#type'] == 'sprowt_address_autocomplete') {
            $addressElement['#address__required'] = true;
            $addressElement['#webform_composite_elements']['address']['#required'] = true;
            $addressElement['#requireFound'] = true;
            $this->updateFormElement($form, $addressKey, $addressElement);
        }
        else {
            $addressElement['#required'] = true;
            $this->updateFormElement($form, $addressKey, $addressElement);
        }

        $zipKey = $this->getSetting('zip_key');
        $zipElement = $this->searchForFormElement($form, $zipKey);

        if($zipElement['#type'] == 'webform_address') {
            $zipElement['#postal_code__required'] = true;
            $zipElement['#webform_composite_elements']['postal_code']['#required'] = true;
            $this->updateFormElement($form, $zipKey, $zipElement);
        }
        elseif($zipElement['#type'] == 'sprowt_address_autocomplete') {
            $zipElement['#address__required'] = true;
            $zipElement['#webform_composite_elements']['address']['#required'] = true;
            $zipElement['#requireFound'] = true;
            $this->updateFormElement($form, $zipKey, $zipElement);
        }
        else {
            $zipElement['#required'] = true;
            $this->updateFormElement($form, $zipKey, $zipElement);
        }
    }

    protected function setLawnBotValues(WebformSubmissionInterface $webform_submission) {
        $array = [
            'source' => 'contactForm',
        ];
        $map = [
            'phonenumber' => 'phone_key',
            'customerName' => 'first_or_full_name_key',
            'customerEmail' => 'email_key',
        ];
        foreach($map as $key => $settingsKey) {
            $subKey = $this->getSetting($settingsKey);
            if(!empty($subKey)) {
                $array[$key] = $webform_submission->getElementData($subKey);
            }
        }
        $lastNameKey = $this->getSetting('last_name_key');
        if(!empty($lastNameKey)) {
            $lastName = $webform_submission->getElementData($lastNameKey);
            if(!empty($lastName)) {
                $array['customerName'] .= " $lastName";
            }
        }
        $addressKey = $this->getSetting('address_key');
        $addressElement = $this->getWebform()->getElement($addressKey);
        if ($addressElement['#type'] == 'webform_address' || $addressElement['#type'] == 'sprowt_address_autocomplete') {
            $addressData = $webform_submission->getElementData($addressKey);
            if (!empty($addressData) && !empty($addressData['address'])) {
                $array['customerAddress'] = $addressData['address'];
            }
        } else {
            $array['customerAddress'] = $webform_submission->getElementData($addressKey);
        }

        $zipKey = $this->getSetting('zip_key');
        $zipElement = $this->getWebform()->getElement($zipKey);
        if ($zipElement['#type'] == 'webform_address' || $zipElement['#type'] == 'sprowt_address_autocomplete') {
            $addressData = $webform_submission->getElementData($zipKey);
            if (!empty($addressData) && !empty($addressData['postal_code'])) {
                $array['customerZip'] = $addressData['postal_code'];
            }
        } else {
            $array['customerZip'] = $webform_submission->getElementData($zipKey);
        }

        $cityKey = $this->getSetting('city_key');
        if (!empty($cityKey)) {
            $element = $this->getWebform()->getElement($cityKey);
            if ($element['#type'] == 'webform_address' || $element['#type'] == 'sprowt_address_autocomplete') {
                $addressData = $webform_submission->getElementData($cityKey);
                if (!empty($addressData) && !empty($addressData['city'])) {
                    $array['customerCity'] = $addressData['city'];
                }
            } else {
                $val = $webform_submission->getElementData($cityKey);
                if (!empty($val)) {
                    $array['customerCity'] = $val;
                }
            }
        }

        $stateKey = $this->getSetting('state_key');
        if (!empty($stateKey)) {
            $element = $this->getWebform()->getElement($stateKey);
            if ($element['#type'] == 'webform_address' || $element['#type'] == 'sprowt_address_autocomplete') {
                $addressData = $webform_submission->getElementData($stateKey);
                if (!empty($addressData) && !empty($addressData['state_province'])) {
                    $array['customerState'] = $addressData['state_province'];
                }
            } else {
                $val = $webform_submission->getElementData($stateKey);
                if (!empty($val)) {
                    $array['customerState'] = $val;
                }
            }
        }

        if (in_array($array['customerState'], array_values(static::$states))) {
            $array['customerState'] = $this->getStateAbbreviation($array['customerState']);
        }

        if (!in_array($array['customerState'], array_keys(static::$states))) {
            unset($array['customerState']);
        }

        $addressSendMethod = $this->getSetting('address_send') ?? 'components';
        if($addressSendMethod == 'geolocate') {
            $addressKey = $this->getSetting('address_key');
            $addressElement = $this->getWebform()->getElement($addressKey);
            if ($addressElement['#type'] == 'sprowt_address_autocomplete') {
                $addressData = $webform_submission->getElementData($addressKey);
                if (!empty($addressData) && !empty($addressData['formattedAddress'])) {
                    $array['googlelocate'] = $addressData['formattedAddress'];
                }
            }
            else {
                $address = [
                    $array['customerAddress'] ?? '',
                    $array['customerCity'] ?? '',
                    $array['customerState'] ?? '',
                    $array['customerZip'] ?? ''
                ];
                $array['googlelocate'] = implode(', ', $address);
            }
            $unset = [
                'customerAddress',
                'customerCity',
                'customerState',
                'customerZip'
            ];
            foreach ($array as $key => $val) {
                if(in_array($key, $unset)) {
                    unset($array[$key]);
                }
            }
        }

        $bot = $this->getLinkedServiceBot();
        if($bot instanceof Servicebot) {
            $array['serviceBotId'] = $bot->uuid();
        }

        $this->lawnBotValues = $array;
        return $this->lawnBotValues;
    }

    protected function lawnbotValuesValid() {
        $required = [
            'source',
            'phonenumber',
            'customerName',
            'customerEmail',
            'serviceBotId'
        ];
        $valid = true;
        foreach($required as $key) {
            $valid &= !empty($this->lawnBotValues[$key]);
        }
        if(empty($this->lawnBotValues['customerAddress'])
            && empty($this->lawnBotValues['customerZip'])
            && empty($this->lawnBotValues['googlelocate'])
        ) {
            $valid = false;
        }

        return $valid;
    }

    /**
     * stolen from WebformSubmissionForm::getConfirmationUrl
     */
    protected function getConfirmationUrl() {
        $confirmation_url = trim($this->getWebform()->getSetting('confirmation_url', ''));
        if(strpos($confirmation_url, '[') !== false) {
            /** @var Token $tokenService */
            $tokenService = \Drupal::service('token');
            $data = [];
            $submission = $this->getWebformSubmission();
            if ($submission instanceof WebformSubmissionInterface) {
                $data['webform_submission'] = $this->getWebformSubmission();
            }
            $confirmation_url = $tokenService->replace($confirmation_url, $data);
        }

        if (strpos($confirmation_url, '/') === 0) {
            // Get redirect URL using an absolute URL for the absolute  path.
            $redirect_url = Url::fromUri(\Drupal::request()->getSchemeAndHttpHost() . $confirmation_url);
        }
        elseif (preg_match('#^[a-z]+(?:://|:)#', $confirmation_url)) {
            // Get redirect URL from URI (i.e. http://, https:// or ftp://)
            // and Drupal custom URIs (i.e internal:).
            $redirect_url = Url::fromUri($confirmation_url);
        }
        elseif (strpos($confirmation_url, '<') === 0) {
            // Get redirect URL from special paths: '<front>' and '<none>'.
            $redirect_url = \Drupal::service('path.validator')->getUrlIfValid($confirmation_url);
        }
        else  {
            // Get redirect URL by validating the Drupal relative path which does not
            // begin with a forward slash (/).
            $confirmation_url = \Drupal::service('path_alias.manager')->getPathByAlias('/' . $confirmation_url);
            $redirect_url = \Drupal::service('path.validator')->getUrlIfValid($confirmation_url);
        }

        return $redirect_url;
    }

    protected function setModalRedirect(FormStateInterface $formState) {
        $confirmation_type = $this->getWebform()->getSetting('confirmation_type');
        switch ($confirmation_type) {
            case WebformInterface::CONFIRMATION_PAGE:
                $response = $formState->getResponse();
                break;

            case WebformInterface::CONFIRMATION_URL:
            case WebformInterface::CONFIRMATION_URL_MESSAGE:
                $redirect_url = $this->getConfirmationUrl();
                if ($redirect_url) {
                    $response = $formState->getResponse();
                }
                else {
                    $redirect = $formState->getRedirect();
                    if($redirect instanceof RedirectResponse) {
                        $response = $redirect;
                    }
                    elseif($redirect instanceof Url) {
                        $response = new TrustedRedirectResponse($redirect->setAbsolute(true)->toString());
                    }
                }
                break;
            default:
                // Get current route name, parameters, and options.
                $route_name = \Drupal::routeMatch()->getRouteName();
                $route_parameters = \Drupal::routeMatch()->getRawParameters()->all();
                $route_options = [];

                // Add current query to route options.
                if (!$this->getWebform()->getSetting('confirmation_exclude_query')) {
                    $query = \Drupal::request()->query->all();
                    // Remove Ajax parameters from query.
                    unset($query['ajax_form'], $query['_wrapper_format']);
                    if ($query) {
                        $route_options['query'] = $query;
                    }
                }
                $url = Url::fromRoute($route_name, $route_parameters, $route_options);
                $response = new TrustedRedirectResponse($url->setAbsolute(true)->toString());
                break;
        }
        if($response instanceof RedirectResponse) {
            $urlStr = $response->getTargetUrl();
            $url = Url::fromUri($urlStr);
            $query = $url->getOption('query') ?? [];
            if(empty($this->webformSubmissionId)) {
                $this->webformSubmissionId = \Drupal::service('uuid')->generate();
            }
            $query['servicebot'] = $this->webformSubmissionId;
            $url->setOption('query', $query);

            $state = \Drupal::state();
            $keyBase = 'lawnbot.' . $this->webformSubmissionId;
            $queryKey = $keyBase . '.query';
            $urlKey = $keyBase . '.modalUrl';
            $expireArray = $state->get('lawnbot.expires', []);
            $expireArray[$this->webformSubmissionId] = time() + 3600;
            $state->set('lawnbot.expires', $expireArray);
            $state->set($queryKey, $this->lawnBotValues);
            $state->set($urlKey, $url->setAbsolute(true)->toString());
            $response = new TrustedRedirectResponse($url->setAbsolute(true)->toString());
            $formState->setResponse($response);
            return $formState;
        }

        return $this->setRedirect($formState, true);
    }

    public function setRedirect(FormStateInterface $formState, $forcePage = false) {
        $confirmation_type = $this->getWebform()->getSetting('confirmation_type');
        $inModal = $this->getSetting('open_in_modal');
        if($confirmation_type != WebformInterface::CONFIRMATION_URL
            &&  $confirmation_type != WebformInterface::CONFIRMATION_URL_MESSAGE
        ) {
            $inModal = true;
        }
        if($forcePage) {
            $inModal = false;
        }
        if($inModal) {
            return $this->setModalRedirect($formState);
        }

        $confirmation_type = $this->getWebform()->getSetting('confirmation_type');
        switch ($confirmation_type) {
            case WebformInterface::CONFIRMATION_PAGE:
                $currentResponse = $formState->getResponse();
                break;

            case WebformInterface::CONFIRMATION_URL:
            case WebformInterface::CONFIRMATION_URL_MESSAGE:
                $redirect_url = $this->getConfirmationUrl();
                if ($redirect_url) {
                    $currentResponse = $formState->getResponse();
                }
                else {
                    $redirect = $formState->getRedirect();
                    if($redirect instanceof RedirectResponse) {
                        $currentResponse = $redirect;
                    }
                    elseif($redirect instanceof Url) {
                        $currentResponse = new TrustedRedirectResponse($redirect->setAbsolute(true)->toString());
                    }
                }
                break;
            default:
                // Get current route name, parameters, and options.
                $route_name = \Drupal::routeMatch()->getRouteName();
                $route_parameters = \Drupal::routeMatch()->getRawParameters()->all();
                $route_options = [];

                // Add current query to route options.
                if (!$this->getWebform()->getSetting('confirmation_exclude_query')) {
                    $query = \Drupal::request()->query->all();
                    // Remove Ajax parameters from query.
                    unset($query['ajax_form'], $query['_wrapper_format']);
                    if ($query) {
                        $route_options['query'] = $query;
                    }
                }
                $url = Url::fromRoute($route_name, $route_parameters, $route_options);
                $currentResponse = new TrustedRedirectResponse($url->setAbsolute(true)->toString());
                break;
        }

        $absUrl = $currentResponse->getTargetUrl();
        $uri_parts = parse_url($absUrl);
        $currentQuery =  [];
        if(!empty($uri_parts['query'])) {
            parse_str($uri_parts['query'], $currentQuery);
        }
        $query = array_merge($currentQuery, $this->lawnBotValues);

        $url = LawnbotService::getLawnbotUrl($query);
        $response = new TrustedRedirectResponse($url->setAbsolute(true)->toString());
        $formState->setResponse($response);
        return $formState;
    }

    public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
    {
        if(!$this->handlerEnabled() || !$this->botEnabled()) {
            return;
        }
        $optIn = true;
        $optInSetting = $this->getSetting('add_opt_in');
        if(!empty($optInSetting)) {
            $optIn = $form_state->getValue('instant_quote', false);
        }
        if(empty($optIn)) {
            return null;
        }
        $excluded = false;
        $exclusions = $this->getSetting('exclusions') ?? [];
        foreach($exclusions as $exclusion) {
            $val = $webform_submission->getElementData($exclusion['field']);
            if(!is_array($val)) {
                $val = [$val];
            }
            foreach($val as $v) {
                if (!empty($exclusion['value']) && $v == $exclusion['value']) {
                    $excluded = true;
                }
            }
        }
        if(!empty($excluded)) {
            return;
        }


        $this->setLawnBotValues($webform_submission);
        if($this->lawnbotValuesValid()) {
            $this->webformSubmissionId = null;
            $this->webformSubmissionId = $webform_submission->uuid();
            $this->setRedirect($form_state);
        }

        parent::confirmForm($form, $form_state, $webform_submission);
    }

}
