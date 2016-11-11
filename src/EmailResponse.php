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

    public function sponsor($count) {
        $config = Config::getInstance();
        $this->subject = $config->values['email-messages']['sponsor-subject'];
        if ($count>0) {
            $this->message = file_get_contents(
                    $config->values['email-messages']['sponsor-file']);
            $this->message = str_replace("%X%", $count, $this->message);
        } else {
            $this->message = file_get_contents(
                    $config->values['email-messages']['sponsor-help-file']);
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

    public function signUp($user) {
        $config = Config::getInstance();
        $this->subject =
                $config->values['email-messages']['enrollment-subject'];
        $this->message = file_get_contents(
                $config->values['email-messages']['enrollment-file']);
        $this->message = str_replace("%LOGIN%", $user->login, $this->message);
        $this->message = str_replace("%PASS%", $user->password, $this->message);
        $this->message = str_replace(
                "%SPONSOR%", $user->sponsor->text, $this->message);
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

    public function send() {
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
        $email->setTo($this->to);
        $email->setFrom($this->from);
        $email->setSubject($this->subject);
        $email->setBody($this->message);
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
            error_log("Email sent! Message ID: " . $messageId);
        } catch (Exception $e) {
            error_log(
                "The email was not sent. Error message: " . $e->getMessage());
        }
    }
}
