<?php 
namespace App\Notification;

use App\Entity\User;
use Twig\Environment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ContactNotification {

    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @var Environment
     */
    private $renderer;

    public function __construct(MailerInterface $mailer, Environment $renderer,UrlGeneratorInterface $router)
    {
        $this->mailer = $mailer;
        $this->renderer = $renderer;
        $this->router = $router;
    
    }

    public function notify(User $user)
    {
        $urlConfirmedToken = $this->router->generate('app_confirmed_mail', [
            'Apitoken' => $user->getApitoken()
        ]); 

        $email = (new Email())
                    ->from('jvjlondon@outlook.com')
                    ->to($user->getEmail())
                    ->subject('Activate your account, Registration of ' . $user->getUsername())
                    ->replyTo('jvjlondon@outlook.com')
                    ->html($this->renderer->render('emails/confirmation.html.twig',[
                        'user' => $user,
                        'urlConfirmedToken' => 'http://localhost:8000' . $urlConfirmedToken 
                    ]));

        /** @var Symfony\Component\Mailer\SentMessage $sentEmail */
        $this->mailer->send($email);
    }

    public function forgotNotify(User $user)
    {
        $urlResetToken = $this->router->generate('app_reset_password', [
            'ResetToken' => $user->getResetToken()
        ]); 

        $email = (new Email())
                    ->from('jvjlondon@outlook.com')
                    ->to($user->getEmail())
                    ->subject('Forgotten Password Link, Get a new password for ' . $user->getUsername())
                    ->replyTo('jvjlondon@outlook.com')
                    ->html($this->renderer->render('emails/forgotPassword.html.twig',[
                        'user' => $user,
                        'urlResetToken' => 'http://localhost:8000' . $urlResetToken 
                    ]));

        /** @var Symfony\Component\Mailer\SentMessage $sentEmail */
        $this->mailer->send($email);

    }

}