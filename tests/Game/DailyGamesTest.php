<?php

namespace App\Tests\Game;

use App\Entity\Brand;
use App\Entity\Model;
use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use App\Game\DailyGames;
use PHPUnit\Framework\TestCase;

final class DailyGamesTest extends TestCase
{
    private function model(string $brand, string $name, Body $body, array $versions): Model
    {
        $model = (new Model())->setBrand(new Brand($brand))->setName($name)->setBody($body);
        foreach ($versions as [$price, $power]) {
            $version = (new Version())->setModel($model)->setEnergy(Energy::Petrol)->setPrice($price)->setPower($power)->setConsumption(5.0)->setWeight(1100);
            $model->getVersions()->add($version);
        }

        return $model;
    }

    public function testWinnerFollowsTheDirectionAndRefusesTies(): void
    {
        $cars = array_map(fn (array $v) => (new Version())->setPrice($v[0])->setPower($v[1]), [[20000, 100], [18000, 150], [25000, 150], [30000, 90]]);

        $this->assertSame(1, DailyGames::winner($cars, 'price'), 'le moins cher');
        $this->assertNull(DailyGames::winner($cars, 'power'), 'deux à 150 ch : la question est écartée');
    }

    public function testCluesGrowWithEachMistakeAndCompareSaysWhichWay(): void
    {
        $answer = $this->model('Dacia', 'Sandero', Body::CityCar, [[14000, 65], [17000, 110]]);
        $guess = $this->model('Renault', 'Clio', Body::CityCar, [[18000, 90]]);

        $this->assertCount(1, DailyGames::clues($answer, 0));
        $this->assertSame('dès 14 000 €', DailyGames::clues($answer, 1)[1]['value']);
        $this->assertCount(6, DailyGames::clues($answer, 9), 'jamais plus que les indices prévus');

        $row = DailyGames::compare($guess, $answer);
        $this->assertFalse($row['brand']);
        $this->assertTrue($row['body']);
        $this->assertSame('down', $row['price'], 'la Sandero part de moins cher');
        $this->assertSame('up', $row['power'], 'et monte plus haut en puissance');
    }

    public function testVersionNamesNeverGiveThePowerAway(): void
    {
        $this->assertSame('1.0 essence T-GDi Mhev iBVM6', DailyGames::hidePower('1.0 essence T-GDi 120 ch Mhev iBVM6'));
        $this->assertSame('CRDi Htrac DCT-7', DailyGames::hidePower('CRDi 136 Htrac DCT-7'));
        $this->assertSame('SW', DailyGames::hidePower('SW 181 ch+30 kW'));
        $this->assertSame('', DailyGames::hidePower('110 ch'));
        $this->assertSame('Nouvelle i10 1.0 ECO 4PL', DailyGames::hidePower('Nouvelle i10 1.0 ECO 4PL'), 'cylindrée et places gardées');
    }

    public function testPhotoStaysBlurryUntilTheEnd(): void
    {
        $this->assertCount(DailyGames::TRIES + 1, DailyGames::PHOTO_WIDTHS);
        $this->assertLessThanOrEqual(120, max(\array_slice(DailyGames::PHOTO_WIDTHS, 0, DailyGames::TRIES)), 'jamais nette avant la fin');
        $this->assertSame(
            'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1c/Clio.jpg/40px-Clio.jpg',
            \App\Catalog\ImageFinder::resize('https://upload.wikimedia.org/wikipedia/commons/thumb/1/1c/Clio.jpg/960px-Clio.jpg', 40),
        );
    }
}
