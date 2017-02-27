<?php

namespace Alphagov\GovWifi;

/**
 * Base class to house common functionality across the project.
 *
 * @package Alphagov\GovWifi
 */
class GovWifiBase {
    /**
     * Checks if the mandatory list of keys is present and not empty in the params array.
     *
     * @param array $keyList list of keys to check for
     * @param array $params The array of params to check
     * @throws GovWifiException if one of the keys is empty in the params array.
     */
    public static function checkNotEmpty($keyList, $params) {
        foreach ($keyList as $key) {
            if (empty($params[$key])) {
                throw new GovWifiException("The field " . $key . " is required in the params array.");
            }
        }
    }

    /**
     * Checks if the standard params are of the appropriate class. Config, DB and Cache are considered
     * standard across the project.
     *
     * @param array $params The array maybe containing the standard params.
     */
    public static function checkStandardParams($params) {
        if (!empty($params['config']) && !is_a($params['config'], Config::class)) {
            throw new GovWifiException("Config class not recognised.");
        }
        if (!empty($params['db']) && !is_a($params['db'], DB::class)) {
            throw new GovWifiException("DB class not recognised.");
        }
        if (!empty($params['cache']) && !is_a($params['cache'], Cache::class)) {
            throw new GovWifiException("Cache class not recognised.");
        }
    }
}
