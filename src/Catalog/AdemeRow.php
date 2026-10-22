<?php

namespace App\Catalog;

use App\Enum\Body;
use App\Enum\Energy;
use App\Enum\Gearbox;

/**
 * Convertit une ligne du jeu « ADEME - Car Labelling » en version du catalogue (tableau à plat), ou null si elle est inutilisable.
 */
final class AdemeRow
{
    private const array ENERGIES = [
        'ESSENCE' => Energy::Petrol,
        'GAZOLE' => Energy::Diesel,
        'ESS+ELEC HNR' => Energy::Hybrid,
        'GAZ+ELEC HNR' => Energy::Hybrid,
        'ELEC+ESSENC HR' => Energy::PluginHybrid,
        'ELEC+GAZOLE HR' => Energy::PluginHybrid,
        'ELECTRIC' => Energy::Electric,
        'SUPERETHANOL' => Energy::Ethanol,
        'ESS+G.P.L.' => Energy::Lpg,
    ];

    private const array BRANDS = [
        'B.M.W.' => 'BMW', 'M.G.' => 'MG', 'DS' => 'DS', 'CITROEN' => 'Citroën', 'LAMBORGHIN' => 'Lamborghini',
        'ROLLS ROYC' => 'Rolls-Royce', 'MERCEDES' => 'Mercedes-Benz', 'LAND ROVER' => 'Land Rover', 'ALFA ROMEO' => 'Alfa Romeo',
    ];

    /** Noms que la casse automatique abîme */
    private const array MODELS = ['I 10' => 'i10', 'I 20' => 'i20', 'I 30' => 'i30', 'IX' => 'iX', 'IX3' => 'iX3', 'T-ROC' => 'T-Roc', 'E' => 'e', 'RIO' => 'Rio', 'ZOE' => 'Zoe', 'SANTA FE' => 'Santa Fe', 'S-MAX' => 'S-Max', 'MINI' => 'Hatch'];

    /** Accents que la source perd en capitales */
    private const array ACCENTS = ['Serie' => 'Série', 'Megane' => 'Mégane', 'Scenic' => 'Scénic', 'Coupe' => 'Coupé'];

    /**
     * @param array<string, mixed> $raw une ligne telle que renvoyée par l'API
     *
     * @return array{key: string, brand: string, model: string, name: string, body: Body, energy: Energy, gearbox: Gearbox, price: int, power: int, consumption: ?float, co2: ?int, weight: ?int, electricRange: ?int}|null
     */
    public static function map(array $raw): ?array
    {
        // L'API renvoie les clés accentuées mal encodées : on ne garde que leurs caractères ASCII (« Prix_vhicule »)
        $r = [];
        foreach ($raw as $key => $value) {
            $r[preg_replace('/[^A-Za-z0-9_-]/', '', (string) $key)] = \is_string($value) ? self::text($value) : $value;
        }

        $energy = self::ENERGIES[$r['Energie'] ?? ''] ?? null;
        $price = (int) ($r['Prix_vhicule'] ?? 0);
        $rawModel = trim((string) ($r['Modle'] ?? ''));
        if (!$energy || $price <= 0 || '' === $rawModel) {
            return null;
        }

        $brandRaw = trim((string) ($r['Marque'] ?? ''));
        $brand = self::BRANDS[$brandRaw] ?? self::nice($brandRaw, 3);
        // BMW range ses modèles par motorisation (« 320 ») : le libellé (« SERIE 3 ») est le vrai nom
        $model = 'BMW' === $brand && ctype_digit($rawModel) ? (string) ($r['Libell_modle'] ?? $rawModel) : $rawModel;
        $model = self::MODELS[$model] ?? self::nice($model);

        $description = trim((string) ($r['Description_Commerciale'] ?? ''));
        $power = self::power($description, $r);
        if (!$power) {
            return null;
        }
        $name = self::name($description, [$rawModel, (string) ($r['Libell_modle'] ?? '')], $power);

        $electric = Energy::Electric === $energy;
        $gearbox = 'MECANIQUE' === ($r['Type_de_boite'] ?? null) ? Gearbox::Manual : Gearbox::Automatic;
        $consumption = $electric
            ? self::average($r, 'Conso_elec', 10)
            : self::average($r, 'Conso_vitesse_mixte');
        $co2 = $electric ? 0 : self::average($r, 'CO2_vitesse_mixte');
        $range = \in_array($energy, [Energy::Electric, Energy::PluginHybrid], true)
            ? (int) ($r['Autonomie_elec_Min'] ?? $r['Autonomie_elec_Max'] ?? 0) ?: null
            : null;

        return [
            'key' => sha1(implode('|', [$brand, $model, $name, $energy->value, $gearbox->value, $power, $price])),
            'brand' => $brand,
            'model' => $model,
            'name' => $name,
            'body' => self::body((string) ($r['Carrosserie'] ?? ''), (string) ($r['Gamme'] ?? '')),
            'energy' => $energy,
            'gearbox' => $gearbox,
            'price' => $price,
            'power' => $power,
            'consumption' => null === $consumption ? null : round($consumption, 1),
            'co2' => null === $co2 ? null : (int) round($co2),
            'weight' => (int) ($r['Poids__vide'] ?? 0) ?: null,
            'electricRange' => $range,
        ];
    }

    /** Répare un texte encodé deux fois (« CoupÃ© » → « Coupé ») */
    private static function text(string $value): string
    {
        if (!str_contains($value, 'Ã')) {
            return $value;
        }
        $fixed = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');

        return mb_check_encoding($fixed, 'UTF-8') ? $fixed : $value;
    }

    /** « SERIE 3 » → « Série 3 », « T-ROC » → « T-Roc » ; sigles courts et mots à chiffres intacts (TSI, ID.3, DSG7) */
    public static function nice(string $value, int $minWord = 4): string
    {
        $value = preg_replace_callback('/[A-ZÀ-Ý]{'.$minWord.',}/u', fn (array $m) => mb_convert_case($m[0], MB_CASE_TITLE), $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtr($value, self::ACCENTS);
    }

    /** En ch : d'abord la description (« (110ch) », « (57Kw) »), puis la puissance en kW de la source */
    private static function power(string $description, array $r): ?int
    {
        if (preg_match('/(\d{2,4})\s*ch\b/i', $description, $m)) {
            return (int) $m[1];
        }
        $kw = preg_match('/(\d{2,4})\s*kw\b/i', $description, $m) ? (float) $m[1]
            : (float) ($r['Puissance_maximale'] ?? $r['Puissance_nominale_lectrique'] ?? 0);

        return $kw > 0 ? (int) round($kw * 1.35962) : null;
    }

    /** La description sans le nom du modèle, la puissance écrite à la française : « CLIO (67ch) » → « 67 ch » */
    private static function name(string $description, array $modelNames, int $power): string
    {
        foreach (array_filter($modelNames) as $model) {
            // Le modèle seul, pas le début d'un mot : « 320d » garde son 320
            $stripped = preg_replace('/^'.preg_quote($model, '/').'(?![a-z0-9])[\s-]*/i', '', $description, 1, $count);
            if ($count) {
                $description = $stripped;
                break;
            }
        }
        $description = preg_replace(
            ['/\(?(\d{2,4})\s*ch\)?/i', '/\(?(\d{2,4})\s*kw\)?/i', '/\s+(pn de|norme|euro ?6).*$/i', '/Ã\s/u'],
            ['$1 ch', '$1 kW', '', 'à '],
            $description,
        );
        $name = self::nice($description);

        return mb_substr('' === $name ? $power.' ch' : $name, 0, 80);
    }

    /** Moyenne du minimum et du maximum WLTP d'une mesure (ou de celle des deux qui existe) */
    private static function average(array $r, string $field, float $divide = 1): ?float
    {
        $values = array_filter([$r[$field.'_Min'] ?? null, $r[$field.'_Max'] ?? null], fn ($v) => is_numeric($v) && $v > 0);

        return $values ? array_sum($values) / \count($values) / $divide : null;
    }

    private static function body(string $body, string $range): Body
    {
        return match ($body) {
            'TS TERRAINS/CHEMINS' => Body::Suv,
            'BREAK' => Body::Estate,
            'COUPE' => Body::Coupe,
            'CABRIOLET' => Body::Convertible,
            'COMBISPACE', 'MINIBUS', 'MONOSPACE', 'MONOSPACE COMPACT', 'MINISPACE' => Body::Mpv,
            default => match ($range) {
                'ECONOMIQUE', 'INFERIEURE' => Body::CityCar,
                'MOYENNE INFERIEURE' => Body::Compact,
                default => Body::Sedan,
            },
        };
    }
}
