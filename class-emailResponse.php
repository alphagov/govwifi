<?php

class emailResponse {
    public $from;
    public $to;
    public $subject;
    public $message;
    public $filename;
    public $filepath;

    public function __construct() {
        $config = config::getInstance();
        $this->from = $config->values['email-noreply'];

    }

    public function sponsor($count) {
        $config = config::getInstance();
        $this->subject = $config->values['email-messages']['sponsor-subject'];
        if ($count>0) {
            $this->message = file_get_contents(
                    $config->values['email-messages']['sponsor-file']);
            $this->message = str_replace("%X%", $count, $this->message);
        } else {
            $this->message = file_get_contents($config->values['email-messages']['sponsor-help-file']);
        }
    }

    public function newsite($action,$outcome,$site) {
        $config = config::getInstance();
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

    public function enroll($user) {
        $config = config::getInstance();
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

    public function logrequest() {
        $config = config::getInstance();
        $this->subject =
                $config->values['email-messages']['logrequest-subject'];
        $this->message = file_get_contents(
                $config->values['email-messages']['logrequest-file']);
    }

    public function send() {
        $config = config::getInstance();
        // TODO(afoldesi-gds): Test (debug) attachments with this version.
	$client = Aws\Ses\SesClient::factory(array(
            'version' => 'latest',
            'region' => 'eu-west-1',
            'key'    => $config->values['AWS']['Access-keyID'],
            'secret' => $config->values['AWS']['Access-key']
        ));
        $request = array();
        $request['Source'] = $this->from;
        $request['Destination']['ToAddresses'] = array($this->to);
        $request['Message']['Subject']['Data'] = $this->subject;
        $request['Message']['Body']['Text']['Data'] = $this->message;


        try {
            $result = $client->sendEmail($request);
            $messageId = $result->get('MessageId');
            error_log("Email sent! Message ID: $messageId"."\n");
        } catch (Exception $e) {
            error_log(
                "The email was not sent. Error message: ".$e->getMessage());
        }
    }

    function tryEmailProvider($provider) {
        $config = config::getInstance();
        $conf_index = 'email-provider' . $provider;
        $success = false;
        if ($config->values[$conf_index]['enabled']) {
            switch ($config->values[$conf_index]['provider']) {
                case "postmark":
                    $success = $this->tryPostMark($provider);
                    break;
                case "mailgun":
                    $success = $this->tryMailGun($provider);
                    break;
            }
        }
        return $success;
    }
}

?>
