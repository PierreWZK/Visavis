<?php

namespace App\Compare;

use App\Entity\Version;

/**
 * Aligne des versions caractéristique par caractéristique et désigne le meilleur de chaque ligne.
 */
final class Comparator
{
    public const int MAX = 4;

    /**
     * Pour chaque caractéristique : libellé, unité et sens (true : plus haut est mieux).
     * La consommation prend l'unité de l'énergie (L ou kWh) : elle n'est départagée qu'entre voitures de même unité.
     */
    private const array CRITERIA = [
        'price' => ['Prix', '€', false],
        'power' => ['Puissance', 'ch', true],
        'consumption' => ['Consommation', null, false],
        'co2' => ['Émissions CO₂', 'g/km', false],
        'weight' => ['Poids à vide', 'kg', false],
        'electricRange' => ['Autonomie électrique', 'km', true],
        'trunk' => ['Coffre', 'L', true],
    ];

    /**
     * Les lignes dont au moins une voiture a la valeur. Une valeur inconnue (null) n'est jamais classée.
     *
     * @param Version[] $versions
     *
     * @return list<array{key: string, label: string, higherIsBetter: bool, comparable: bool, mixed: bool, cells: list<array{value: int|float|null, unit: string, best: bool, gap: int|float|null, gapPercent: float|null}>}>
     */
    public function rows(array $versions): array
    {
        $rows = [];
        foreach (self::CRITERIA as $key => [$label, $unit, $higherIsBetter]) {
            $cells = array_map(fn (Version $v) => [
                'value' => $v->{'get'.ucfirst($key)}(),
                'unit' => $unit ?? $v->getEnergy()->consumptionUnit(),
            ], $versions);
            $known = array_filter($cells, fn (array $cell) => null !== $cell['value']);
            if (!$known) {
                continue;
            }

            // Meilleur par unité : les thermiques entre elles, les électriques entre elles
            $byUnit = [];
            foreach ($known as $cell) {
                $byUnit[$cell['unit']][] = $cell['value'];
            }
            $comparable = \count($byUnit) < \count($known);

            foreach ($cells as &$cell) {
                $group = null === $cell['value'] ? [] : $byUnit[$cell['unit']];
                $ranked = \count($group) > 1;
                $best = $ranked ? ($higherIsBetter ? max($group) : min($group)) : null;
                $isBest = $ranked && $cell['value'] == $best;
                $gap = $ranked && !$isBest ? abs($cell['value'] - $best) : null;
                $cell += [
                    'best' => $isBest,
                    'gap' => $gap,
                    // Pas de pourcentage face à un meilleur à zéro (0 g/km d'une électrique)
                    'gapPercent' => null !== $gap && 0 != $best ? round($gap / $best * 100, 1) : null,
                ];
            }
            unset($cell);

            $mixed = \count($byUnit) > 1;
            $rows[] = compact('key', 'label', 'higherIsBetter', 'comparable', 'mixed', 'cells');
        }

        return $rows;
    }

    /**
     * Lit « 12,34,56 » : identifiants uniques, dans l'ordre, au plus MAX.
     *
     * @return int[]
     */
    public static function parseIds(string $raw): array
    {
        $ids = array_filter(array_map('intval', explode(',', $raw)), fn (int $id) => $id > 0);

        return \array_slice(array_values(array_unique($ids)), 0, self::MAX);
    }
}
