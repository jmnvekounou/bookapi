<?php

namespace App\Repository;

use App\Entity\Advisors;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Advisors|null find($id, $lockMode = null, $lockVersion = null)
 * @method Advisors|null findOneBy(array $criteria, array $orderBy = null)
 * @method Advisors[]    findAll()
 * @method Advisors[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdvisorsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advisors::class);
    }

    // /**
    //  * @return Advisors[] Returns an array of Advisors objects
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
    public function findOneBySomeField($value): ?Advisors
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
