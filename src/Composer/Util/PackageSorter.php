<?php declare(strict_types=1);

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Util;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;

class PackageSorter
{
    /**
     * Sorts packages by dependency weight
     *
     * Packages of equal weight are sorted alphabetically
     *
     * @param  PackageInterface[] $packages
     * @return PackageInterface[] sorted array
     */
    public static function sortPackages(array $packages): array
    {
        $usageList = array();

        foreach ($packages as $package) {
            $links = $package->getRequires();
            if ($package instanceof RootPackageInterface) {
                $links = array_merge($links, $package->getDevRequires());
            }
            foreach ($links as $link) {
                $target = $link->getTarget();
                $usageList[$target][] = $package->getName();
            }
        }
        $computing = array();
        $computed = array();
        $computeImportance = function ($name) use (&$computeImportance, &$computing, &$computed, $usageList) {
            // reusing computed importance
            if (isset($computed[$name])) {
                return $computed[$name];
            }

            // canceling circular dependency
            if (isset($computing[$name])) {
                return 0;
            }

            $computing[$name] = true;
            $weight = 0;

            if (isset($usageList[$name])) {
                foreach ($usageList[$name] as $user) {
                    $weight -= 1 - $computeImportance($user);
                }
            }

            unset($computing[$name]);
            $computed[$name] = $weight;

            return $weight;
        };

        $weightedPackages = array();

        foreach ($packages as $index => $package) {
            $name = $package->getName();
            $weight = $computeImportance($name);
            $weightedPackages[] = array('name' => $name, 'weight' => $weight, 'index' => $index);
        }

        usort($weightedPackages, function (array $a, array $b): int {
            if ($a['weight'] !== $b['weight']) {
                return $a['weight'] - $b['weight'];
            }

            return strnatcasecmp($a['name'], $b['name']);
        });

        $sortedPackages = array();

        foreach ($weightedPackages as $pkg) {
            $sortedPackages[] = $packages[$pkg['index']];
        }

        return $sortedPackages;
    }
}
