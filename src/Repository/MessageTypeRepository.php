<?php

namespace App\Repository;

use App\Entity\MessageType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method MessageType|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageType|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageType[]    findAll()
 * @method MessageType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageType::class);
    }

    // /**
    //  * @return MessageType[] Returns an array of MessageType objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MessageType
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
