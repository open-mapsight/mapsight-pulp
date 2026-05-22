<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use DateTime;
use Exception;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class BuildInfoHandler extends AbstractHandler
{
    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        /** @var array $json */
        $json = $file->content;
        $json['buildTimestamp'] = (new DateTime())->format(DateTime::ISO8601); // TODO: Use ATOM instead
        $file->content = $json;

        $this->pushFile($file);
    }
}
