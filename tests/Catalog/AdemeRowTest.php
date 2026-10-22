<?php

namespace App\Tests\Catalog;

use App\Catalog\AdemeRow;
use App\Enum\Body;
use App\Enum\Energy;
use App\Enum\Gearbox;
use PHPUnit\Framework\TestCase;

final class AdemeRowTest extends TestCase
{
    public function testThermalRowWithBrokenKeysAndCapitals(): void
    {
        // Clés et valeurs telles que l'API les renvoie : accents mal encodés, tout en capitales
        $v = AdemeRow::map([
            'Marque' => 'B.M.W.', 'ModÃ¨le' => '420', 'LibellÃ©_modÃ¨le' => 'SERIE 4',
            'Description_Commerciale' => '420d Gran CoupÃ©', 'Energie' => 'GAZ+ELEC HNR',
            'Carrosserie' => 'COUPE', 'Gamme' => 'MOYENNE SUPERIEURE', 'Type_de_boite' => 'AUTOMATIQUE',
            'Puissance_maximale' => 140, 'Conso_vitesse_mixte_Min' => 4.784, 'Conso_vitesse_mixte_Max' => 5.47,
            'CO2_vitesse_mixte_Min' => 125.3, 'CO2_vitesse_mixte_Max' => 143.4, 'Prix_vÃ©hicule' => 56383, 'Poids_Ã _vide' => 1705,
        ]);

        $this->assertSame('BMW', $v['brand']);
        $this->assertSame('Série 4', $v['model']);
        $this->assertSame('420d Gran Coupé', $v['name'], 'le « 420 » de 420d n\'est pas pris pour le modèle');
        $this->assertSame(Energy::Hybrid, $v['energy']);
        $this->assertSame(Body::Coupe, $v['body']);
        $this->assertSame(Gearbox::Automatic, $v['gearbox']);
        $this->assertSame(190, $v['power'], '140 kW');
        $this->assertSame(5.1, $v['consumption'], 'moyenne WLTP min/max');
        $this->assertSame(134, $v['co2']);
        $this->assertSame(56383, $v['price']);
        $this->assertSame(1705, $v['weight']);
        $this->assertNull($v['electricRange']);
    }

    public function testElectricRowTakesPowerFromDescription(): void
    {
        $v = AdemeRow::map([
            'Marque' => 'RENAULT', 'Modèle' => 'CLIO', 'Libellé_modèle' => 'CLIO', 'Description_Commerciale' => 'CLIO (67ch)',
            'Energie' => 'ELECTRIC', 'Carrosserie' => 'BERLINE', 'Gamme' => 'INFERIEURE', 'Type_de_boite' => 'VARIATION ELECTRIQUE',
            'Conso_elec_Min' => 149.8, 'Conso_elec_Max' => 161.5, 'Autonomie_elec_Min' => 373, 'Prix_véhicule' => 37600,
        ]);

        $this->assertSame('Renault', $v['brand']);
        $this->assertSame('Clio', $v['model']);
        $this->assertSame('67 ch', $v['name']);
        $this->assertSame(67, $v['power']);
        $this->assertSame(15.6, $v['consumption'], 'Wh/km → kWh/100 km');
        $this->assertSame(0, $v['co2']);
        $this->assertSame(373, $v['electricRange']);
        $this->assertSame(Body::CityCar, $v['body']);
        $this->assertNull($v['weight'], 'absent de la source : null, pas 0');
    }

    public function testUnusableRowsAreIgnored(): void
    {
        $this->assertNull(AdemeRow::map(['Marque' => 'KIA', 'Modèle' => 'RIO', 'Energie' => 'ESSENCE', 'Prix_véhicule' => 0, 'Puissance_maximale' => 60]), 'sans prix');
        $this->assertNull(AdemeRow::map(['Marque' => 'KIA', 'Modèle' => 'RIO', 'Energie' => 'HYDROGENE', 'Prix_véhicule' => 20000, 'Puissance_maximale' => 60]), 'énergie inconnue');
    }
}
