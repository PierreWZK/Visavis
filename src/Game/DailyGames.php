<?php

namespace App\Game;

use App\Entity\Model;
use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use App\Repository\VersionRepository;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Les deux jeux du jour, tirés du catalogue : la même partie pour tout le monde un jour donné (heure de Paris).
 */
final class DailyGames
{
    /** À l'aveugle : critère => [question, sens (true : le plus haut gagne), unité] */
    public const array QUESTIONS = [
        'price' => ['Laquelle coûte le moins cher ?', false, '€'],
        'power' => ['Laquelle est la plus puissante ?', true, 'ch'],
        'consumption' => ['Laquelle consomme le moins ?', false, 'L/100 km'],
        'co2' => ['Laquelle émet le moins de CO₂ ?', false, 'g/km'],
        'weight' => ['Laquelle est la plus légère ?', false, 'kg'],
    ];

    /** Devine la voiture : nombre d'essais */
    public const int TRIES = 6;

    /** Largeur de la photo (px) selon le nombre d'erreurs : minuscule puis un peu moins ; nette (960) seulement à la fin */
    public const array PHOTO_WIDTHS = [20, 20, 40, 40, 60, 120, 960];

    /** @var list<Version>|null */
    private ?array $all = null;

    public function __construct(private readonly VersionRepository $versions)
    {
    }

    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }

    /**
     * À l'aveugle : quatre versions de modèles différents et trois questions dont la réponse est unique.
     *
     * @return array{cars: list<Version>, questions: list<string>}
     */
    public function blind(\DateTimeInterface $day): array
    {
        // Des thermiques et hybrides aux chiffres complets, pour que la consommation se compare (même unité)
        $pool = array_values(array_filter($this->all(), fn (Version $v) => Energy::Electric !== $v->getEnergy() && Body::Mpv !== $v->getModel()->getBody()
            && null !== $v->getConsumption() && null !== $v->getCo2() && null !== $v->getWeight() && $v->getPrice() <= 90000));
        $random = $this->random('blind', $day);

        for ($attempt = 0; $attempt < 50; ++$attempt) {
            $cars = [];
            foreach ($random->shuffleArray($pool) as $version) {
                $cars[$version->getModel()->getId()] ??= $version;
                if (4 === \count($cars)) {
                    break;
                }
            }
            $cars = array_values($cars);
            $questions = array_keys(array_filter(self::QUESTIONS, fn (array $q, string $key) => null !== self::winner($cars, $key), \ARRAY_FILTER_USE_BOTH));
            if (\count($cars) === 4 && \count($questions) >= 3) {
                return ['cars' => $cars, 'questions' => \array_slice($random->shuffleArray($questions), 0, 3)];
            }
        }

        throw new \RuntimeException('Catalogue trop petit pour le jeu à l\'aveugle.');
    }

    /** Le nom de version sans sa puissance (« 1.0 TCe 90 ch » → « 1.0 TCe ») : sinon la question de puissance serait donnée */
    public static function hidePower(string $name): string
    {
        $name = preg_replace(['/\d{2,4}\s*(ch|kw|cv)\b(\s*\+\s*\d{2,4}\s*(ch|kw)\b)?/i', '/(?<![\d.])\b\d{2,3}\b(?![.\d])/'], '', $name);

        return trim(preg_replace('/\s+/', ' ', $name), " \t-·");
    }

    /** L'indice de la version qui gagne ce critère, ou null en cas d'égalité */
    public static function winner(array $cars, string $key): ?int
    {
        $values = array_map(fn (Version $v) => $v->{'get'.ucfirst($key)}(), $cars);
        $best = self::QUESTIONS[$key][1] ? max($values) : min($values);
        $winners = array_keys($values, $best);

        return 1 === \count($winners) ? $winners[0] : null;
    }

    /**
     * Devine la voiture : un modèle du catalogue, à 70 000 € ou moins pour qu'il reste connu.
     */
    public function mystery(\DateTimeInterface $day): Model
    {
        $cheapest = [];
        foreach ($this->all() as $version) {
            $id = $version->getModel()->getId();
            $cheapest[$id] = min($cheapest[$id] ?? \PHP_INT_MAX, $version->getPrice());
        }
        // Un modèle connu (70 000 € ou moins) et, si possible, avec sa photo à déflouter
        $models = array_values(array_filter($this->models(), fn (Model $m) => $cheapest[$m->getId()] <= 70000));
        $withPhoto = array_values(array_filter($models, fn (Model $m) => $m->hasImage()));
        $models = $withPhoto ?: $models;

        return $models[$this->random('mystery', $day)->getInt(0, \count($models) - 1)];
    }

    /**
     * Tous les modèles, une fois chacun, dans un ordre stable.
     *
     * @return list<Model>
     */
    public function models(): array
    {
        $models = [];
        foreach ($this->all() as $version) {
            // Pas de fourgons ni de minibus (Trafic, Transit…) : ils rendent les jeux sans intérêt
            if (Body::Mpv !== $version->getModel()->getBody()) {
                $models[$version->getModel()->getId()] = $version->getModel();
            }
        }
        ksort($models);

        return array_values($models);
    }

    /**
     * Ce qu'on sait d'un modèle, toutes versions confondues.
     *
     * @return array{brand: string, name: string, body: string, energies: list<string>, price: int, power: int, consumption: ?float, consumptionUnit: string, weight: ?int, co2: ?int}
     */
    public static function profile(Model $model): array
    {
        $versions = $model->getVersions()->toArray();
        $thermal = array_filter($versions, fn (Version $v) => Energy::Electric !== $v->getEnergy());
        $known = fn (array $list, string $getter) => array_filter(array_map(fn (Version $v) => $v->$getter(), $list), fn ($x) => null !== $x);
        $min = fn (array $values) => $values ? min($values) : null;

        return [
            'brand' => $model->getBrand()->getName(),
            'name' => $model->getName(),
            'body' => $model->getBody()->label(),
            'energies' => array_values(array_unique(array_map(fn (Version $v) => $v->getEnergy()->label(), $versions))),
            'price' => min(array_map(fn (Version $v) => $v->getPrice(), $versions)),
            'power' => max(array_map(fn (Version $v) => $v->getPower(), $versions)),
            'consumption' => $min($known($thermal ?: $versions, 'getConsumption')),
            'consumptionUnit' => $thermal ? 'L/100 km' : 'kWh/100 km',
            'weight' => $min($known($versions, 'getWeight')),
            'co2' => $min($known($versions, 'getCo2')),
        ];
    }

    /**
     * Les indices dévoilés après $wrong erreurs : le premier est donné d'emblée.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function clues(Model $model, int $wrong): array
    {
        $p = self::profile($model);
        $n = fn (int|float|null $v, int $d = 0) => null === $v ? '—' : number_format($v, $d, ',', ' ');
        $all = [
            ['label' => 'Carrosserie et énergies', 'value' => $p['body'].' · '.implode(', ', $p['energies'])],
            ['label' => 'Prix', 'value' => 'dès '.$n($p['price']).' €'],
            ['label' => 'Puissance', 'value' => 'jusqu\'à '.$n($p['power']).' ch'],
            ['label' => 'Consommation', 'value' => 'dès '.$n($p['consumption'], 1).' '.$p['consumptionUnit']],
            ['label' => 'Poids à vide', 'value' => 'dès '.$n($p['weight']).' kg'],
            ['label' => 'La marque', 'value' => 'commence par « '.mb_substr($p['brand'], 0, 1).' »'],
        ];

        return \array_slice($all, 0, min(\count($all), $wrong + 1));
    }

    /**
     * Un essai comparé au modèle à trouver : même marque, même carrosserie, prix et puissance plus haut ou plus bas.
     *
     * @return array{label: string, brand: bool, body: bool, price: string, power: string}
     */
    public static function compare(Model $guess, Model $answer): array
    {
        $g = self::profile($guess);
        $a = self::profile($answer);
        $direction = fn (int $mine, int $target) => $mine === $target ? 'same' : ($target > $mine ? 'up' : 'down');

        return [
            'label' => $g['brand'].' '.$g['name'],
            'brand' => $g['brand'] === $a['brand'],
            'body' => $g['body'] === $a['body'],
            'price' => $direction($g['price'], $a['price']),
            'power' => $direction($g['power'], $a['power']),
        ];
    }

    /** @return list<Version> */
    private function all(): array
    {
        if (null === $this->all) {
            // Une requête avec modèles et marques, puis un ordre stable : celui des identifiants
            $this->all = $this->versions->search(null, null, null, null, null);
            usort($this->all, fn (Version $a, Version $b) => $a->getId() <=> $b->getId());
        }

        return $this->all;
    }

    private function random(string $game, \DateTimeInterface $day): Randomizer
    {
        return new Randomizer(new Mt19937(crc32('visavis|'.$game.'|'.$day->format('Y-m-d'))));
    }
}
