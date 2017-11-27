<?php
namespace Alphagov\GovWifi;
use PDO;

/**
 * Class BulkRegistration
 *
 * Handles bulk registration of users.
 *
 * @package Alphagov\GovWifi
 */
class BulkRegistration {
    /**
     * @var Config
     */
    private $config;

    /**
     * @var DB
     */
    private $db;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * BulkRegistration constructor.
     *
     * @param $config Config
     * @param $db DB
     */
    public function __construct($config, $db, $cache) {
        $this->config   = $config;
        $this->db       = $db;
        $this->cache    = $cache;
    }

    public function sendBulkEmails($orgId) {
        $bulkConfig = $this->getBulkConfig($orgId);
        $limit = '';
        if (!empty($bulkConfig['batch_size']) && intval($bulkConfig['batch_size']) > 0) {
            $limit = ' LIMIT ' . intval($bulkConfig['batch_size']);
        }

        $handle = $this->db->getConnection()->prepare(
            "SELECT contact_email FROM bulk_registration_emails WHERE " .
            "bulk_registration_id = :bulkRegId AND NOT email_sent" . $limit
        );

        $handle->bindValue(':bulkRegId', $bulkConfig['id']);
        $handle->execute();
        ;

        while ($result = $handle->fetch(PDO::FETCH_ASSOC)) {
            if ($this->sendEmail($bulkConfig, $result['contact_email'])) {
                $update = $this->db->getConnection()->prepare(
                    'UPDATE bulk_registration_emails SET email_sent = 1, email_sent_at = now() WHERE contact_email = :contactEmail;');
                $update->bindValue(':contactEmail', $result['contact_email'], PDO::PARAM_STR);
                $update->execute();
            } else {
                $update = $this->db->getConnection()->prepare(
                    'UPDATE bulk_registration_emails SET failed = 1, last_failed_at = now() WHERE contact_email = :contactEmail;');
                $update->bindValue(':contactEmail', $result['contact_email'], PDO::PARAM_STR);
                $update->execute();
            }
        }
    }

    private function getBulkConfig($orgId) {
        $handle = $this->db->getConnection()->prepare(
            "SELECT id, sponsor_name, sponsor_email, batch_size FROM bulk_registrations WHERE org_id = :orgId");
        $handle->bindValue(':orgId', $orgId, PDO::PARAM_INT);
        $handle->execute();
        return $handle->fetch(PDO::FETCH_ASSOC);
    }

    private function sendEmail($bulkConfig, $contactEmail) {
        $identifier = new Identifier($contactEmail);
        if (!$identifier->validEmail) {
            return false;
        }
        $user = new User($this->cache, $this->config);
        $user->identifier = $identifier;
        $user->sponsor = new Identifier($bulkConfig['sponsor_email']);
        $user->signUp("", false, false, $bulkConfig['sponsor_name']);
        return true;
    }
}