<?php
/**
 * Phinx Feature Flags
 *
 * This file is missing in hoping/thinkphp distribution.
 * Created based on Phinx 0.13.x implementation.
 */

namespace Phinx\Config;

/**
 * Feature flags for Phinx
 *
 * @package Phinx\Config
 */
class FeatureFlags
{
    /**
     * Whether to allow NULL as default value for columns.
     * Default: true (compatible with older behavior).
     *
     * @var bool
     */
    public static $columnNullDefault = true;

    /**
     * Whether to use unsigned primary keys.
     * Default: false (compatible with older behavior).
     *
     * @var bool
     */
    public static $unsignedPrimaryKeys = false;

    /**
     * Set feature flags from config array.
     *
     * @param array $flags The feature flags
     * @return void
     */
    public static function setFlagsFromConfig(array $flags): void
    {
        if (isset($flags['columnNullDefault'])) {
            self::$columnNullDefault = (bool) $flags['columnNullDefault'];
        }

        if (isset($flags['unsignedPrimaryKeys'])) {
            self::$unsignedPrimaryKeys = (bool) $flags['unsignedPrimaryKeys'];
        }
    }
}
