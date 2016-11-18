<?php
namespace Alphagov\GovWifi;

use Exception;
use Memcached;
use PDOException;

/**
 * Class Cache - A wrapper around (for now) the standard Memcached class.
 * Singleton. Exceptions thrown from the underlying class are silently logged.
 *
 * @package Alphagov\GovWifi
 */
class Cache {
    private static $instance; //The single instance
    public $memcached;
    public $hostname;

    /**
     * @return Cache The single instance.
     */
    public static function getInstance() {
        if (!self::$instance) { // If no instance then make one
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Store a value in the cache.
     *
     * @param $key string The key to use for identifying the value.
     * @param $value mixed The value to be stored.
     * @return bool Indicates success.
     */
    public function set($key, $value) {
        try {
            return $this->memcached->set($key, $value);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Wrapper for retrieving a value from the cache.
     *
     * @param $key String The key to look for in the cache.
     * @return bool|mixed The value from the cache, false if not found,
     * or there was an exception.
     */
    public function get($key) {
        try {
            return $this->memcached->get($key);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Remove an item from the cache, identified by the key provided.
     *
     * @param $key string the key to be deleted.
     * @return bool Indicates success.
     */
    public function delete($key) {
        try {
            return $this->memcached->delete($key);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
    /**
     * Check the result of the last get() operation.
     *
     * @return bool TRUE if the key was not found in the cache,
     * false otherwise.
     */
    public function itemWasNotFound() {
        return Memcached::RES_NOTFOUND == $this->memcached->getResultCode();
    }

    /**
     * Cache private constructor.
     */
    private function __construct() {
        $this->hostname = trim(getenv("CACHE_HOSTNAME"));

        try {
            $this->memcached = new Memcached();
            $this->memcached->addServer($this->hostname, 11211);
        } catch (PDOException $e) {
            //Ignoring cache exceptions - normal DB functions will take over.
            error_log($e->getMessage());
        }
    }
}
