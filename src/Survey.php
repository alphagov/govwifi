<?php

namespace Alphagov\GovWifi;
use PDO;

/**
 * Class Survey
 *
 * Handles sending out surveys to our users based on survey settings in the database.
 *
 * @package Alphagov\GovWifi
 */
class Survey extends GovWifiBase {
    const SUCCESSFUL_EMAIL_SELF        = "Successful Email - self signup";
    const SUCCESSFUL_EMAIL_SPONSORED   = "Successful Email - sponsored";
    const SUCCESSFUL_TEXT_SELF         = "Successful Text - self signup";
    const UNSUCCESSFUL_EMAIL_SELF      = "Unsuccessful Email - self signup";
    const UNSUCCESSFUL_EMAIL_SPONSORED = "Unsuccessful Email - sponsored";
    const UNSUCCESSFUL_TEXT_SELF       = "Unsuccessful Text - self signup";

    const EMAIL_JOURNEYS = [
        self::SUCCESSFUL_EMAIL_SELF,
        self::SUCCESSFUL_EMAIL_SPONSORED,
        self::UNSUCCESSFUL_EMAIL_SELF,
        self::UNSUCCESSFUL_EMAIL_SPONSORED,
    ];

    const TEXT_JOURNEYS = [
        self::SUCCESSFUL_TEXT_SELF,
        self::UNSUCCESSFUL_TEXT_SELF
    ];

    const SUCCESSFUL_JOURNEYS = [
        self::SUCCESSFUL_EMAIL_SELF,
        self::SUCCESSFUL_EMAIL_SPONSORED,
        self::SUCCESSFUL_TEXT_SELF,
    ];

    const SPONSORED_JOURNEYS = [
        self::SUCCESSFUL_EMAIL_SPONSORED,
        self::UNSUCCESSFUL_EMAIL_SPONSORED,
    ];

    const SMS_CONTACT_CONDITION = "'+%'";
    const EMAIL_CONTACT_CONDITION = "'%@%'";
    /**
     * Instance of the environment-specific Config class.
     * @var Config
     */
    private $config;

    /**
     * Instance of the environment-specific DB class.
     * @var DB
     */
    private $db;

    /**
     * Survey constructor. The standard config and db parameters are required.
     *
     * @param $params
     */
    public function __construct($params) {
        $defaults = [
            'config'        => null,
            'db'            => null,
        ];
        $params = array_merge($defaults, $params);
        parent::checkNotEmpty(array_keys($defaults), $params);
        parent::checkStandardParams($params);

        $this->config        = $params['config'];
        $this->db            = $params['db'];
    }

    /**
     * Send out all active surveys to the users matching survey configuration.
     */
    public function sendSurveys() {
        error_log("SURVEY - started.");
        $configs = $this->getSurveyConfigs();
        if (!empty($configs)) {
            foreach ($configs as $surveyConfig) {
                if (in_array($surveyConfig['journey_type'], self::EMAIL_JOURNEYS)) {
                    $contactDetails = $this->getContactDetails(
                        in_array($surveyConfig['journey_type'], self::SUCCESSFUL_JOURNEYS),
                        self::EMAIL_CONTACT_CONDITION,
                        $surveyConfig['time_delay_minutes'],
                        $surveyConfig['survey_setting_id'],
                        true,
                        in_array($surveyConfig['journey_type'], self::SPONSORED_JOURNEYS)
                    );
                    if (!empty($contactDetails)) {
                        error_log("SURVEY - Sending survey to [" . count($contactDetails) . "] email addresses.");
                        $this->sendEmailToContacts($contactDetails, $surveyConfig);
                    } else {
                        error_log("SURVEY - no contacts found for [" . $surveyConfig['journey_type'] . "]");
                    }
                } else if (in_array($surveyConfig['journey_type'], self::TEXT_JOURNEYS)) {
                    $contactDetails = $this->getContactDetails(
                        in_array($surveyConfig['journey_type'], self::SUCCESSFUL_JOURNEYS),
                        self::SMS_CONTACT_CONDITION,
                        $surveyConfig['time_delay_minutes'],
                        $surveyConfig['survey_setting_id']
                    );
                    if (!empty($contactDetails)) {
                        error_log("SURVEY - Sending survey to [" . count($contactDetails) . "] mobile numbers.");
                        $this->sendSmsToContacts($contactDetails, $surveyConfig);
                    } else {
                        error_log("SURVEY - no contacts found for [" . $surveyConfig['journey_type'] . "]");
                    }
                } else {
                    error_log("SURVEY - journey type not recognised. [" . $surveyConfig['journey_type']. "]");
                }
            }
        } else {
            error_log("SURVEY - No active configurations found for the current environment.");
        }
        error_log("SURVEY - finished.");
    }

    /**
     * Retrieve the list of active survey configurations for the current environment.
     *
     * @return array the survey configurations in an associative array.
     */
    public function getSurveyConfigs() {
        $handle = $this->db->getConnection()->prepare(
            "SELECT * FROM survey_settings_view WHERE survey_active AND environment_name = ?");
        $handle->bindValue(1, $this->config->environment);
        $handle->execute();
        return $handle->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve the list of contact details for the users who have not opted out and are matching the conditions below.
     *
     * @param $loginSuccessful bool Have ever logged in successfully
     * @param $contactCondition string SQL LIKE condition to distinguish between email and mobile contacts
     * @param $timeDelayMinutes int The number of minutes passed since the user registered.
     * @param $surveySettingId int The database ID of the survey setting record we're using for this survey
     * @param $sponsoredOnly bool Whether or not the journey is restricted to consider sponsored registrations
     * @param $sponsored bool If sponsoredOnly is true, this decides if we are looking at sponsored or self-signup.
     *
     * @return array Associative array containing the contact details.
     */

    public function getContactDetails($loginSuccessful, $contactCondition, $timeDelayMinutes, $surveySettingId,
                                      $sponsoredOnly = false, $sponsored = false) {
        $sql = "SELECT distinct(userdetails.contact), userdetails.username FROM userdetails " .
            "LEFT JOIN logs ON userdetails.username = logs.username ".
            "LEFT JOIN survey_logs " .
                "ON (userdetails.username = survey_logs.username " .
                "AND survey_logs.survey_setting_id = ?) " .
            "WHERE ".
            "survey_logs.username IS NULL " .
            "NOT userdetails.survey_opt_out AND " .
            "logs.username IS " . ($loginSuccessful ? "NOT " : "") . "NULL AND " .
            ($sponsoredOnly ?
                ($sponsored ?
                    " userdetails.contact <> userdetails.sponsor AND " :
                    " userdetails.contact = userdetails.sponsor AND "
                ) : ""
            ) .
            "((hour(timediff(now(), created_at)) * 60) + minute(timediff(now(), created_at))) < ?" .
            " AND " .
            "userdetails.contact LIKE " . $contactCondition;
        error_log("SURVEY - Contact details SQL [" . $sql . "]");
        $handle = $this->db->getConnection()->prepare($sql);
        $handle->bindValue(1, $surveySettingId);
        $handle->bindValue(2, $timeDelayMinutes);
        $handle->execute();
        return $handle->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Send the survey emails to the contacts provided.
     * @param $contactDetails array of contacts - email addresses in an associative array, key: 'contact'/
     * @param $surveyConfig array The survey configurations array.
     */
    public function sendEmailToContacts($contactDetails, $surveyConfig) {
        foreach ($contactDetails as $contactDetail) {
            $emailResponse = new EmailResponse();
            $emailResponse->sendSurvey($contactDetail['contact'], $surveyConfig);
            $this->logSurvey($surveyConfig['survey_setting_id'], $contactDetail['username']);
        }
    }

    /**
     * Send the survey text messages to the contacts provided.
     * @param $contactDetails
     * @param $surveyConfig
     */
    public function sendSmsToContacts($contactDetails, $surveyConfig) {
        foreach ($contactDetails as $contactDetail) {
            $smsResponse = new SmsResponse($contactDetail['contact']);
            $smsResponse->sendSurvey($surveyConfig);
            $this->logSurvey($surveyConfig['survey_setting_id'], $contactDetail['username']);
        }
    }

    /**
     * Save username and survey setting ID to the logs table.
     * @param $surveySettingId
     * @param $userName
     */
    public function logSurvey($surveySettingId, $userName) {
        $sql = "INSERT INTO survey_logs(survey_setting_id, username) VALUES (:survey_setting_id, :username)";
        $handle = $this->db->getConnection()->prepare($sql);
        $handle->bindValue(':survey_setting_id', $surveySettingId, PDO::PARAM_INT);
        $handle->bindValue(':username', $userName, PDO::PARAM_STR);
        $handle->execute();
    }
}
