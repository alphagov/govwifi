<?php
namespace Alphagov\GovWifi;

use Aws\Ses\SesClient;
use Exception;
use Swift_Attachment;
use Swift_Message;

class EmailResponse {
    public $from;
    public $to;
    public $subject;
    public $message;
    public $fileName;
    public $filepath;

    public function __construct() {
        $config = Config::getInstance();
        $this->from = $config->values['email-noreply'];
        $this->subject = "";
        $this->message = "";
    }

    public function sponsor($count, $uniqueContactList) {
        $config = Config::getInstance();
        if ($count > 0) {
            $this->message = file_get_contents($config->values['email-messages']['sponsor-file']);
            $this->subject = $config->values['email-messages']['sponsor-subject'];
            if ($count > 1) {
                $this->subject = $config->values['email-messages']['sponsor-subject-plural'];
                $this->message = file_get_contents($config->values['email-messages']['sponsor-plural-file']);
            }
            // TODO: Remove when the count placeholder gets fully deprecated.
            $this->message = str_replace("%X%", $count, $this->message);
            $this->message = str_replace("%CONTACTS%", implode("\n", $uniqueContactList), $this->message);
        } else {
            $this->subject = $config->values['email-messages']['sponsor-subject-help'];
            $this->message = file_get_contents($config->values['email-messages']['sponsor-help-file']);
        }
    }

    public function newSite($action, $outcome, Site $site) {
        $config = Config::getInstance();
        $this->from = $config->values['email-newsitereply'];
        $this->subject = $site->name;
        $this->message = file_get_contents(
                $config->values['email-messages']['newsite-file']);
        $this->message = str_replace("%OUTCOME%", $outcome, $this->message);
        $this->message = str_replace("%ACTION%", $action, $this->message);
        $this->message = str_replace("%NAME%", $site->name, $this->message);
        $this->message = str_replace(
                "%ATTRIBUTES%", $site->attributesText(), $this->message);
    }

    public function newSiteBlank($site) {
        $config = Config::getInstance();
        $this->subject = $site->name;
        $this->message = file_get_contents(
            $config->values['email-messages']['newsite-help-file']);
    }

    public function signUp($user, $selfSignup, $senderName) {
        $config = Config::getInstance();
        $this->subject =
                $config->values['email-messages']['enrollment-subject'];
        $this->message = file_get_contents(
            $config->values['email-messages']['enrollment-file']);
        if ($selfSignup) {
            $this->message = file_get_contents(
                $config->values['email-messages']['enrollment-file-self-signup']);
        }
        $this->message = str_replace("%LOGIN%", $user->login, $this->message);
        $this->message = str_replace("%PASS%", $user->password, $this->message);
        $sponsor = $user->sponsor->text;
        if (!empty($senderName)) {
            $sponsor = $senderName . " (" . $sponsor . ")";
        }
        $this->message = str_replace(
                "%SPONSOR%", $sponsor, $this->message);
        $this->message = str_replace(
                "%THUMBPRINT%",
                $config->values['radcert-thumbprint'],
                $this->message);
        $this->send();
    }

    public function logRequest() {
        $config = Config::getInstance();
        $this->subject =
                $config->values['email-messages']['logrequest-subject'];
        $this->message = file_get_contents(
                $config->values['email-messages']['logrequest-file']);
        $this->message = str_replace(
            "%FILENAME%", $this->fileName, $this->message);
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

        $htmlTemplate = file_get_contents(
            $config->values['email-messages']['header-footer']);
        $email->addPart(
            str_replace("##CONTENT##", $this->message, $htmlTemplate),
            'text/html');

        if (!empty($this->filepath)) {
           $email->attach(Swift_Attachment::fromPath($this->filepath));
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
}
