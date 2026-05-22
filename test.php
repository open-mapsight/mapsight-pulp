#!/usr/bin/env php
<?php

/**
 * Test runner for all packages in the monorepo.
 */

$root = __DIR__;
$packagesDir = $root . '/packages';

if (!is_dir($packagesDir)) {
    echo "Packages directory not found: $packagesDir\n";
    exit(1);
}

$packages = glob($packagesDir . '/*', GLOB_ONLYDIR);
$exitCode = 0;
$testedPackages = 0;

foreach ($packages as $package) {
    $packageName = basename($package);
    $composerJsonPath = $package . '/composer.json';

    if (!file_exists($composerJsonPath)) {
        continue;
    }

    $composerJson = json_decode(file_get_contents($composerJsonPath), true);
    if (isset($composerJson['scripts']['test'])) {
        echo "\n------------------------------------------------\n";
        echo "Testing $packageName...\n";
        echo "------------------------------------------------\n";

        $command = sprintf('cd %s && composer test', escapeshellarg($package));
        passthru($command, $packageExitCode);

        if ($packageExitCode !== 0) {
            $exitCode = 1;
        }
        $testedPackages++;
    }
}

echo "\n================================================\n";
if ($testedPackages === 0) {
    echo "No packages with test scripts found.\n";
} elseif ($exitCode === 0) {
    echo "All $testedPackages package(s) passed tests!\n";
} else {
    echo "Some tests failed. See output above.\n";
}
echo "================================================\n";

exit($exitCode);
