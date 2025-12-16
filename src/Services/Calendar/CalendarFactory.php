<?php
/**
 * Calendar Factory
 * Creates the appropriate calendar strategy based on configuration.
 * Implements the Factory Pattern for calendar services.
 * 
 * @package WhiteLabel\Services\Calendar
 */

declare(strict_types=1);

namespace Services\Calendar;

require_once __DIR__ . '/LocalCalendar.php';
require_once __DIR__ . '/OutlookCalendar.php';

use Contracts\CalendarInterface;

class CalendarFactory
{
    /**
     * Available calendar drivers
     */
    private const DRIVERS = [
        'local' => LocalCalendar::class,
        'outlook' => OutlookCalendar::class,
    ];

    /**
     * Create a calendar instance based on configuration
     * 
     * @return CalendarInterface
     */
    public static function create(): CalendarInterface
    {
        // Try to get from DB first
        try {
            if (!class_exists('IntegrationService')) {
                $servicePath = __DIR__ . '/../../IntegrationService.php'; // Adjust path based on structure
                if (file_exists($servicePath)) {
                    require_once $servicePath;
                } else {
                     // Fallback if file not found (e.g. simple install)
                     require_once __DIR__ . '/../../Services/IntegrationService.php';
                }
            }
            
            $service = new \IntegrationService();
            $config = $service->getIntegrationConfig();
            $driver = $config['provider'] ?? 'local';
            
            // Map 'microsoft_personal'/'microsoft_graph' to 'outlook' for now, 
            // until we have specific drivers or the driver handles both logic internally
            if (str_starts_with($driver, 'microsoft')) {
                $driver = 'outlook';
            }
        } catch (\Exception $e) {
            // Fallback to config constant
             $driver = defined('CALENDAR_DRIVER') ? strtolower(CALENDAR_DRIVER) : 'local';
        }

        return match ($driver) {
            'outlook' => new OutlookCalendar(),
            'google' => new GoogleCalendar(), // Need to implement this if it doesn't exist
            'local'   => new LocalCalendar(),
            default   => new LocalCalendar()
        };
    }

    /**
     * Create a specific calendar driver
     * 
     * @param string $driver Driver name ('local' or 'outlook')
     * @return CalendarInterface
     * @throws \InvalidArgumentException If driver is invalid
     */
    public static function driver(string $driver): CalendarInterface
    {
        $driver = strtolower($driver);

        if (!isset(self::DRIVERS[$driver])) {
            throw new \InvalidArgumentException(
                "Invalid calendar driver: {$driver}. Available: " . implode(', ', array_keys(self::DRIVERS))
            );
        }

        $class = self::DRIVERS[$driver];
        return new $class();
    }

    /**
     * Get current driver name
     * 
     * @return string
     */
    public static function currentDriver(): string
    {
        return defined('CALENDAR_DRIVER') ? strtolower(CALENDAR_DRIVER) : 'local';
    }

    /**
     * Check if using local calendar
     * 
     * @return bool
     */
    public static function isLocal(): bool
    {
        return self::currentDriver() === 'local';
    }

    /**
     * Check if using Outlook calendar
     * 
     * @return bool
     */
    public static function isOutlook(): bool
    {
        return self::currentDriver() === 'outlook';
    }
}
