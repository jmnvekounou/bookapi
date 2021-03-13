<?php

namespace App\Repository;

use App\Entity\EmployeeProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EmployeeProfile|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmployeeProfile|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmployeeProfile[]    findAll()
 * @method EmployeeProfile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeeProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeeProfile::class);
    }

    // /**
    //  * @return EmployeeProfile[] Returns an array of EmployeeProfile objects
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
    public function findOneBySomeField($value): ?EmployeeProfile
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
