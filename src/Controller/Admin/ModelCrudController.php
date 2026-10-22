<?php

namespace App\Controller\Admin;

use App\Entity\Model;
use App\Enum\Body;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Model> */
final class ModelCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Model::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Modèle')
            ->setEntityLabelInPlural('Modèles')
            ->setDefaultSort(['brand' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'brand.name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('brand', 'Marque');
        yield TextField::new('name', 'Nom');
        yield ChoiceField::new('body', 'Carrosserie')
            ->setChoices(array_combine(array_map(fn (Body $b) => $b->label(), Body::cases()), Body::cases()));
        yield IntegerField::new('seats', 'Places');
        yield AssociationField::new('versions', 'Versions')->onlyOnIndex();
    }
}
