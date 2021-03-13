<?php

namespace App\Repository;

use App\Entity\AbsenceStudent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AbsenceStudent|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbsenceStudent|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbsenceStudent[]    findAll()
 * @method AbsenceStudent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbsenceStudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceStudent::class);
    }

    // /**
    //  * @return AbsenceStudent[] Returns an array of AbsenceStudent objects
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
    public function findOneBySomeField($value): ?AbsenceStudent
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
