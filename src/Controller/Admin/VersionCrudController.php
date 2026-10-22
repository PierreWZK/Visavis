<?php

namespace App\Controller\Admin;

use App\Entity\Version;
use App\Enum\Energy;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Version> */
final class VersionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Version::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Version')
            ->setEntityLabelInPlural('Versions')
            ->setDefaultSort(['model' => 'ASC', 'price' => 'ASC'])
            ->setSearchFields(['name', 'model.name', 'model.brand.name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('model', 'Modèle');
        yield TextField::new('name', 'Version');
        yield IntegerField::new('year', 'Année');
        yield ChoiceField::new('energy', 'Énergie')
            ->setChoices(array_combine(array_map(fn (Energy $e) => $e->label(), Energy::cases()), Energy::cases()));
        yield IntegerField::new('price', 'Prix (€)');
        yield IntegerField::new('power', 'Puissance (ch)');
        yield NumberField::new('consumption', 'Conso. (L ou kWh/100 km)')->setNumDecimals(1);
        yield IntegerField::new('trunk', 'Coffre (L)');
        yield IntegerField::new('co2', 'CO₂ (g/km)');
    }
}
