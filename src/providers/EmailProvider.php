<?php
namespace Alphagov\GovWifi;

/**
 * Base interface for email providers.
 *
 * @package Alphagov\GovWifi
 */
interface EmailProvider {
    const EMAIL_REGEX = "/<([^>]+)>/";

    /**
     * Return the provider identifier for logging purposes.
     * @return string
     */
    public function getProviderId();

    /**
     * Pre-process the request received.
     * @return bool Whether or not further processing is required.
     */
    public function preProcess();

    /**
     * Extract the "from" email address.
     * @return string
     */
    public function getEmailFrom();

    /**
     * Extract the name of the sender.
     * @return string
     */
    public function getSenderName();

    /**
     * Extract the "to" email address.
     * @return string
     */
    public function getEmailTo();

    /**
     * Extract the subject of the email.
     * @return string
     */
    public function getEmailSubject();

    /**
     * Extract the body of the email.
     * @return string
     */
    public function getEmailBody();
}
