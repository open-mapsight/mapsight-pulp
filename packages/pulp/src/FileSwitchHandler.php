<?php

declare(strict_types=1);

namespace OpenMapsight\pulp;

use OpenMapsight\Pulp;

class FileSwitchHandler extends AbstractHandler
{
    public function onFile(File $file): void
    {
        $matched = false;
        foreach ($this->cp->patterns as $pattern => $cb) {
            if (!Utils::matchFileName($pattern, $file->fileName)) {
                continue;
            }

            $matched = true;
            $this->callCbWithPulpAndPush($cb, $file);

            // TODO: file objekt kopieren und weiter matchen, da es ggf. auch durch
            // patterns gematched werden kann. erst mal break als workaround.
            break;
        }

        if (!$matched) {
            if (isset($this->cp->defaultCb)) {
                $this->callCbWithPulpAndPush($this->cp->defaultCb, $file);
            } else {
                $this->pushFile($file);
            }
        }
    }

    private function callCbWithPulpAndPush($cb, File $file): void
    {
        $pulp = Pulp::start();
        $resultFiles = $cb($pulp)->run($file);

        foreach ($resultFiles as $rFile) {
            $this->pushFile($rFile);
        }
    }

    protected function getConstructorParamDefs(): array
    {
        return ['patterns', 'defaultCb'];
    }
}
