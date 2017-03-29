<?php
namespace Alphagov\GovWifi;

use Aws\Ses\SesClient;
use Exception;
use Swift_Attachment;
use Swift_Message;

class EmailResponse {
    const STANDARD_PARAGRAPH   = '<p style=3D"Margin: 0 0 20px 0; font-size: 19px; line-height: 25px; color:#0B0C0C;">';
    const HTML_TEMPLATE_PREFIX = 'html' . DIRECTORY_SEPARATOR;
    public $from;
    public $to;
    public $subject;
    public $message;
    /**
     * @var string The HTML-formatted message template.
     */
    public $htmlMessage;
    public $fileName;
    public $filePath;

    public function __construct() {
        $config            = Config::getInstance();
        $this->from        = $config->values['email-noreply'];
        $this->subject     = "";
        $this->message     = "";
        $this->htmlMessage = "";
    }

    public function sponsor($count, $uniqueContactList) {
        $config = Config::getInstance();
        if ($count > 0) {
            $this->setMessages($config->values['email-messages']['sponsor-file']);
            $this->subject = $config->values['email-messages']['sponsor-subject'];
            if ($count > 1) {
                $this->subject = $config->values['email-messages']['sponsor-subject-plural'];
                $this->setMessages($config->values['email-messages']['sponsor-plural-file']);
            }
            $this->replaceInMessages("%X%", $count);
            $this->message = str_replace("%CONTACTS%", implode("\n", $uniqueContactList), $this->message);
            $this->htmlMessage = str_replace("%CONTACTS%", implode("<br/>", $uniqueContactList), $this->htmlMessage);
        } else {
            $this->subject = $config->values['email-messages']['sponsor-subject-help'];
            $this->setMessages($config->values['email-messages']['sponsor-help-file']);
        }
    }

    public function newSite($action, $outcome, Site $site) {
        $config = Config::getInstance();
        $this->from = $config->values['email-newsitereply'];
        $this->subject = $site->name;
        $this->setMessages($config->values['email-messages']['newsite-file']);
        $this->replaceInMessages("%OUTCOME%", $outcome);
        $this->replaceInMessages("%ACTION%", $action);
        $this->replaceInMessages("%NAME%", $site->name);
        $this->replaceInMessages("%ATTRIBUTES%", $site->attributesText());
    }

    public function newSiteBlank($site) {
        $config = Config::getInstance();
        $this->subject = $site->name;
        $this->setMessages($config->values['email-messages']['newsite-help-file']);
    }

    public function signUp($user, $selfSignup, $senderName) {
        $config = Config::getInstance();
        $this->subject =
                $config->values['email-messages']['enrollment-subject'];
        $this->setMessages($config->values['email-messages']['enrollment-file']);
        if ($selfSignup) {
            $this->setMessages($config->values['email-messages']['enrollment-file-self-signup']);
        }
        $this->replaceInMessages("%LOGIN%", $user->login);
        $this->replaceInMessages("%PASS%", $user->password);

        $sponsor = $user->sponsor->text;
        if (!empty($senderName)) {
            $sponsor = $senderName . " (" . $sponsor . ")";
        }
        $this->replaceInMessages("%SPONSOR%", $sponsor);
        $this->replaceInMessages("%THUMBPRINT%", $config->values['radcert-thumbprint']);
        $this->send();
    }

    public function logRequest() {
        $config = Config::getInstance();
        $this->subject =
                $config->values['email-messages']['logrequest-subject'];
        $this->message = file_get_contents(
                $config->values['email-messages']['logrequest-file']);
        $this->replaceInMessages("%FILENAME%", $this->fileName);
    }

    public function sendSurvey($contact, $surveyConfig) {
        $this->to = $contact;
        $this->subject = $surveyConfig['email_subject'];
        $this->setMessages($surveyConfig['email_template']);
        $this->replaceInMessages("%SURVEY%", $surveyConfig['survey_url']);
        $this->send();
    }

    public function send($emailManagerAddress = NULL) {
        $config = Config::getInstance();
        // TODO(afoldesi-gds): (Low)Refactor out deprecated factory method.
	    $client = SesClient::factory(array(
            'version' => 'latest',
            'region' => 'eu-west-1',
            'credentials' => [
                'key'    => $config->values['AWS']['Access-keyID'],
                'secret' => $config->values['AWS']['Access-key']
            ]
        ));

        $email = Swift_Message::newInstance();
        $recipient = $this->to;
        $subject   = $this->subject;
        if (!empty($emailManagerAddress)) {
            $recipient = $emailManagerAddress;
            $subject   = $this->to;
        }

        $email->setTo($recipient);
        $email->setSubject($subject);
        $email->setFrom($this->from, Config::SERVICE_NAME);
        $email->setBody($this->message);
        if ($config->values['email-messages']['use-html'] && !empty($this->htmlMessage)) {
            $htmlTemplate = file_get_contents(
                $config->values['email-messages']['header-footer']);
            $this->htmlMessage = str_replace("<p>", self::STANDARD_PARAGRAPH, $this->htmlMessage);
            $email->addPart(
                str_replace("##CONTENT##", $this->htmlMessage, $htmlTemplate),
                'text/html');
        }

        if (!empty($this->filePath)) {
           $email->attach(Swift_Attachment::fromPath($this->filePath));
        }

        try {
            $result = $client->sendRawEmail(array(
                "RawMessage" => array(
                    "Data" => $email->toString()
                )
            ));
            $messageId = $result->get('MessageId');
            error_log("Email sent! To: " . $recipient . ", Subject: " . $subject . ", Message ID: " . $messageId);
        } catch (Exception $e) {
            error_log(
                "The email was not sent. Error message: " . $e->getMessage());
        }
    }

    /**
     * Sets the internal message templates based on the file path provided.
     *
     * The file path must exist and point to the plain text version of the email. If there is an HTML template
     * corresponding with the file path (same file name in the html directory) it will also be loaded.
     *
     * @param $templateFilePath
     */
    public function setMessages($templateFilePath) {
        $this->message = file_get_contents($templateFilePath);
        $htmlTemplatePath =
            dirname($templateFilePath) .
            DIRECTORY_SEPARATOR .
            self::HTML_TEMPLATE_PREFIX .
            basename($templateFilePath);

        if (file_exists($htmlTemplatePath)) {
            $this->htmlMessage = file_get_contents($htmlTemplatePath);
        }
    }

    /**
     * Perform a search and replace in both the plain text and html message templates.
     *
     * @param $search
     * @param $replace
     */
    public function replaceInMessages($search, $replace) {
        $this->message = str_replace($search, $replace, $this->message);
        $this->htmlMessage = str_replace($search, $replace, $this->htmlMessage);
    }
}
