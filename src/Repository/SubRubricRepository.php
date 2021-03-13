<?php

namespace App\Repository;

use App\Entity\SubRubric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SubRubric|null find($id, $lockMode = null, $lockVersion = null)
 * @method SubRubric|null findOneBy(array $criteria, array $orderBy = null)
 * @method SubRubric[]    findAll()
 * @method SubRubric[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SubRubricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubRubric::class);
    }

    // /**
    //  * @return SubRubric[] Returns an array of SubRubric objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SubRubric
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
