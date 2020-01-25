<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Notification\ContactNotification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/inscription",name="app_registration")
     */
    public function registration(UserRepository $userRepository,MailerInterface $mailer,Request $request, EntityManagerInterface $manager, UserPasswordEncoderInterface $encoder, ContactNotification $notification)
    {
       
        $user = new User();

        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        $uploads_directory = $this->getParameter('uploads_directory');

        //create a new User and send a confirmation mail
        if($form->isSubmitted() && $form->isValid()) { 
            $username = $user->getUsername(); 
            $username = htmlspecialchars($username); 
            $userRepository->register($form,$user,$manager,$encoder,$uploads_directory);
            $userRepository->sendMailConfirmation($user,$notification,$mailer);
            return $this->redirectToRoute('home');
        }

        return $this->render('security/registration.html.twig', [
        'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {

    }

    /**
     * @Route("/confirmed/{Apitoken}", name="app_confirmed_mail")
     */
    public function confirmedMail(UserRepository $userRepository, string $Apitoken, EntityManagerInterface $manager)
    {
        $repo = $this->getDoctrine()->getRepository(User::class);
        $user = $repo->findOneBy(['apiToken' => $Apitoken]);
        if (isset($user)) {
            $userRepository->confirmedMailUser($user,$manager);
            return $this->redirectToRoute('app_login');
        }
        else {
            return $this->redirectToRoute('home');
        }
    }   


      /**
     * @Route("/passwordForgotten", name="app_forgotten_password")
     */
    public function forgottenPassword(UserRepository $userRepository, Request $request,MailerInterface $mailer, EntityManagerInterface $manager,ContactNotification $notification): Response
    {
        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
            $repo = $this->getDoctrine()->getRepository(User::class);
            $user = $repo->findOneBy(['username' => $username]);
 
            if (isset($user)) {   
                //if User exist, the app send him a email with a unique token
                $userRepository->forgottenMailSend($user,$manager,$notification,$mailer);
            }
            else {
                return $this->redirectToRoute('app_forgotten_password');
            }
  
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot.html.twig');
    }

      /**
     * @Route("/changedPassword/{ResetToken}", name="app_reset_password")
     */
    public function changedPassword(String $ResetToken, Request $request, EntityManagerInterface $manager,UserPasswordEncoderInterface $encoder)
    { 
        $repo = $this->getDoctrine()->getRepository(User::class);

        $user = $repo->findOneBy(['resetToken' => $ResetToken]);
    
        if(isset($user)) {
            if ($request->isMethod('POST')) {
                if (isset($user)) {
                    $hash = $encoder->encodePassword($user, $request->request->get('password'));
                    $user->setPassword($hash);
                    $manager->persist($user);
                    $manager->flush();
                    return $this->redirectToRoute('app_login');
                }
                else {
                    return $this->redirectToRoute('home');
                }
            }
            return $this->render('security/reset.html.twig');
        }
        else {
            return $this->redirectToRoute('home');
        }
    }


      /**
     * @Route("/registerOrLogin", name="app_choiceRegister")
     */
    public function choice()
    {
        return $this->render('security/choose.html.twig');
    }
}
