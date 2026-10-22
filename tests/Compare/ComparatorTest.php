<?php

namespace App\Tests\Compare;

use App\Compare\Comparator;
use App\Entity\Version;
use App\Enum\Energy;
use PHPUnit\Framework\TestCase;

final class ComparatorTest extends TestCase
{
    private function version(Energy $energy, int $price, int $power, float $consumption, int $trunk, int $co2): Version
    {
        return (new Version())->setEnergy($energy)->setPrice($price)->setPower($power)
            ->setConsumption($consumption)->setTrunk($trunk)->setCo2($co2);
    }

    /** @return array<string, array> */
    private function rowsByKey(Version ...$versions): array
    {
        return array_column((new Comparator())->rows($versions), null, 'key');
    }

    public function testBestFollowsDirectionOfEachCriterion(): void
    {
        $rows = $this->rowsByKey(
            $this->version(Energy::Petrol, 20000, 100, 5.5, 350, 125),
            $this->version(Energy::Diesel, 25000, 130, 4.5, 400, 118),
        );

        $this->assertSame([true, false], array_column($rows['price']['cells'], 'best'), 'le prix le plus bas gagne');
        $this->assertSame([false, true], array_column($rows['power']['cells'], 'best'), 'la puissance la plus haute gagne');
        $this->assertSame([false, true], array_column($rows['consumption']['cells'], 'best'));
        $this->assertSame([false, true], array_column($rows['trunk']['cells'], 'best'));
        $this->assertSame([false, true], array_column($rows['co2']['cells'], 'best'));
    }

    public function testGapInValueAndPercentAgainstBest(): void
    {
        $rows = $this->rowsByKey(
            $this->version(Energy::Petrol, 20000, 100, 5, 300, 120),
            $this->version(Energy::Petrol, 25000, 150, 5, 300, 120),
        );

        $expensive = $rows['price']['cells'][1];
        $this->assertSame(5000, $expensive['gap']);
        $this->assertSame(25.0, $expensive['gapPercent']);
        $this->assertNull($rows['price']['cells'][0]['gap'], 'le meilleur n\'a pas d\'écart');
        $this->assertSame([true, true], array_column($rows['trunk']['cells'], 'best'), 'ex aequo : tous deux en tête');
    }

    public function testMixedConsumptionUnitsAreNotRanked(): void
    {
        $rows = $this->rowsByKey(
            $this->version(Energy::Petrol, 20000, 100, 5.5, 350, 125),
            $this->version(Energy::Electric, 30000, 136, 15.0, 300, 0),
        );

        $this->assertFalse($rows['consumption']['comparable']);
        $this->assertSame([false, false], array_column($rows['consumption']['cells'], 'best'));
        $this->assertNull($rows['co2']['cells'][0]['gapPercent'], 'pas de % face à 0 g/km');
        $this->assertSame(125, $rows['co2']['cells'][0]['gap']);
    }

    public function testConsumptionIsRankedWithinTheSameUnit(): void
    {
        $rows = $this->rowsByKey(
            $this->version(Energy::Petrol, 20000, 100, 5.5, 350, 125),
            $this->version(Energy::Electric, 30000, 136, 15.0, 300, 0),
            $this->version(Energy::Hybrid, 25000, 140, 4.4, 330, 100),
        );

        $this->assertTrue($rows['consumption']['comparable']);
        $this->assertTrue($rows['consumption']['mixed']);
        $this->assertSame([false, false, true], array_column($rows['consumption']['cells'], 'best'), "l'électrique seule n'est pas départagée");
        $this->assertEqualsWithDelta(1.1, $rows['consumption']['cells'][0]['gap'], 0.001);
        $this->assertNull($rows['consumption']['cells'][1]['gap']);
    }

    public function testUnknownValuesAreNeverRanked(): void
    {
        $withTrunk = $this->version(Energy::Petrol, 20000, 100, 5.5, 350, 125);
        $ademe = $this->version(Energy::Petrol, 21000, 110, 5.0, 0, 120)->setTrunk(null);
        $ademeToo = $this->version(Energy::Petrol, 22000, 120, 5.2, 0, 118)->setTrunk(null)->setWeight(1200);
        $rows = $this->rowsByKey($withTrunk, $ademe, $ademeToo);

        $this->assertSame([false, false, false], array_column($rows['trunk']['cells'], 'best'), 'un seul coffre connu : rien à départager');
        $this->assertNull($rows['trunk']['cells'][1]['value']);
        $this->assertNull($rows['weight']['cells'][0]['gap'], 'poids inconnu : pas d\'écart');
        $this->assertArrayNotHasKey('electricRange', $rows, 'aucune autonomie connue : ligne absente');

        $rows = $this->rowsByKey($ademe, $ademeToo);
        $this->assertArrayNotHasKey('trunk', $rows);
    }

    public function testParseIds(): void
    {
        $this->assertSame([12, 34, 56], Comparator::parseIds('12,34,56'));
        $this->assertSame([3, 1, 2, 4], Comparator::parseIds('3,abc,1,3,,-5,2,4,9'));
        $this->assertSame([], Comparator::parseIds(''));
    }
}
