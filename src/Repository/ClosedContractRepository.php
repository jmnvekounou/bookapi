<?php

namespace App\Repository;

use App\Entity\ClosedContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ClosedContract|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClosedContract|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClosedContract[]    findAll()
 * @method ClosedContract[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClosedContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClosedContract::class);
    }

    // /**
    //  * @return ClosedContract[] Returns an array of ClosedContract objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ClosedContract
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
