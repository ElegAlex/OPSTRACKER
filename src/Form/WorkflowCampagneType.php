<?php

namespace App\Form;

use App\Entity\Campagne;
use App\Entity\ChecklistTemplate;
use App\Entity\TypeOperation;
use App\Repository\ChecklistTemplateRepository;
use App\Repository\TypeOperationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire de configuration workflow d'une campagne.
 * Permet de modifier le TypeOperation et ChecklistTemplate apres creation.
 *
 * @extends AbstractType<Campagne>
 */
class WorkflowCampagneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeOperation', EntityType::class, [
                'class' => TypeOperation::class,
                'choice_label' => 'nom',
                'label' => 'Type d\'operation',
                'placeholder' => 'Aucun type selectionne',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'query_builder' => fn(TypeOperationRepository $repository) => $repository->createQueryBuilder('t')
                    ->where('t.actif = :actif')
                    ->setParameter('actif', true)
                    ->orderBy('t.nom', 'ASC'),
            ])
            ->add('checklistTemplate', EntityType::class, [
                'class' => ChecklistTemplate::class,
                'choice_label' => function (ChecklistTemplate $template) {
                    return sprintf('%s (v%d - %d etapes)',
                        $template->getNom(),
                        $template->getVersion(),
                        $template->getNombreEtapes()
                    );
                },
                'label' => 'Template de checklist',
                'placeholder' => 'Aucun template selectionne',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'query_builder' => fn(ChecklistTemplateRepository $repository) => $repository->createQueryBuilder('t')
                    ->where('t.actif = :actif')
                    ->setParameter('actif', true)
                    ->orderBy('t.nom', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campagne::class,
        ]);
    }
}
