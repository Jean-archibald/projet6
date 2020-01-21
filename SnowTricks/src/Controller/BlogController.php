<?php

namespace App\Controller;


use App\Entity\Photo;
use App\Entity\Trick;
use App\Entity\Comment;
use App\Form\PhotoType;
use App\Form\TrickType;
use App\Form\CommentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;



class BlogController extends AbstractController
{
    /**
     * @Route("/blog", name="blog")
     */
    public function index()
    {
        $repo = $this->getDoctrine()->getRepository(Trick::class);

        $tricks = $repo->findAll();

        $username = $this->getUserNameWhenConnected();

        return $this->render('blog/index.html.twig', [
            'controller_name' => 'BlogController',
            'tricks' => $tricks,
            'username' => $username
        ]);
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        $repo = $this->getDoctrine()->getRepository(Trick::class);

        $tricks = $repo->findAll();

        $username = $this->getUserNameWhenConnected();

        return $this->render('blog/home.html.twig', [
            'controller_name' => 'BlogController',
            'tricks' => $tricks,
            'username' => $username
        ]);
    }

      /**
     * @Route("/admin/{id}/delete", name="blog_delete")
     */
    public function delete(Trick $trick, EntityManagerInterface $manager)
    {

        $trickFeaturedPhoto = $trick->getFeaturedPhoto();
        if(!$trick)
        {
            throw $this->createNotFoundException("The trick doesnt exist");
        }
        unlink('uploads/'. $trickFeaturedPhoto);
        $manager->remove($trick);
        $manager->flush();

        
        return $this->redirectToRoute('home');
    }


     /**
     * @Route("/admin/new", name="blog_create")
     * @Route("/admin/{id}/edit", name="blog_edit")
     */
    public function trick(Trick $trick = null, Request $request, EntityManagerInterface $manager)
    {
        if(!$trick)
        {
            $trick = new Trick();
            $photoFeatured = "";
        }
        else
        {
            $photoFeatured = $trick->getFeaturedPhoto();
        }
      

        $user = $this->get('security.token_storage')->getToken()->getUser();

        $formTrick = $this->createForm(TrickType::class, $trick);
        $formTrick->handleRequest($request);
        if($formTrick->isSubmitted() && $formTrick->isValid()) {
            $file = $request->files->get('trick')['featuredPhoto'];
            if (isset($file))
            {
            $uploads_directory = $this->getParameter('uploads_directory');

            $filename = md5(uniqid()) . '.' . $file->guessExtension();

            $file->move(
                $uploads_directory,
                $filename
            );
            }

            if($trick->getId() && !isset($file))
            {
                $trick->setCreatedAt(new \DateTime())
                      ->setFeaturedPhoto($photoFeatured)
                      ->setUser($user)
                      ->setTrash(false);
            }
            elseif($trick->getId() && isset($file))
            {
                unlink('uploads/'. $photoFeatured);
                $trick->setModifiedAt(new \DateTime())
                      ->setFeaturedPhoto($filename)
                      ->setUser($user)
                      ->setTrash(false);
            }
            else
            $trick->setCreatedAt(new \DateTime())
                  ->setUser($user)
                  ->setTrash(false)
                  ->setFeaturedPhoto($filename);

            $manager->persist($trick);
            $manager->flush();


            return $this->redirectToRoute('blog_show', ['id' => $trick->getId()]);
        }

      
      

        return $this->render('blog/create.html.twig', [
            'formTrick' => $formTrick->createView(),
            'editMode' => $trick->getId() !== null,
            'trick' => $trick,
            'photoFeatured' => $photoFeatured
        ]);
    }


  
    /**
     * @Route("/blog/{id}", name="blog_show")
     */
    public function show(Trick $trick, Request $request, EntityManagerInterface $manager)
    {   
        $username = $this->getUserNameWhenConnected();
        $user = $this->getUserWhenConnected();

        $comment = new Comment();
        $formComment = $this->createForm(CommentType::class, $comment);

        $formComment->handleRequest($request);

        if($formComment->isSubmitted() && $formComment->isValid()) {
            $comment->setCreatedAt(new \DateTime())
                    ->setTrick($trick)
                    ->setUser($user);

            $manager->persist($comment);
            $manager->flush();

            return $this->redirectToRoute('blog_show', ['id' => $trick->getId()]);
        }

        return $this->render('blog/show.html.twig', [
            'trick' => $trick,
            'commentForm' => $formComment->createView(),
            'username' => $username
        ]);
    }

    public function getUserWhenConnected()
    {
        $token = $this->get('security.token_storage')->getToken();
        $user = $token->getUser();
        return $user;
    }

    public function getUserNameWhenConnected()
    {
        $user = $this->getUserWhenConnected();
    
        if ($user != "anon.")
        {
            $username = $user->getUsername();
        }
        else
        {
            $username = "";
        }

        return $username;
    }
}