<?php

namespace App\Catalog;

use App\Entity\Brand;
use App\Entity\Model;
use App\Entity\Version;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importe le jeu « ADEME - Car Labelling » dans le catalogue : crée, met à jour, retire les versions disparues.
 * Les versions du catalogue de démonstration ne sont jamais touchées.
 */
final class AdemeImporter
{
    public const string URL = 'https://data.ademe.fr/data-fair/api/v1/datasets/ademe-car-labelling/lines?size=1000';

    public function __construct(private readonly EntityManagerInterface $em, private readonly ImageFinder $images)
    {
    }

    /**
     * Toutes les lignes de l'API, page après page.
     *
     * @return list<array<string, mixed>>
     */
    public function fetch(): array
    {
        $rows = [];
        $url = self::URL;
        $context = stream_context_create(['http' => ['timeout' => 60, 'header' => "Accept: application/json\r\n"]]);
        for ($page = 0; $url && $page < 50; ++$page) {
            $body = @file_get_contents($url, false, $context);
            if (false === $body) {
                throw new \RuntimeException('API de l\'ADEME injoignable : '.$url);
            }
            $data = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $rows = [...$rows, ...$data['results']];
            $url = $data['results'] ? ($data['next'] ?? null) : null;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array{created: int, updated: int, removed: int, ignored: int}
     */
    public function import(array $rows): array
    {
        // Une version par empreinte : la source répète une ligne par variante de poids ou d'options
        $mapped = [];
        $ignored = 0;
        foreach ($rows as $row) {
            $version = AdemeRow::map($row);
            null === $version ? ++$ignored : $mapped[$version['key']] ??= $version;
        }
        if (!$mapped) {
            throw new \RuntimeException('Aucune version exploitable dans la réponse de l\'ADEME : import annulé.');
        }

        $brands = [];
        foreach ($this->em->getRepository(Brand::class)->findAll() as $brand) {
            $brands[mb_strtolower($brand->getName())] = $brand;
        }
        $models = [];
        foreach ($this->em->getRepository(Model::class)->findAll() as $model) {
            $models[mb_strtolower($model->getBrand()->getName().'|'.$model->getName())] = $model;
        }
        $existing = [];
        foreach ($this->em->getRepository(Version::class)->findBy(['source' => 'ademe']) as $version) {
            $existing[$version->getSourceKey()] = $version;
        }

        // La carrosserie d'un nouveau modèle : celle de la plupart de ses versions
        $bodies = [];
        foreach ($mapped as $v) {
            $bodies[mb_strtolower($v['brand'].'|'.$v['model'])][$v['body']->value][] = $v['body'];
        }

        $created = $updated = 0;
        foreach ($mapped as $key => $v) {
            $brand = $brands[mb_strtolower($v['brand'])] ??= $this->persist(new Brand($v['brand']));
            $modelKey = mb_strtolower($v['brand'].'|'.$v['model']);
            if (!isset($models[$modelKey])) {
                $counts = $bodies[$modelKey];
                uasort($counts, fn (array $a, array $b) => \count($b) <=> \count($a));
                $models[$modelKey] = $this->persist((new Model())->setBrand($brand)->setName($v['model'])->setBody(reset($counts)[0]));
            }

            $version = $existing[$key] ?? null;
            if ($version) {
                ++$updated;
                unset($existing[$key]);
            } else {
                ++$created;
                $version = $this->persist((new Version())->setSource('ademe')->setSourceKey($key));
            }
            $version->setModel($models[$modelKey])->setName($v['name'])->setEnergy($v['energy'])->setGearbox($v['gearbox'])
                ->setPrice($v['price'])->setPower($v['power'])->setConsumption($v['consumption'])->setCo2($v['co2'])
                ->setWeight($v['weight'])->setElectricRange($v['electricRange'])->setYear(null)->setTrunk(null);
        }

        // Disparues de la source : retirées, puis les modèles et marques restés vides
        foreach ($existing as $version) {
            $version->getModel()->getVersions()->removeElement($version);
            $this->em->remove($version);
        }
        $this->em->flush();
        $this->em->getConnection()->executeStatement('DELETE FROM car_model WHERE id NOT IN (SELECT DISTINCT model_id FROM version)');
        $this->em->getConnection()->executeStatement('DELETE FROM brand WHERE id NOT IN (SELECT DISTINCT brand_id FROM car_model)');

        return ['created' => $created, 'updated' => $updated, 'removed' => \count($existing), 'ignored' => $ignored];
    }

    /**
     * Une photo pour chaque modèle qui n'en a jamais cherché (démonstration comprise). Une recherche par modèle, une seule fois.
     *
     * @return array{found: int, missing: int}
     */
    public function findImages(): array
    {
        $found = $missing = 0;
        foreach ($this->em->getRepository(Model::class)->findBy(['imageUrl' => null]) as $model) {
            $image = $this->images->find($model->getBrand()->getName(), $model->getName());
            $image ? ++$found : ++$missing;
            $model->setImageUrl($image['url'] ?? '')->setImagePage($image['page'] ?? null)->setImageCredit($image['credit'] ?? null);
            $this->em->flush();
        }

        return ['found' => $found, 'missing' => $missing];
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->em->persist($entity);

        return $entity;
    }
}
