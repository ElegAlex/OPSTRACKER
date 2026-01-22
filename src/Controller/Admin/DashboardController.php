<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routes: [
    'index' => ['routePath' => '/admin', 'routeName' => 'admin'],
])]
class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        // Rediriger vers la liste des utilisateurs par défaut
        return $this->redirect($adminUrlGenerator->setController(UtilisateurCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('OpsTracker Admin')
            ->setFaviconPath('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect fill="%230a0a0a" width="128" height="128"/></svg>')
            ->setTranslationDomain('admin')
            ->setLocales(['fr'])
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', Utilisateur::class);

        yield MenuItem::section('');
        yield MenuItem::linkToRoute('Retour a OpsTracker', 'fa fa-arrow-left', 'app_home');
        yield MenuItem::linkToLogout('Deconnexion', 'fa fa-sign-out');
    }
}
