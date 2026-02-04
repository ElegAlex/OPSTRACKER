<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Formulaire de transfert de propriete d'une campagne.
 * US-210 / RG-111 : Definir le proprietaire d'une campagne
 *
 * @extends AbstractType<null>
 */
class TransfertProprietaireType extends AbstractType
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentProprietaire = $options['current_proprietaire'];

        // Récupérer gestionnaires et admins actifs, exclure le propriétaire actuel
        $users = $this->utilisateurRepository->findAllActifs();
        $choices = array_filter($users, function (Utilisateur $u) use ($currentProprietaire) {
            if ($currentProprietaire && $u->getId() === $currentProprietaire->getId()) {
                return false;
            }
            return in_array(Utilisateur::ROLE_GESTIONNAIRE, $u->getRoles(), true)
                || in_array(Utilisateur::ROLE_ADMIN, $u->getRoles(), true);
        });

        $builder
            ->add('nouveauProprietaire', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => fn(Utilisateur $u) => sprintf('%s %s (%s)', $u->getPrenom(), $u->getNom(), $u->getEmail()),
                'choices' => $choices,
                'label' => 'Nouveau proprietaire',
                'placeholder' => 'Selectionnez un gestionnaire...',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink/20 focus:border-ink outline-none',
                ],
                'constraints' => [
                    new NotNull(message: 'Veuillez selectionner un nouveau proprietaire.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'current_proprietaire' => null,
        ]);
        $resolver->setAllowedTypes('current_proprietaire', ['null', Utilisateur::class]);
    }
}
