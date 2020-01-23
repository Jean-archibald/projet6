<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Form\ForgotType;
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
    public function registration(MailerInterface $mailer,Request $request, EntityManagerInterface $manager, UserPasswordEncoderInterface $encoder, ContactNotification $notification) {
        $user = new User();

        $form = $this->createForm(RegistrationType::class, $user);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
            {   
                $this->addFlash('success','We just send you an email registration confirmation');

                $hash = $encoder->encodePassword($user, $user->getPassword());
                $user->setApitoken($this->generateToken());
                $user->SetConfirmed(0);
                $user->setPassword($hash);

                $manager->persist($user);
                $manager->flush();
                $notification->notify($user,$mailer);

                return $this->redirectToRoute('home');
            }

        return $this->render('security/registration.html.twig', [
            'form' => $form->createView()
        ]);
    }

    public function generateToken()
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/','-_'), '=');
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
    public function confirmedMail(string $Apitoken, EntityManagerInterface $manager)
    {
  
        $repo = $this->getDoctrine()->getRepository(User::class);
        $user = $repo->findOneBy(['apiToken' => $Apitoken]);

        if (isset($user))
        {
            $user->SetConfirmed(1);
            $manager->persist($user);
            $manager->flush();
            return $this->redirectToRoute('app_login');
        }
        else
        {
            return $this->redirectToRoute('home');
        }
    }   


      /**
     * @Route("/passwordForgotten", name="app_forgotten_password")
     */
    public function forgottenPassword(Request $request,MailerInterface $mailer, EntityManagerInterface $manager,ContactNotification $notification): Response
    {
        if ($request->isMethod('POST')) {
    
            $username = $request->request->get('username');

            $repo = $this->getDoctrine()->getRepository(User::class);
 
            $user = $repo->findOneBy(['username' => $username]);
 
            if (isset($user))
            {
                $token = $this->generateToken();
                $user->setResetToken($token);
                $manager->persist($user);
                $manager->flush();
                $notification->forgotNotify($user,$mailer);
                $this->addFlash('success', 'We just send you a reset mail');
            }
            else{
                $this->addFlash('danger', 'Username does not exist!');
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
    
        if(isset($user))
        {
            if ($request->isMethod('POST'))
            {
                if (isset($user))
                {
                
                    $hash = $encoder->encodePassword($user, $request->request->get('password'));
                    $user->setPassword($hash);
                    $manager->persist($user);
                    $manager->flush();
                    return $this->redirectToRoute('app_login');
                }
                else
                {
                    return $this->redirectToRoute('home');
                }
            }

            return $this->render('security/reset.html.twig');
        }
        else
        {
            return $this->redirectToRoute('home');
        }
      
    }

}
