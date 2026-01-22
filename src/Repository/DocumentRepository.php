<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Trouve les documents d'une campagne
     *
     * @return Document[]
     */
    public function findByCampagne(int $campagneId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents par type dans une campagne
     *
     * @return Document[]
     */
    public function findByCampagneAndType(int $campagneId, string $type): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.campagne = :campagne')
            ->andWhere('d.type = :type')
            ->setParameter('campagne', $campagneId)
            ->setParameter('type', $type)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les documents par type pour une campagne
     *
     * @return array<string, int>
     */
    public function countByTypeForCampagne(int $campagneId): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.type, COUNT(d.id) as total')
            ->andWhere('d.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->groupBy('d.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach (Document::TYPES as $type => $label) {
            $counts[$type] = 0;
        }
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Compte le nombre total de documents pour une campagne
     */
    public function countByCampagne(int $campagneId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche de documents par nom
     *
     * @return Document[]
     */
    public function searchByNom(int $campagneId, string $query): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.campagne = :campagne')
            ->andWhere('LOWER(d.nomOriginal) LIKE LOWER(:query)')
            ->setParameter('campagne', $campagneId)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('d.nomOriginal', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule la taille totale des documents d'une campagne (en octets)
     */
    public function getTailleTotaleByCampagne(int $campagneId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('SUM(d.taille)')
            ->andWhere('d.campagne = :campagne')
            ->setParameter('campagne', $campagneId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
