<?php

namespace App\Repository;

use App\Entity\EmployeeStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EmployeeStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmployeeStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmployeeStatus[]    findAll()
 * @method EmployeeStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeeStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeeStatus::class);
    }

    // /**
    //  * @return EmployeeStatus[] Returns an array of EmployeeStatus objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?EmployeeStatus
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
