<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\TrafficData;

use Exception;
use OpenMapsight\pulp\File;
use OpenMapsight\pulpconcert\AbstractProjectionHandler;
use OpenMapsight\pulpconcert\TrafficData\Mapper\Legacy\SubSectionMapper as LegacySubSectionMapper;
use OpenMapsight\pulpconcert\TrafficData\Model\SubSection;
use OpenMapsight\pulpconcert\Utils;
use SimpleXMLElement;

class ParseSubSectionDescriptionsHandler extends AbstractProjectionHandler
{
    /**
     * @param File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        /** @var SimpleXMLElement $data */
        $data = $file->content;

        $file->content = Utils::parseConcertResponse(
            $data,
            SubSection::class,
            // NOTE: Only supporting legacy syntax for now
            fn(SimpleXMLElement $e, SubSection $subSection): SubSection|false => LegacySubSectionMapper::mapSubSection($this, $e, $subSection)
        );
        $this->pushFile($file);
    }
}
