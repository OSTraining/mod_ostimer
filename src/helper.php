<?php
/**
 * @package   OSTimer
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2017-2019 Joomlashack.com. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of OSTimer.
 *
 * OSTimer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSTimer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSTimer.  If not, see <http://www.gnu.org/licenses/>.
 */

use Alledia\OSTimer\DateTime;

defined('_JEXEC') or die();

abstract class ModOstimerHelper
{
    /**
     * @var string[]
     */
    protected static $log = array();

    /**
     * @return string
     * @throws Exception
     */
    public static function getAjax()
    {
        require_once __DIR__ . '/library/DateTime.php';

        $app = JFactory::getApplication();

        $event         = $app->input->getString('time');
        $now           = $app->input->getString('date');
        $tzid          = $app->input->getString('tzid');
        $offsetMinutes = $app->input->getInt('offset', 0);
        $offsetSeconds = 0 - ($offsetMinutes * 60); // Javascript reports offset in inverse minutes

        $eventTime    = new DateTime($event);

        static::logEntry('Event', $eventTime->format('c (e)'));
        static::logEntry('JS Now', $now);
        static::logEntry('JS Offset', $offsetMinutes);

        $userTimezone = static::createTimezone($offsetSeconds, $tzid);

        if ($userTimezone instanceof DateTimeZone) {
            $eventTime->setTimezone($userTimezone);
        }
        static::logEntry('Event', $eventTime->format('c (e)'));

        $format   = $app->input->getString('display');
        $tzFormat = $app->input->getString('tz');

        $dateString = $eventTime->localeFormat($format);
        if ($tzFormat) {
            $dateString .= ' ' . str_replace('_', ' ', $eventTime->format($tzFormat));
        }

        return static::renderLog() . $dateString;
    }

    /**
     * Create a DateTimeZone object using either a timezone id
     * (e.g. America/New_York) or GMT offset. Note that we are
     * ignoring Timezone names and abbreviations (e.g. Eastern Standard Time
     * or EST) because the data tables have been found to be unreliable.
     *
     * @param int    $offset   GMT offset seconds (west < 0, east > 0)
     * @param string $tzString Recognized Intl Timezone ID
     *
     * @return DateTimeZone
     */
    protected static function createTimezone($offset, $tzString = null)
    {
        try {
            $now = new DateTime();

            if ($tzString) {
                // Attempt to create by Intl Timezone ID
                try {
                    if (in_array($tzString, timezone_identifiers_list())) {
                        $timezone = new DateTimeZone($tzString);
                        if ($timezone->getOffset($now) == $offset) {
                            static::logEntry('TZID', $tzString);
                            return $timezone;
                        }
                    }

                } catch (Exception $error) {
                    // ignore it here
                }
            }

            // Look for the first timezone that will give us the specified GMT offset
            $tzList = timezone_abbreviations_list();
            foreach ($tzList as $key => $codes) {
                foreach ($codes as $tzData) {
                    $tzId     = $tzData['timezone_id'];
                    $tzOffset = $tzData['offset'];

                    if ($tzOffset == $offset) {
                        try {
                            $timezone = new DateTimeZone($tzId);
                            if ($timezone->getOffset($now) == $offset) {
                                static::logEntry('offset', $offset);
                                static::logEntry('found', $tzId);

                                return $timezone;
                            }

                        } catch (Exception $error) {
                            // ignore this and carry on
                        }
                    }

                }
            }

        } catch (Exception $error) {
            // Just bail!
        }

        return null;
    }

    /**
     * @return string
     */
    protected static function renderLog()
    {
        try {
            if (JFactory::getApplication()->input->getInt('debug') && static::$log) {
                static::logEntry('Server', date('c (e)'));
                static::logEntry('UTC', gmdate('c (e)'));

                return sprintf(
                    '<div class="alert-error" style="text-align: left;"><ul><li>%s</li></ul></div>',
                    join('</li><li>', static::$log)
                );
            }

        } catch (Exception $error) {
            // ignore
        }

        return '';
    }

    /**
     * @param string $label
     * @param string $text
     */
    protected static function logEntry($label, $text = null)
    {
        if ($text) {
            static::$log[] = sprintf('%s: %s', $label, $text);

        } else {
            static::$log[] = sprintf('<i class="icon-info"></i>%s', $label);
        }
    }
}
