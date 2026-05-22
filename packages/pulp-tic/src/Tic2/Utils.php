<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic\Tic2;

use DateTime;
use DateTimeZone;
use OpenMapsight\pulptic\CommonUtils as CommonUtils;
use SimpleXMLElement;

class Utils
{
    private static array $stringByLocationTypeCodeAndSubCode = [
        // area
        0 => [100 => 'Continent', 200 => 'Country Group', 300 => 'Country',
            500 => 'Water Area', 501 => 'Sea', 502 => 'Lake',
            600 => 'Fuzzy Area', 601 => 'Tourist Area',
            602 => 'Metropolitan Area', 603 => 'Industrial Area',
            604 => 'Traffic Area', 605 => 'Meteorological area',
            606 => 'Carpool area', 607 => 'Park and ride site',
            608 => 'Car park area', 700 => 'Order 1 Area', 800 => 'Order 2 Area',
            900 => 'Order 3 Area', 901 => 'Rural county', 902 => 'Urban county',
            1000 => 'Order 4 Area', 1100 => 'Order 5 Area',
            1200 => 'Application Area'],

        // line
        1 => [100 => 'Road', 101 => 'Motorway', 102 => '1 st Class Road',
            103 => '2 nd Class Road', 104 => '3 rd Class Road',
            200 => 'Ring-road', 201 => 'Ring motorway', 202 => 'Other ring road',
            300 => 'Order 1 segment', 400 => 'Order 2 segment',
            500 => 'Urban street', 600 => 'Vehicular link', 601 => 'Ferry',
            602 => 'Vehicular rail link'],

        // point
        2 => [100 => 'Junction', 101 => 'Motorway intersection',
            102 => 'Motorway triangle', 103 => 'Motorway junction',
            104 => 'Motorway exit', 105 => 'Motorway entrance', 106 => 'Flyover',
            107 => 'Underpass', 108 => 'Roundabout', 109 => 'Gyratory',
            110 => 'Traffic lights', 111 => 'Cross – roads', 112 => 'T-junction',
            113 => 'Intermediate node', 114 => 'Connection', 115 => 'Exit',
            200 => 'Intermediate point', 201 => 'Distance marker',
            202 => 'Traffic monitoring station', 300 => 'Other landmark points',
            301 => 'Tunnel', 302 => 'Bridge', 303 => 'Service area',
            304 => 'Rest area', 305 => 'View point', 306 => 'Carpool point',
            307 => 'Park and ride site', 308 => 'Car park', 309 => 'Kiosk',
            310 => 'Kiosk with WC', 311 => 'Petrol station',
            312 => 'Petrol station with kiosk', 313 => 'Motel',
            314 => 'Border/frontier', 315 => 'Customer post',
            316 => 'Toll plaza', 317 => 'Ferry terminal', 318 => 'Harbour',
            319 => 'Square', 320 => 'Fair', 321 => 'Garage',
            322 => 'Underground garage', 323 => 'Retail park',
            324 => 'Theme park', 325 => 'Tourist attraction',
            326 => 'University', 327 => 'Airport', 328 => 'Station',
            329 => 'Hospital', 330 => 'Church', 331 => 'Stadium',
            332 => 'Palace', 333 => 'Castle', 334 => 'Town hall',
            335 => 'Exhibition/convention centre', 336 => 'Communities',
            337 => 'Place name', 338 => 'Dam', 339 => 'Dike', 340 => 'Aqueduct',
            341 => 'Lock', 342 => 'Mountain crossing/pass',
            343 => 'Railroad crossing', 344 => 'Wade', 345 => 'Ferry',
            346 => 'Industrial area', 347 => 'Viaduct', 400 => 'Link road point',
            401 => 'Rent-a-car facility', 402 => 'Restaurant',
            403 => 'Tourist office', 404 => 'Museum', 405 => 'Theatre',
            406 => 'Sports activity', 407 => 'Ski resort',
            408 => 'Community centre', 409 => 'Open parking area',
            410 => 'Recreation facility', 411 => 'Shopping centre',
            412 => 'Business facility', 413 => 'Bus station',
            414 => 'Golf course', 415 => 'Marina', 416 => 'Amusement park',
            417 => 'Historical monument', 418 => 'Bowling centre',
            419 => 'Casino', 420 => 'Cinema', 421 => 'Ice skating rink',
            422 => 'Nightlife', 423 => 'Public sport airport',
            424 => 'Sports centre', 425 => 'Winery',
            426 => 'Automobile dealership', 427 => 'Motorcycle dealership',
            428 => 'Parking garage'],
    ];

    public static function msTimezoneStringToTimezone($msTimezoneString): ?DateTimeZone
    {
        return match ($msTimezoneString) {
            'W. Europe Standard Time' => new DateTimeZone('Europe/Berlin'),
            default => null,
        };
    }

    public static function parseTime($str, DateTimeZone $timezone): DateTime|false
    {
        return DateTime::createFromFormat(
            'Y-m-d\TH:i:s',
            $str,
            $timezone
        );
    }

    public static function statusCodeToString($code): string
    {
        return match ($code) {
            1 => 'creation',
            2 => 'modification',
            4 => 'cancellation',
            5 => 'deletion',
            6 => 'extension',
            default => 'unknown',
        };
    }

    public static function parseMessages(SimpleXMLElement $parent): array
    {
        $codeMap = [0 => 'roadNumber', 1 => 'segment', 2 => 'content',
            3 => 'freeText', 4 => 'complement', 20 => 'segmentFrom',
            21 => 'segmentTo', 22 => 'state', 23 => 'city', 40 => 'name',
            41 => 'roadName', 42 => 'secondName', 43 => 'areaName',
            44 => 'linearFirstName', 45 => 'linearSecondName', 46 => 'county',
            47 => 'postalCode', 48 => 'houseNumber'];
        $messages = [];

        foreach ($codeMap as $messageCode => $messageKey) {
            $messageTexts = $parent->xpath(
                './MES/MDA/MDC[.="' . $messageCode . '"]/../MDT'
            );

            switch (count($messageTexts)) {
                case 0:
                    break;
                case 1:
                    $messages[$messageKey] = (string)$messageTexts[0][0];
                    break;
                default:
                    $messages[$messageKey] = array_map(
                        fn(array $messageText): string => (string)$messageText[0],
                        $messageTexts
                    );
                    break;
            }
        }

        return $messages;
    }

    public static function locationTypeCodeSubTypeCodeToString($code, $subCode)
    {
        return self::$stringByLocationTypeCodeAndSubCode[$code][$subCode] ?? 'misc';
    }

    public static function buildDescription(array $messagse): string
    {
        return self::buildFieldFromMessages(
            $messagse,
            ['roadNumber', 'segment', 'content', 'freeText', 'complement']
        );
    }

    public static function buildName(array $messagse): string
    {
        return self::buildFieldFromMessages(
            $messagse,
            ['roadNumber', 'segment']
        );
    }

    public static function buildFieldFromMessages(array $messages, array $keys): string
    {
        $result = [];
        foreach ($keys as $key) {
            if (isset($messages[$key])) {
                $text = is_array($messages[$key]) ?
                    implode(', ', $messages[$key]) :
                    $messages[$key];
                $result[] = $text;
            }
        }

        return implode(' ', $result);
    }

    public static function parseLocationData(SimpleXMLElement $loc): array
    {
        $curLoc = [];

        $curLoc['pos'] = [];
        foreach ($loc->xpath('./LCO') as $lco) {
            $x = $lco->xpath('./X')[0] ?? false;
            $y = $lco->xpath('./Y')[0] ?? false;
            if ($x !== false && $y !== false) {
                $curLoc['pos'][] = [
                    'x' => ((int)$x) / 100000,
                    'y' => ((int)$y) / 100000,
                ];
            }
        }

        if (($val = $loc->xpath('./LCD')[0] ?? false) !== false) {
            $curLoc['code'] = (int)$val;
        }

        if (($val = $loc->xpath('./LTP')[0] ?? false) !== false) {
            $curLoc['typeCode'] = (int)$val;

            switch ($curLoc['typeCode']) {
                case 0:
                    $curLoc['type'] = 'area';
                    break;
                case 1:
                    $curLoc['type'] = 'line';
                    break;
                case 2:
                    $curLoc['type'] = 'point';
                    break;
            }
        }

        $curLoc['messages'] = self::parseMessages($loc);

        if (($val = $loc->xpath('./LSU')[0] ?? false) !== false) {
            $curLoc['subTypeCode'] = (int)$val;
            if (isset($curLoc['typeCode'])) {
                $curLoc['subType'] = self::locationTypeCodeSubTypeCodeToString(
                    $curLoc['typeCode'],
                    (int)$val
                );
            }
        }

        return $curLoc;
    }

    public static function parseEntry(SimpleXMLElement $entry): array
    {
        $res = [];

        if (($val = $entry->xpath('./ORI/ONA')[0] ?? false) !== false) {
            $res['originName'] = trim((string)$val);
        }

        if (($val = $entry->xpath('./IID')[0] ?? false) !== false) {
            $res['guid'] = trim((string)$val);
        }

        if (($val = $entry->xpath('./IDT/ORG')[0] ?? false) !== false) {
            $res['organisation'] = trim((string)$val);
        }

        $timezone = null;
        if (($val = $entry->xpath('./VER/MNG/TZI')[0] ?? false) !== false) {
            $timezone = Utils::msTimezoneStringToTimezone((string)$val);
        }

        if (!$timezone instanceof DateTimeZone) {
            $timezone = new DateTimeZone('UTC');
        }

        // activation time
        if (($val = $entry->xpath('./VER/MNG/ACT')[0] ?? false) !== false) {
            $res['activateTime'] = Utils::parseTime((string)$val, $timezone);
        }

        // expiration time
        if (($val = $entry->xpath('./VER/MNG/EXP')[0] ?? false) !== false) {
            $res['expiryTime'] = Utils::parseTime((string)$val, $timezone);
        }

        if (($val = $entry->xpath('./VER/MNG/STA')[0] ?? false) !== false) {
            $res['status'] = Utils::statusCodeToString((int)$val);
        }

        if (($val = $entry->xpath('./VER/TRA/TTI/TSA')[0] ?? false) !== false) {
            $res['startTime'] = Utils::parseTime((string)$val, $timezone);
        }

        if (($val = $entry->xpath('./VER/TRA/TTI/TSO')[0] ?? false) !== false) {
            $res['stopTime'] = Utils::parseTime((string)$val, $timezone);
        }

        // additional data "messages"
        if (($val = $entry->xpath('./VER/TRA')[0] ?? false) !== false) {
            $res['messages'] = Utils::parseMessages($val);
            $res['name'] = Utils::buildName($res['messages']);
            $res['description'] = Utils::buildDescription($res['messages']);
        }

        // type of events
        foreach ($entry->xpath('./VER/TRA/EVT/EDA') as $eventData) {
            if (($val = $eventData->xpath('./ECT')[0] ?? false) !== false) {
                $val = (int)$val;

                if (!isset($res['eventTypes'])) {
                    $res['eventTypes'] = [];
                }

                $res['eventTypes'][] = [
                    'code' => $val,
                    'type' => CommonUtils::eventTypeCategoryCodeToString($val),
                ];
            }
        }

        if (($val = $entry->xpath('./VER/TRA/LCA')[0] ?? false) !== false) {
            $res['locMessages'] = Utils::parseMessages($val);
        }

        $res['locations'] = array_map(self::parseLocationData(...), $entry->xpath('./VER/TRA/LCA/LOC'));

        // take multiple points as coordinates of one LineString
        // even though they might not be a line and
        // accuracy is pretty bad
        // „connect the dots“
        $lineCoordinates = [];
        $messages = [];
        foreach ($res['locations'] as $location) {
            $lineCoordinates = array_merge($lineCoordinates, $location['pos']);
            $messages = array_merge($messages, $location['messages']);
        }

        if (isset($lineCoordinates[1])) {
            $res['locations'] = [
                [
                    'type' => 'line',
                    'typeCode' => 1,
                    'pos' => $lineCoordinates, // collected positions
                    'messages' => $messages, // collected messages
                    'code' => $res['locations'][0]['code'], // only use first code
                    'subType' => $res['locations'][0]['subType'], // only use first subType
                    'subTypeCode' => $res['locations'][0]['subTypeCode'], // only use first subTypeCode
                ],
            ];
        }

        return $res;
    }
}
