<?php

namespace App\Form;

use App\Entity\Campagne;
use App\Entity\Operation;
use App\Entity\Segment;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\CampagneChampService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire ajout/edition operation.
 *
 * RG-014 : Statut initial = "A planifier"
 * RG-015 : TOUTES les donnees passent par donneesPersonnalisees (JSONB)
 * RG-018 : 1 technicien assigne maximum
 *
 * Le formulaire est 100% dynamique : tous les champs sont definis
 * par les CampagneChamp de la campagne.
 */
class OperationType extends AbstractType
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Campagne|null $campagne */
        $campagne = $options['campagne'];

        // Recuperer les donnees existantes pour pre-remplir les champs
        /** @var Operation|null $operation */
        $operation = $builder->getData();
        $donneesPersonnalisees = $operation?->getDonneesPersonnalisees() ?? [];

        // Ajouter les champs dynamiques de la campagne (CampagneChamp)
        // C'est la source principale des donnees de l'operation
        if ($campagne && $campagne->getChamps()->count() > 0) {
            foreach ($campagne->getChamps() as $champ) {
                $champNom = $champ->getNom();
                $fieldName = CampagneChampService::normalizeFieldName($champNom);

                $builder->add($fieldName, TextType::class, [
                    'label' => $champNom,
                    'required' => false,
                    'mapped' => false,
                    'data' => $donneesPersonnalisees[$champNom] ?? null,
                    'attr' => [
                        'placeholder' => 'Valeur pour ' . $champNom,
                        'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                        'data-champ-nom' => $champNom,
                    ],
                    'label_attr' => [
                        'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                    ],
                ]);
            }
        }

        // Champs systeme (mappes sur l'entite Operation)
        $builder
            ->add('datePlanifiee', DateType::class, [
                'label' => 'Date planifiee',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ])
            ->add('technicienAssigne', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $utilisateur) {
                    return $utilisateur->getPrenom() . ' ' . $utilisateur->getNom();
                },
                'label' => 'Technicien assigne',
                'placeholder' => 'Non assigne',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
                'choices' => $this->utilisateurRepository->findTechniciensActifs(),
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Notes ou commentaires...',
                    'rows' => 3,
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white resize-none',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
            ]);

        // Ajouter le champ segment si la campagne a des segments
        if ($campagne && $campagne->getSegments()->count() > 0) {
            $builder->add('segment', EntityType::class, [
                'class' => Segment::class,
                'choice_label' => 'nom',
                'label' => 'Segment',
                'placeholder' => 'Aucun segment',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink focus:outline-none bg-white',
                ],
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-ink uppercase tracking-wider mb-2',
                ],
                'choices' => $campagne->getSegments(),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Operation::class,
            'campagne' => null,
        ]);

        $resolver->setAllowedTypes('campagne', ['null', Campagne::class]);
    }
}
