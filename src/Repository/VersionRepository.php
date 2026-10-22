<?php

namespace App\Repository;

use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Version> */
class VersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Version::class);
    }

    /** Tris proposés au catalogue : clé => [colonne, sens] */
    public const array SORTS = [
        'name' => ['b.name', 'ASC'],
        'price' => ['v.price', 'ASC'],
        'power' => ['v.power', 'DESC'],
        'trunk' => ['v.trunk', 'DESC'],
    ];

    /** @return Version[] */
    public function search(?string $query, ?Energy $energy, ?int $maxPrice, ?Body $body, ?int $minSeats, string $sort = 'name'): array
    {
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['name'];
        $qb = $this->createQueryBuilder('v')
            ->addSelect('m', 'b')
            ->join('v.model', 'm')
            ->join('m.brand', 'b')
            ->orderBy($column, $direction)->addOrderBy('b.name')->addOrderBy('m.name')->addOrderBy('v.price');

        if ($query) {
            // Chaque mot doit apparaître dans la marque, le modèle ou la version : « clio hybride », « tesla 3 »
            foreach (preg_split('/\s+/', trim($query)) as $i => $word) {
                $qb->andWhere("CONCAT(b.name, ' ', m.name, ' ', v.name) LIKE :w$i")
                    ->setParameter("w$i", '%'.addcslashes($word, '%_').'%');
            }
        }
        if ($energy) {
            $qb->andWhere('v.energy = :energy')->setParameter('energy', $energy);
        }
        if ($maxPrice) {
            $qb->andWhere('v.price <= :maxPrice')->setParameter('maxPrice', $maxPrice);
        }
        if ($body) {
            $qb->andWhere('m.body = :body')->setParameter('body', $body);
        }
        if ($minSeats) {
            $qb->andWhere('m.seats >= :seats')->setParameter('seats', $minSeats);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Les versions demandées, dans l'ordre de l'adresse.
     *
     * @param int[] $ids
     *
     * @return Version[]
     */
    public function findInOrder(array $ids): array
    {
        $found = [];
        foreach ($this->findBy(['id' => $ids]) as $version) {
            $found[$version->getId()] = $version;
        }

        return array_values(array_filter(array_map(fn (int $id) => $found[$id] ?? null, $ids)));
    }
}
