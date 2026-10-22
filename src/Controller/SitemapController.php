<?php

namespace App\Controller;

use App\Repository\VersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Plan du site pour les moteurs : les pages fixes et la fiche de chaque version.
 * Généré à la demande : les identifiants changent avec les imports ADEME, un fichier statique serait vite faux.
 */
final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function index(VersionRepository $versions): Response
    {
        $urls = [
            $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('app_catalog', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('app_game', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        foreach ($versions->findBy([], ['id' => 'ASC']) as $version) {
            $urls[] = $this->generateUrl('app_version', ['id' => $version->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $response = $this->render('sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic()->setMaxAge(3600);

        return $response;
    }
}
