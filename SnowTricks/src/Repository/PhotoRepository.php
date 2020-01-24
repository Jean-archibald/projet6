<?php

namespace App\Repository;

use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Photo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Photo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Photo[]    findAll()
 * @method Photo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * @return Photo[]
     */
    public function selectFeaturedPhoto(\DateTimeInterface $createdAt): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT p
            FROM App\Entity\Photo p
            WHERE p.createdAt <= :createdAt
            ORDER BY p.createdAt ASC'
        )->setParameter('createdAt', $createdAt);

        // returns an array of Product objects
        return $query->getResult();
    }

    public function uploadAndAddPhoto($photo,$filename,$trick,$manager)
    {
        $photo->setCreatedAt(new \DateTime())
                          ->setPathUrl('uploads/'.$filename)
                          ->setTrick($trick);
                      ;
                    $manager->persist($photo);
                    $manager->flush();
    }

    public function deletePhoto($image,$manager)
    {
        $photo = $this->findOneBy(['pathUrl' => $image]);
        unlink($image);
        $manager->remove($photo);
        $manager->flush();
    }

    // /**
    //  * @return Photo[] Returns an array of Photo objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Photo
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
