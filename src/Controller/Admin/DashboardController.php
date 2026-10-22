<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $urls)
    {
    }

    public function index(): Response
    {
        return $this->redirect($this->urls->setController(VersionCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('<strong>Vis-à-vis</strong> · catalogue');
    }

    /** Authentification HTTP basique : le navigateur garde les identifiants, il n'y a pas de déconnexion */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)->disableLogoutLink();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(VersionCrudController::class, 'Versions', 'fa fa-car');
        yield MenuItem::linkTo(ModelCrudController::class, 'Modèles', 'fa fa-layer-group');
        yield MenuItem::linkTo(BrandCrudController::class, 'Marques', 'fa fa-tag');

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Voir le site', 'fa fa-arrow-up-right-from-square', 'app_home')
            ->setLinkTarget('_blank');
    }
}
