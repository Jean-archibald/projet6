<?php

namespace App\Controller;


use App\Entity\Trick;
use App\Form\TrickType;
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

        return $this->render('blog/index.html.twig', [
            'controller_name' => 'BlogController',
            'tricks' => $tricks
        ]);
    }

    /**
     * @Route("/", name="home")
     */
    public function home()
    {
        return $this->render('blog/home.html.twig');
    }


     /**
     * @Route("/blog/new", name="blog_create")
     */
    public function create( Request $request, EntityManagerInterface $manager)
    {
        $trick = new Trick();
        
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $formTrick = $this->createForm(TrickType::class, $trick);
        $formTrick->handleRequest($request);

        if($formTrick->isSubmitted() && $formTrick->isValid()) {
            
            $file = $request->files->get('trick')['featuredPhoto'];
            
            $uploads_directory = $this->getParameter('uploads_directory');

            $filename = md5(uniqid()) . '.' . $file->guessExtension();

            $file->move(
                $uploads_directory,
                $filename
            );

            $trick->setCreatedAt(new \DateTime())
            ->setUser($user)
            ->setTrash(false)
            ->setFeaturedPhoto($filename);

            $manager->persist($trick);
            $manager->flush();


            /*return $this->redirectToRoute('blog_show', ['id' => $trick->getId()]);*/
        }

        return $this->render('blog/create.html.twig', [
            'formTrick' => $formTrick->createView()
        ]);
    }

    /**
     * @Route("/blog/{id}", name="blog_show")
     */
    public function show($id)
    {
        $repo = $this->getDoctrine()->getRepository(Trick::class);

        $trick = $repo->find($id);

        return $this->render('blog/show.html.twig', [
            'trick' => $trick
        ]);
    }
}