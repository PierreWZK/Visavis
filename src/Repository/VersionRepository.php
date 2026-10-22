<?php

namespace App\Repository;

use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use App\Enum\Gearbox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
        'co2' => ['v.co2', 'ASC'],
    ];

    public const int PER_PAGE = 50;

    /** @return Version[] */
    public function search(?string $query, ?Energy $energy, ?int $maxPrice, ?Body $body, ?Gearbox $gearbox, string $sort = 'name'): array
    {
        return $this->filtered($query, $energy, $maxPrice, $body, $gearbox, $sort)->getQuery()->getResult();
    }

    /**
     * Une page du catalogue filtré, et le nombre total de versions qui correspondent.
     *
     * @return array{versions: Version[], total: int}
     */
    public function page(int $page, ?string $query, ?Energy $energy, ?int $maxPrice, ?Body $body, ?Gearbox $gearbox, string $sort = 'name'): array
    {
        $paginator = new Paginator($this->filtered($query, $energy, $maxPrice, $body, $gearbox, $sort)
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE));

        return ['versions' => iterator_to_array($paginator), 'total' => \count($paginator)];
    }

    private function filtered(?string $query, ?Energy $energy, ?int $maxPrice, ?Body $body, ?Gearbox $gearbox, string $sort): QueryBuilder
    {
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['name'];
        $qb = $this->createQueryBuilder('v')
            ->addSelect('m', 'b')
            ->join('v.model', 'm')
            ->join('m.brand', 'b')
            // Les valeurs inconnues en dernier, quel que soit le sens
            ->addSelect("CASE WHEN $column IS NULL THEN 1 ELSE 0 END AS HIDDEN unknown")
            ->orderBy('unknown')->addOrderBy($column, $direction)
            ->addOrderBy('b.name')->addOrderBy('m.name')->addOrderBy('v.price')->addOrderBy('v.id');

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
        if ($gearbox) {
            $qb->andWhere('v.gearbox = :gearbox')->setParameter('gearbox', $gearbox);
        }

        return $qb;
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
