<?php

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire d'upload de document (T-1006, RG-050)
 */
class DocumentUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                    'accept' => '.pdf,.docx,.doc,.ps1,.bat,.zip,.exe,.txt,.md,.markdown',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez selectionner un fichier.']),
                    new File([
                        'maxSize' => '50M',
                        'maxSizeMessage' => 'RG-050 : Le fichier est trop volumineux ({{ size }} {{ suffix }}). Taille max : {{ limit }} {{ suffix }}.',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/msword',
                            'text/plain',
                            'text/markdown',
                            'text/x-markdown',
                            'application/zip',
                            'application/x-zip-compressed',
                            'application/x-msdownload',
                            'application/octet-stream',
                        ],
                        'mimeTypesMessage' => 'RG-050 : Type de fichier non autorise. Formats acceptes : PDF, DOCX, DOC, PS1, BAT, ZIP, EXE, TXT, MD.',
                    ]),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => array_flip(Document::TYPES),
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary bg-white',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border-2 border-ink focus:outline-none focus:ring-2 focus:ring-primary',
                    'rows' => 3,
                    'placeholder' => 'Description du document...',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
