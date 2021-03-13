<?php

namespace App\Repository;

use App\Entity\AbsenceReason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AbsenceReason|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbsenceReason|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbsenceReason[]    findAll()
 * @method AbsenceReason[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbsenceReasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceReason::class);
    }

    // /**
    //  * @return AbsenceReason[] Returns an array of AbsenceReason objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AbsenceReason
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
