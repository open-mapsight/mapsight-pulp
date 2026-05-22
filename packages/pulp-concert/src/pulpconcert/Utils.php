<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert;

use DateTime;
use DateTimeZone;
use Exception;
use OpenMapsight\pulpconcert\Model\DataObject;
use SimpleXMLElement;

class Utils
{
    /**
     * @param string $dateTimeString
     * @param string $format optional datetime format, default: \DateTime::ATOM
     *
     * @return DateTime
     */
    public static function parsePulpDateTimeObject(
        string $dateTimeString,
        string $format = DateTime::ATOM
    ): DateTime {
        return DateTime::createFromFormat($format, $dateTimeString);
    }

    /**
     * @param SimpleXMLElement $rootElement
     * @param string $class
     * @param callable $mapper
     * @param DataObject[] $elements
     *
     * @return DataObject[]
     * @throws Exception
     */
    public static function parseConcertResponse(
        SimpleXMLElement $rootElement,
        string $class,
        callable $mapper,
        array $elements = []
    ): array {
        if (!$rootElement->ds) {
            echo 'Document is missing ds\'s';

            return $elements;
        }

        /** @var SimpleXMLElement $e */
        foreach ($rootElement->ds as $e) {
            /** @var DataObject $object */
            $object = new $class();

            if ($e->identifier->ident !== false) {
                $identifier = (string) $e->identifier->ident;
                $object->setIdentifier($identifier);

                // use existing element if identifier matches
                foreach ($elements as $element) {
                    if ($element instanceof $class && $element->getIdentifier() === $identifier) {
                        $object = $element;
                        break;
                    }
                }
            }

            if ($e->tstore !== false) {
                $object->setStoreTimestamp(self::convertConcertDateTime($e->tstore[0]));
            }

            if ($e->objectState !== false) {
                $object->setObjectState((string) $e->objectState);
            }

            if ($e->identifier->source !== false) {
                $object->setSource((string) $e->identifier->source);
            }

            $result = $mapper($e, $object);

            if ($result !== false) {
                if (is_array($result)) {
                    $elements[$result[0]] = $result[1];
                } else {
                    $elements[] = $result;
                }
            }
        }

        return $elements;
    }

    /**
     * @param SimpleXMLElement $element
     *
     * @return string
     * @throws Exception
     */
    public static function convertConcertDateTime(SimpleXMLElement $element): string
    {
        return self::formatDateTimeObject(self::parseConcertDateTimeObject($element));
    }

    /**
     * @param DateTime $dateTime
     * @param string $format optional datetime format, default: \DateTime::ATOM
     *
     * @return string
     */
    public static function formatDateTimeObject(DateTime $dateTime, $format = DateTime::ATOM): string
    {
        return $dateTime->format($format);
    }

    /**
     * @param SimpleXMLElement $element
     *
     * @return DateTime
     * @throws Exception
     */
    public static function parseConcertDateTimeObject(SimpleXMLElement $element): DateTime
    {
        $dateTime = new DateTime((string) $element, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));

        return $dateTime;
    }
}
