<?php

namespace App\Repository;

use App\Entity\ChecklistInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChecklistInstance>
 */
class ChecklistInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChecklistInstance::class);
    }

    /**
     * Trouve l'instance pour une operation
     */
    public function findByOperation(int $operationId): ?ChecklistInstance
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.operation = :operation')
            ->setParameter('operation', $operationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les instances incompletes d'un template
     *
     * @return ChecklistInstance[]
     */
    public function findIncompletesByTemplate(int $templateId): array
    {
        // On recupere toutes les instances du template
        // et on filtre en PHP car la progression est en JSON
        $instances = $this->createQueryBuilder('c')
            ->andWhere('c.template = :template')
            ->setParameter('template', $templateId)
            ->getQuery()
            ->getResult();

        return array_filter($instances, fn (ChecklistInstance $i) => !$i->isComplete());
    }
}
