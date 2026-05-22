<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert;

use SimpleXMLElement;

abstract class AbstractMergeIntoKMLHandler extends AbstractMergeIntoHandler
{
    protected $PROPERTY_KEY_EXTERNAL_ID = 'externid';
    protected $propertyWhitelist = [];

    protected function loopFeatures($file, $data)
    {
        /** @var SimpleXMLElement $kml */
        $kml = $file->content;
        /** @var SimpleXMLElement $feature */
        foreach ($kml->Document->Placemark as $feature) {
            $this->handleFeature($feature, $file, $data);
        }

        $file->content = $kml->asXML();
        $file->content = str_replace('<kml>', '<kml xmlns="http://earth.google.com/kml/2.2">', $file->content);

        return $file;
    }

    /**
     * @param SimpleXMLElement $targetObject
     * @return bool|string
     */
    protected function getFeatureExternalId($targetObject)
    {
        $results = $targetObject->xpath('ExtendedData/Data[@name="' . $this->PROPERTY_KEY_EXTERNAL_ID . '"]/value');

        return $results[0] ? (string) $results[0] : false;
    }

    protected function addData(SimpleXMLElement $placemark, $name, $value)
    {
        /** @var SimpleXMLElement $auslastungElement */
        $auslastungElement = $placemark->ExtendedData->addChild('Data');
        $auslastungElement->addAttribute('name', $name);
        $auslastungElement->addChild('value', $value);
    }

    protected function addCData($name, $value, SimpleXMLElement $parent): SimpleXMLElement
    {
        $child = $parent->addChild($name);

        if ($child !== null) {
            $childNode = dom_import_simplexml($child);
            $childNode->appendChild($childNode->ownerDocument->createCDATASection($value));
        }

        return $child;
    }
}
