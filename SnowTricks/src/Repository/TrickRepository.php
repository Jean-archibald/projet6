<?php

namespace App\Repository;

use App\Entity\Trick;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Trick|null find($id, $lockMode = null, $lockVersion = null)
 * @method Trick|null findOneBy(array $criteria, array $orderBy = null)
 * @method Trick[]    findAll()
 * @method Trick[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrickRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trick::class);
    }

    public function findSomeTrickOrderedByNewest($offset,$limit)
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }


    public function editTrick($trick,$manager,$user,$slug)
    {
        $trick->setCreatedAt(new \DateTime())
            ->setUser($user)
            ->setSlug($slug)
            ;
        $manager->persist($trick);
        $manager->flush();
    }

    public function setNewFeaturedPhoto($trick,$manager,$imageToFeatured)
    {
        $trick->setFeaturedPhoto($imageToFeatured);
        $manager->persist($trick);
        $manager->flush();
    }

    public function addVideoToCollection($trick,$manager,$video)
    {
        $trick->addVideo($video);
        $manager->persist($trick);
        $manager->persist($video);
        $manager->flush();
    }

    public function setDefaultImageFeatured($trick,$manager)
    {
        $trick->setFeaturedPhoto('uploads/homeImage.jpg');
        $manager->persist($trick);
        $manager->flush();
    }

    public function modifyTrick($trick,$manager)
    {
        $trick->setModifiedAt(new \DateTime());
        $manager->persist($trick);
        $manager->flush();
    }

    public function deleteTrick($trick,$manager)
    {
        $photos = $trick->getPhotos();
        $photoCollection = $photos->toArray();

        foreach ($photoCollection as $photo)
        {
            unlink($photo->getPathUrl());
        }
  
        $manager->remove($trick);
        $manager->flush();
    }





    // /**
    //  * @return Trick[] Returns an array of Trick objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Trick
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */


}
