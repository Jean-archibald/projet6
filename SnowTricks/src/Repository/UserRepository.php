<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function register($form,$user,$manager,$encoder,$uploads_directory)
    {  
        $hash = $encoder->encodePassword($user, $user->getPassword());
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/','-_'), '=');
        $user->setApitoken( $token);
        $user->SetConfirmed(1);
        $user->setPassword($hash);
    
        $avatarUpload = $form->get('avatar')->getData();
        if($avatarUpload != null)
        {
            $avatar = md5(uniqid()) . '.' . $avatarUpload->guessExtension();   
            $avatarUpload->move(
                $uploads_directory,
                $avatar
        );
        $user->setAvatar('uploads/'.$avatar);
        }
        $manager->persist($user);
        $manager->flush();
    }

    public function sendMailConfirmation($user,$manager,$notification,$mailer)
    {
        $user->SetConfirmed(0);
        $manager->persist($user);
        $manager->flush();
        $notification->notify($user,$mailer);
    }

    public function confirmedMailUser($user,$manager)
    {
        $user->SetConfirmed(1);
        $manager->persist($user);
        $manager->flush();
    }

    public function forgottenMailSend($user,$manager,$notification,$mailer)
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/','-_'), '=');
        $user->setResetToken($token);
        $manager->persist($user);
        $manager->flush();
        $notification->forgotNotify($user,$mailer);
    }

    
}
