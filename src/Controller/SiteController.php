<?php

namespace App\Controller;

use App\Compare\Comparator;
use App\Entity\Version;
use App\Enum\Body;
use App\Enum\Energy;
use App\Enum\Gearbox;
use App\Repository\VersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class SiteController extends AbstractController
{
    /** Quatre modèles du catalogue en photo (assets/images, crédits dans docs/photo-credits.json) */
    private const array GALLERY = [
        ['brand' => 'Renault', 'model' => 'Clio', 'image' => 'images/clio.jpg', 'credit' => 'Alexander Migl, CC BY-SA 4.0'],
        ['brand' => 'Peugeot', 'model' => '2008', 'image' => 'images/2008.jpg', 'credit' => 'Vauxford, CC BY-SA 4.0'],
        ['brand' => 'BMW', 'model' => 'i4', 'image' => 'images/i4.jpg', 'credit' => 'HJUdall, CC0'],
        ['brand' => 'Dacia', 'model' => 'Jogger', 'image' => 'images/jogger.jpg', 'credit' => 'Alexander Migl, CC BY-SA 4.0'],
    ];

    #[Route('/', name: 'app_home')]
    public function home(VersionRepository $versions, Comparator $comparator): Response
    {
        // Le duel de la vitrine : une Clio hybride contre un 2008 essence, aux chiffres complets (absent si le catalogue est vide)
        $complete = fn (array $found) => array_find($found, fn (Version $v) => null !== $v->getWeight() && null !== $v->getConsumption());
        $example = array_values(array_filter([
            $complete($versions->search('Renault Clio', Energy::Hybrid, null, null, null, 'price')),
            $complete($versions->search('Peugeot 2008', Energy::Petrol, null, null, null, 'price')),
        ]));
        $example = 2 === \count($example) ? $example : [];

        return $this->render('site/home.html.twig', [
            'count' => $versions->count(),
            'example' => $example,
            // Les cinq lignes que le duel en direct sait redessiner (voir assets/app.js)
            'exampleRows' => $example ? array_values(array_filter($comparator->rows($example), fn (array $row) => \in_array($row['key'], ['price', 'power', 'consumption', 'co2', 'weight'], true))) : [],
            'gallery' => array_map(function (array $photo) use ($versions) {
                $prices = array_map(
                    fn (Version $v) => $v->getPrice(),
                    array_filter($versions->search($photo['brand'].' '.$photo['model'], null, null, null, null),
                        fn (Version $v) => 0 === strcasecmp($v->getModel()->getName(), $photo['model'])),
                );

                return $photo + ['count' => \count($prices), 'from' => $prices ? min($prices) : null];
            }, self::GALLERY),
            // Le catalogue en miniature de « En trois gestes » : les hybrides à moins de 35 000 €
            'demo' => \array_slice($versions->search(null, Energy::Hybrid, 35000, null, null, 'price'), 0, 4),
        ]);
    }

    #[Route('/catalogue', name: 'app_catalog')]
    public function catalog(
        VersionRepository $versions,
        // En chaînes : un champ laissé vide (budget=&body=) ou mal saisi est ignoré au lieu de donner une 404
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter] string $energy = '',
        #[MapQueryParameter] string $budget = '',
        #[MapQueryParameter] string $body = '',
        #[MapQueryParameter] string $gearbox = '',
        #[MapQueryParameter] string $sort = 'name',
        #[MapQueryParameter] string $page = '1',
        #[MapQueryParameter] string $v = '',
    ): Response {
        $filters = [
            'q' => trim($q),
            'energy' => Energy::tryFrom($energy),
            'budget' => max(0, (int) $budget) ?: null,
            'body' => Body::tryFrom($body),
            'gearbox' => Gearbox::tryFrom($gearbox),
        ];
        $sort = isset(VersionRepository::SORTS[$sort]) ? $sort : 'name';
        $page = max(1, (int) $page);
        $result = $versions->page($page, ...[...array_values($filters), $sort]);
        $pages = max(1, (int) ceil($result['total'] / VersionRepository::PER_PAGE));

        return $this->render('site/catalog.html.twig', [
            'versions' => $result['versions'],
            'total' => $result['total'],
            'page' => min($page, $pages),
            'pages' => $pages,
            'filters' => $filters,
            'sort' => $sort,
            'selected' => $versions->findInOrder(Comparator::parseIds($v)),
            'energies' => Energy::cases(),
            'bodies' => Body::cases(),
            'gearboxes' => Gearbox::cases(),
        ]);
    }

    /** Recherche pour les champs à suggestions (duel de l'accueil) : les 8 premières versions */
    #[Route('/voitures.json', name: 'app_cars_json')]
    public function cars(VersionRepository $versions, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        $found = mb_strlen(trim($q)) >= 2 ? $versions->page(1, mb_substr($q, 0, 60), null, null, null, null)['versions'] : [];

        return $this->json(array_map(fn (Version $v) => [
            'id' => $v->getId(),
            'label' => (string) $v,
            'brand' => $v->getModel()->getBrand()->getName(),
            'model' => $v->getModel()->getName(),
            'name' => $v->getName(),
            'energy' => $v->getEnergy()->label(),
            'unit' => $v->getEnergy()->consumptionUnit(),
            'price' => $v->getPrice(),
            'power' => $v->getPower(),
            'consumption' => $v->getConsumption(),
            'co2' => $v->getCo2(),
            'weight' => $v->getWeight(),
        ], \array_slice($found, 0, 8)));
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
