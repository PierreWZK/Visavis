<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Model;
use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Catalogue de démonstration : chiffres approchés des catalogues 2024, pas une source officielle.
 */
class AppFixtures extends Fixture
{
    private const string P = 'petrol';
    private const string D = 'diesel';
    private const string H = 'hybrid';
    private const string E = 'electric';

    /**
     * marque => modèle => [carrosserie, places, versions : [nom, année, prix €, ch, conso, coffre L, CO₂ g/km, énergie]].
     */
    private const array CATALOG = [
        'Peugeot' => [
            '208' => ['city', 5, [
                ['PureTech 75', 2024, 18500, 75, 5.6, 352, 127, self::P],
                ['PureTech 100', 2024, 21000, 100, 5.4, 352, 122, self::P],
                ['Hybrid 100 e-DCS6', 2024, 24500, 100, 4.7, 352, 106, self::H],
                ['e-208 156 ch', 2024, 32500, 156, 15.0, 309, 0, self::E],
            ]],
            '2008' => ['suv', 5, [
                ['PureTech 130', 2024, 29500, 130, 5.9, 434, 134, self::P],
                ['Hybrid 136 e-DCS6', 2024, 31500, 136, 5.0, 434, 113, self::H],
                ['e-2008 156 ch', 2024, 38500, 156, 15.6, 434, 0, self::E],
            ]],
            '308 SW' => ['estate', 5, [
                ['BlueHDi 130', 2024, 34500, 130, 4.8, 608, 126, self::D],
                ['Hybrid 136 e-DCS6', 2024, 34000, 136, 5.0, 608, 114, self::H],
            ]],
        ],
        'Renault' => [
            'Clio' => ['city', 5, [
                ['TCe 90', 2024, 19500, 90, 5.4, 391, 121, self::P],
                ['Blue dCi 100', 2024, 22000, 100, 4.3, 391, 113, self::D],
                ['E-Tech full hybrid 145', 2024, 24500, 145, 4.2, 301, 95, self::H],
            ]],
            'Captur' => ['suv', 5, [
                ['TCe 90', 2024, 24500, 90, 5.8, 404, 131, self::P],
                ['E-Tech full hybrid 145', 2024, 29500, 145, 4.7, 304, 105, self::H],
            ]],
            'Mégane E-Tech' => ['suv', 5, [
                ['EV60 220 ch', 2024, 39000, 220, 16.1, 440, 0, self::E],
            ]],
            'Scénic E-Tech' => ['suv', 5, [
                ['EV87 220 ch', 2024, 47000, 220, 16.8, 545, 0, self::E],
            ]],
        ],
        'Dacia' => [
            'Sandero' => ['city', 5, [
                ['TCe 90', 2024, 14500, 90, 5.4, 328, 121, self::P],
                ['Stepway TCe 110', 2024, 17500, 110, 5.6, 328, 125, self::P],
            ]],
            'Duster' => ['suv', 5, [
                ['TCe 130', 2024, 22500, 130, 5.7, 472, 129, self::P],
                ['Hybrid 140', 2024, 25500, 140, 4.9, 430, 111, self::H],
            ]],
            'Jogger' => ['mpv', 7, [
                ['TCe 110', 2024, 19500, 110, 5.8, 160, 131, self::P],
                ['Hybrid 140', 2024, 24500, 140, 4.8, 160, 109, self::H],
            ]],
            'Spring' => ['city', 4, [
                ['Electric 65', 2024, 17000, 65, 13.9, 308, 0, self::E],
            ]],
        ],
        'Citroën' => [
            'C3' => ['city', 5, [
                ['Turbo 100', 2024, 18000, 100, 5.5, 310, 125, self::P],
                ['ë-C3 113 ch', 2024, 23300, 113, 17.4, 310, 0, self::E],
            ]],
            'C5 Aircross' => ['suv', 5, [
                ['Hybrid 136', 2024, 34000, 136, 5.3, 580, 120, self::H],
            ]],
        ],
        'Volkswagen' => [
            'Polo' => ['city', 5, [
                ['TSI 95', 2024, 21500, 95, 5.3, 351, 120, self::P],
            ]],
            'Golf' => ['compact', 5, [
                ['eTSI 116', 2024, 32000, 116, 5.4, 381, 123, self::P],
                ['TDI 116', 2024, 34500, 116, 4.4, 381, 116, self::D],
            ]],
            'ID.3' => ['compact', 5, [
                ['Pro 204 ch', 2024, 40000, 204, 15.5, 385, 0, self::E],
            ]],
            'Tiguan' => ['suv', 5, [
                ['eTSI 150', 2024, 41000, 150, 6.1, 652, 138, self::P],
                ['TDI 150', 2024, 44000, 150, 5.2, 652, 137, self::D],
            ]],
        ],
        'Toyota' => [
            'Yaris' => ['city', 5, [
                ['Hybride 116', 2024, 23500, 116, 3.9, 286, 87, self::H],
            ]],
            'C-HR' => ['suv', 5, [
                ['Hybride 140', 2024, 34500, 140, 4.8, 364, 108, self::H],
            ]],
            'Corolla Touring Sports' => ['estate', 5, [
                ['Hybride 140', 2024, 33500, 140, 4.6, 596, 104, self::H],
            ]],
        ],
        'Tesla' => [
            'Model 3' => ['sedan', 5, [
                ['Propulsion', 2024, 40000, 283, 13.2, 594, 0, self::E],
                ['Grande Autonomie', 2024, 48000, 498, 13.8, 594, 0, self::E],
            ]],
            'Model Y' => ['suv', 5, [
                ['Propulsion', 2024, 42000, 299, 15.0, 854, 0, self::E],
            ]],
        ],
        'Kia' => [
            'Niro' => ['suv', 5, [
                ['HEV 141', 2024, 32000, 141, 4.4, 451, 100, self::H],
                ['EV 204 ch', 2024, 42000, 204, 16.2, 475, 0, self::E],
            ]],
            'Sportage' => ['suv', 5, [
                ['T-GDi 150', 2024, 33500, 150, 6.8, 591, 155, self::P],
            ]],
        ],
        'Hyundai' => [
            'Tucson' => ['suv', 5, [
                ['Hybrid 215', 2024, 40000, 215, 5.6, 616, 127, self::H],
            ]],
            'Kona' => ['suv', 5, [
                ['Electric 156', 2024, 37000, 156, 14.6, 466, 0, self::E],
            ]],
        ],
        'BMW' => [
            'Série 3' => ['sedan', 5, [
                ['320i', 2024, 48500, 184, 6.5, 480, 148, self::P],
                ['320d', 2024, 51000, 190, 4.9, 480, 129, self::D],
            ]],
            'i4' => ['sedan', 5, [
                ['eDrive40', 2024, 62000, 340, 16.6, 470, 0, self::E],
            ]],
        ],
        'Skoda' => [
            'Octavia Combi' => ['estate', 5, [
                ['TSI 116', 2024, 30000, 116, 5.6, 640, 127, self::P],
                ['TDI 150', 2024, 36000, 150, 4.6, 640, 121, self::D],
            ]],
        ],
        'Fiat' => [
            '500' => ['city', 4, [
                ['Hybrid 70', 2024, 17500, 70, 5.3, 185, 120, self::P],
                ['500e 118 ch', 2024, 30000, 118, 14.3, 185, 0, self::E],
            ]],
        ],
        'MG' => [
            'MG4' => ['compact', 5, [
                ['Standard 170', 2024, 30000, 170, 16.0, 363, 0, self::E],
            ]],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::CATALOG as $brandName => $models) {
            $brand = new Brand($brandName);
            $manager->persist($brand);

            foreach ($models as $modelName => [$body, $seats, $versions]) {
                $model = (new Model())->setBrand($brand)->setName($modelName)->setBody(Body::from($body))->setSeats($seats);
                $manager->persist($model);

                foreach ($versions as [$name, $year, $price, $power, $consumption, $trunk, $co2, $energy]) {
                    $manager->persist((new Version())
                        ->setModel($model)->setName($name)->setYear($year)->setEnergy(Energy::from($energy))
                        ->setPrice($price)->setPower($power)->setConsumption($consumption)->setTrunk($trunk)->setCo2($co2));
                }
            }
        }

        $manager->flush();
    }
}
