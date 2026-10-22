<?php

namespace App\Controller;

use App\Compare\Comparator;
use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use App\Repository\VersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class SiteController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(VersionRepository $versions, Comparator $comparator): Response
    {
        $all = $versions->search(null, null, null, null, null);
        // Le duel de la vitrine, tiré du catalogue (absent si les fixtures ne sont pas chargées)
        $example = array_filter([
            $versions->search('clio 145', null, null, null, null)[0] ?? null,
            $versions->search('2008 hybrid', null, null, null, null)[0] ?? null,
        ]);

        return $this->render('site/home.html.twig', [
            'count' => \count($all),
            'example' => 2 === \count($example) ? array_values($example) : [],
            'exampleRows' => 2 === \count($example) ? $comparator->rows(array_values($example)) : [],
            // Pour le duel en direct : tout le catalogue, quelques Ko
            'cars' => array_map(fn (Version $v) => [
                'id' => $v->getId(),
                'brand' => $v->getModel()->getBrand()->getName(),
                'model' => $v->getModel()->getName(),
                'name' => $v->getName(),
                'unit' => $v->getEnergy()->consumptionUnit(),
                'price' => $v->getPrice(),
                'power' => $v->getPower(),
                'consumption' => $v->getConsumption(),
                'trunk' => $v->getTrunk(),
                'co2' => $v->getCo2(),
            ], $all),
            // Quatre modèles du catalogue en photo (assets/images, crédits dans docs/photo-credits.json)
            'gallery' => array_map(function (array $photo) use ($all) {
                $versionsOf = array_filter($all, fn (Version $v) => $v->getModel()->getName() === $photo['model']);

                return $photo + [
                    'count' => \count($versionsOf),
                    'from' => $versionsOf ? min(array_map(fn (Version $v) => $v->getPrice(), $versionsOf)) : null,
                ];
            }, [
                ['brand' => 'Renault', 'model' => 'Clio', 'image' => 'images/clio.jpg', 'credit' => 'Alexander Migl, CC BY-SA 4.0'],
                ['brand' => 'Peugeot', 'model' => '2008', 'image' => 'images/2008.jpg', 'credit' => 'Vauxford, CC BY-SA 4.0'],
                ['brand' => 'BMW', 'model' => 'i4', 'image' => 'images/i4.jpg', 'credit' => 'HJUdall, CC0'],
                ['brand' => 'Dacia', 'model' => 'Jogger', 'image' => 'images/jogger.jpg', 'credit' => 'Alexander Migl, CC BY-SA 4.0'],
            ]),
            // Le catalogue en miniature de « En trois gestes » : les hybrides à moins de 35 000 €
            'demo' => \array_slice($versions->search(null, Energy::Hybrid, 35000, null, null, 'price'), 0, 4),
        ]);
    }

    #[Route('/catalogue', name: 'app_catalog')]
    public function catalog(
        VersionRepository $versions,
        // En chaînes : un champ laissé vide (budget=&seats=) ou mal saisi est ignoré au lieu de donner une 404
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter] string $energy = '',
        #[MapQueryParameter] string $budget = '',
        #[MapQueryParameter] string $body = '',
        #[MapQueryParameter] string $seats = '',
        #[MapQueryParameter] string $sort = 'name',
        #[MapQueryParameter] string $v = '',
    ): Response {
        $filters = [
            'q' => trim($q),
            'energy' => Energy::tryFrom($energy),
            'budget' => max(0, (int) $budget) ?: null,
            'body' => Body::tryFrom($body),
            'seats' => max(0, (int) $seats) ?: null,
        ];

        return $this->render('site/catalog.html.twig', [
            'versions' => $versions->search(...array_values($filters), sort: $sort),
            'filters' => $filters,
            'sort' => isset(VersionRepository::SORTS[$sort]) ? $sort : 'name',
            'selected' => $versions->findInOrder(Comparator::parseIds($v)),
            'energies' => Energy::cases(),
            'bodies' => Body::cases(),
        ]);
    }

    #[Route('/version/{id}', name: 'app_version', requirements: ['id' => '\d+'])]
    public function version(Version $version, VersionRepository $versions): Response
    {
        // Ses rivales : même carrosserie, autre modèle, au prix le plus proche
        $rivals = array_filter(
            $versions->search(null, null, null, $version->getModel()->getBody(), null),
            fn (Version $v) => $v->getModel() !== $version->getModel(),
        );
        usort($rivals, fn (Version $a, Version $b) => abs($a->getPrice() - $version->getPrice()) <=> abs($b->getPrice() - $version->getPrice()));

        return $this->render('site/version.html.twig', ['version' => $version, 'rivals' => \array_slice($rivals, 0, 4)]);
    }

    #[Route('/comparer', name: 'app_compare')]
    public function compare(Request $request, VersionRepository $versions, Comparator $comparator): Response
    {
        // Le formulaire du catalogue envoie ids[] : on redirige vers l'adresse partageable ?v=12,34
        if ($request->query->has('ids')) {
            $ids = Comparator::parseIds(implode(',', $request->query->all('ids')));

            return $this->redirectToRoute('app_compare', ['v' => implode(',', $ids)]);
        }

        $selected = $versions->findInOrder(Comparator::parseIds($request->query->getString('v')));

        return $this->render('site/compare.html.twig', [
            'versions' => $selected,
            'rows' => $comparator->rows($selected),
            'ids' => array_map(fn (Version $v) => $v->getId(), $selected),
        ]);
    }
}
