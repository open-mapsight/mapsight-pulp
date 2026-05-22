<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic\Tic3;

use DateTime;
use DateTimeZone;
use OpenMapsight\pulptic\CommonUtils as CommonUtils;
use SimpleXMLElement;

class Utils
{
    /**
     * @param $timezoneEnum
     *
     * @return DateTimeZone|null
     */
    private static function timezoneEnumToTimezone(string $timezoneEnum): ?DateTimeZone
    {
        // TODO: Support more timezones
        return match ($timezoneEnum) {
            '2', '3' => new DateTimeZone('GMT'),
            '4' => new DateTimeZone('CEST'),
            default => null,
        };
    }

    /**
     * @param               $str
     * @param DateTimeZone $timezone
     *
     * @return DateTime
     */
    public static function parseTime($str, DateTimeZone $timezone): DateTime
    {
        return new DateTime($str, $timezone);
    }

    /**
     * @param SimpleXMLElement $element
     *
     * @return string
     */
    private static function parseShapeOrPositionType(SimpleXMLElement $element): ?string
    {
        if (($val = reset($element->xpath('./Type'))) !== false) {
            switch ($val) {
                case '0':
                case 'Area':
                    return 'area';
                case '1':
                case 'Line':
                    return 'line';
                case '2':
                case 'Point':
                    return 'point';
            }
        }

        return null;
    }

    /**
     * @param SimpleXMLElement $element
     *
     * @return array
     */
    private static function parseCoordinates(SimpleXMLElement $element): array
    {
        $positions = [];
        foreach ($element->xpath('./Coordinate') as $coordinate) {
            if (($x = reset($coordinate->xpath('./Longitude'))) !== false &&
                ($y = reset($coordinate->xpath('./Latitude'))) !== false) {
                $positions[] = ['x' => (float) $x, 'y' => (float) $y];
            }
        }

        return $positions;
    }

    /**
     * @param SimpleXMLElement $locationPosition
     *
     * @return array
     */
    public static function parseLocationShapeOrPosition(SimpleXMLElement $locationPosition): array
    {
        return [
            'pos' => self::parseCoordinates($locationPosition),
            'type' => self::parseShapeOrPositionType($locationPosition),
        ];
    }

    /**
     * @param SimpleXMLElement $tic3Location
     *
     * @return array
     */
    public static function parseTic3Location(SimpleXMLElement $tic3Location): array
    {
        return array_map(self::parseLocationShapeOrPosition(...), $tic3Location->xpath('./Shape'));
    }

    /**
     * We try to extract more info from description. We assume the description value is made up
     * from multiple sources concatenated with line breaks. Part 0 is the road identifier and segment or direction,
     * part 1 is the narrower localization on the road and the rest is the actual description.
     *
     * @param $val
     * @param $res
     */
    private static function parseDescription($val, array &$res): void
    {
        $description = explode("\n", trim((string) $val), 2);

        // if the description is actually multi-part extract the road segment from part 0
        if (count($description) > 1) {
            $res['segment'] = trim($description[0]);

            // remove the road number from the start of part 0
            if (isset($res['roadNumber'])) {
                $res['segment'] = trim((string) preg_replace('/^' . $res['roadNumber'] . '/', '', trim($res['segment'])), ",; \t\n\r\0\x0B");
            }

            // take the rest as description
            $res['description'] = trim($description[1]);
        } else {
            // we do not have any clue about the description so we cant extract the segment
            // we take it as-is
            $res['description'] = $description[0];
        }
    }

    /**
     * @param SimpleXMLElement $entry
     *
     * @return array
     */
    private static function parseLocations(SimpleXMLElement $entry): array
    {
        $locations = [];
        foreach (array_map(self::parseTic3Location(...), $entry->xpath('./Location/Tic3Location')) as $locations) {
            $locations = array_merge($locations, $locations);
        }

        foreach (array_map(self::parseTic3Location(...), $entry->xpath('./OppositeDirectionLocation/Tic3Location')) as $locations) {
            $locations = array_merge($locations, $locations);
        }

        if (($val = reset($entry->xpath('./Location/Position'))) !== false) {
            $locations[] = self::parseLocationShapeOrPosition($val);
        }

        return $locations;
    }

    /**
     * @param SimpleXMLElement $entry
     *
     * @return array
     */
    private static function parseTimes(SimpleXMLElement $entry): array
    {
        $res = [];

        $timezone = null;
        if (($val = reset($entry->xpath('./TimeZone'))) !== false) {
            $timezone = Utils::timezoneEnumToTimezone((string) $val);
        }

        if (!$timezone instanceof DateTimeZone) {
            $timezone = new DateTimeZone('UTC');
        }

        if (($val = reset($entry->xpath('./CreateTime'))) !== false) {
            $res['activateTime'] = Utils::parseTime((string) $val, $timezone);
        }

        if (($val = reset($entry->xpath('./Duration/StartTime'))) !== false) {
            $res['startTime'] = Utils::parseTime((string) $val, $timezone);
        }

        if (($val = reset($entry->xpath('./Duration/EndTime'))) !== false) {
            $res['stopTime'] = Utils::parseTime((string) $val, $timezone);
        }

        return $res;
    }

    /**
     * type of events
     *
     * @param SimpleXMLElement $eventData
     * @param                   $res
     */
    private static function parseTmcEvent(SimpleXMLElement $eventData, array &$res): void
    {
        if (($val = reset($eventData->xpath('./UpdateClass'))) !== false) {
            $val = (int) $val;

            if (!isset($res['eventTypes'])) {
                $res['eventTypes'] = [];
            }

            $res['eventTypes'][] = [
                'code' => $val,
                'type' => CommonUtils::eventTypeCategoryCodeToString($val),
            ];
        }
    }

    /**
     * @return mixed[]
     */
    public static function parseEntry(SimpleXMLElement $entry): array
    {
        $res = [];

        if (($val = reset($entry->xpath('./DataProducer'))) !== false) {
            $res['originName'] = trim((string) $val->attributes()['value']);
            $res['organisation'] = trim((string) $val->attributes()['value']);
        }

        if (($val = reset($entry->xpath('./TicId'))) !== false) {
            $res['guid'] = trim((string) $val);
        }

        $res = array_merge($res, self::parseTimes($entry));

        foreach ($entry->xpath('./Event/TmcEvent') as $eventData) {
            self::parseTmcEvent($eventData, $res);
        }

        if (($val = reset($entry->xpath('./Location'))) !== false) {
            $res['name'] = trim((string) $val->attributes()['description']);
        }

        $res['isAbolished'] = reset($entry->xpath('./CancelationTime')) !== false;

        if (($val = reset($entry->xpath('./Location/Tic3Location/Edge'))) !== false) {
            $res['locationName'] = trim((string) $val->attributes()['description']);
        }

        if (($val = reset($entry->xpath('./Location/Tic3Location/Edge/SortRoadNumber'))) !== false) {
            $val = trim((string) $val);

            if ($val !== '' && $val !== '0') {
                $res['roadNumber'] = $val;
            }
        }

        if (!empty($entry->attributes()['description'])) {
            self::parseDescription($entry->attributes()['description'], $res);
        }

        $res['locations'] = self::parseLocations($entry);

        return $res;
    }

    /**
     * @return mixed[]
     */
    public static function parseTemplateEntry(SimpleXMLElement $entry): array
    {
        $res = [];

        if (($val = reset($entry->xpath('./TicId'))) !== false) {
            $res['guid'] = trim((string) $val);
        }

        if (($val = reset($entry->xpath('./Location'))) !== false) {
            $res['name'] = trim((string) $val->attributes()['description']);
        }

        if (($val = reset($entry->xpath('./Location/Tic3Location/Edge'))) !== false) {
            $res['locationName'] = trim((string) $val->attributes()['description']);
        }

        if (($val = reset($entry->xpath('./Location/Tic3Location/Edge/SortRoadNumber'))) !== false) {
            $val = trim((string) $val);

            if ($val !== '' && $val !== '0') {
                $res['roadNumber'] = $val;
            }
        }

        if (($val = reset($entry->xpath('./Description'))) !== false) {
            self::parseDescription($val, $res);
        }

        $res['locations'] = self::parseLocations($entry);

        return $res;
    }
}
