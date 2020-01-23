<?php

namespace App\Controller;


use App\Entity\Photo;
use App\Entity\Trick;
use App\Entity\Video;
use App\Form\EditType;
use App\Entity\Comment;
use App\Form\TrickType;
use App\Form\VideoType;
use App\Form\UploadType;
use App\Form\CommentType;
use App\Form\UploadPhotoType;
use App\Repository\TrickRepository;
use App\Repository\CommentRepository;
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
    public function home(Request $request, TrickRepository $trickRepository)
    {

        $loadMore = $request->get('loadMore');
        $limitPost = (int)$request->get('limit');

        if(isset($loadMore))
        {
            $offset = 0;
            $limit = $limitPost;
            $tricks = $trickRepository->findSomeTrickOrderedByNewest($offset,$limit);
        }
        else
        {
            $offset = 0;
            $limit = 5;
            $tricks = $trickRepository->findSomeTrickOrderedByNewest($offset,$limit);
        }

        $username = $this->getUserNameWhenConnected();

        return $this->render('blog/home.html.twig', [
            'controller_name' => 'BlogController',
            'tricks' => $tricks,
            'username' => $username,
            'limit' => (int)$limit
        ]);
    }

     /**
     * @Route("/blog/{id}", name="blog_show")
     */
    public function show(Trick $trick, Request $request, EntityManagerInterface $manager, CommentRepository $commentRepository)
    {    
        $loadMore = $request->get('loadMore');
        $limitPost = (int)$request->get('limit');

        if(isset($loadMore))
        {
            $offset = 0;
            $limit = $limitPost;
            $comments = $commentRepository->findSomeCommentOrderedByNewest($trick,$offset,$limit);
        }
        else
        {
            $offset = 0;
            $limit = 5;
            $comments = $commentRepository->findSomeCommentOrderedByNewest($trick,$offset,$limit);
        }

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
            'username' => $username,
            'comments' => $comments,
            'limit' => (int)$limit
        ]);
    }

     /**
     * @Route("/admin/new", name="blog_create")
     */
    public function create(Request $request, EntityManagerInterface $manager)
    {
        
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $trick = new Trick();

        $formTrick = $this->createForm(TrickType::class, $trick);
        $formTrick->handleRequest($request);

        if($formTrick->isSubmitted() && $formTrick->isValid()) 
        {
            $trick->setCreatedAt(new \DateTime())
            ->setUser($user)
            ->setTrash(false)
            ;
            $manager->persist($trick);
            $manager->flush();

            $uploads_directory = $this->getParameter('uploads_directory');
            //get array of photos
            $photoFiles = $request->files->get('trick')['photos'];
            //loop throught the photoFiles
            
            foreach($photoFiles as $photoFile)
            {
                $filename = md5(uniqid()) . '.' . $photoFile->guessExtension();

                $photoFile->move(
                    $uploads_directory,
                    $filename
                );
                
                    $photo = new Photo();
                    $photo->setCreatedAt(new \DateTime())
                          ->setPathUrl('uploads/'.$filename)
                          ->setTrick($trick);
                      ;
                    $manager->persist($photo);
                    $manager->flush();

                if($trick->getFeaturedPhoto() === null)
                {
                    $trick->setFeaturedPhoto($photo->getPathUrl());
                    $manager->persist($trick);
                    $manager->flush();
                }
            }

            $videoFullUrl = $formTrick->get('videos')->getData();
            parse_str( parse_url( $videoFullUrl, PHP_URL_QUERY ), $videoPathUrl );
            var_dump($videoPathUrl);
            $video = new Video();
                    $video->setCreatedAt(new \DateTime())
                          ->setPathUrl($videoPathUrl["v"])
                          ->setTrick($trick);
                      ;
                    $trick->addVideo($video);
                    $manager->persist($trick);
                    $manager->persist($video);
                    $manager->flush();

            return $this->redirectToRoute('blog_show', ['id' => $trick->getId()]);
        }

        return $this->render('blog/create.html.twig', [
            'formTrick' => $formTrick->createView(),
            'trick' => $trick
        ]);
    }


     /**
     * @Route("/admin/{id}/edit", name="blog_edit")
     */
    public function edit(Video $video = null, Trick $trick, Request $request, EntityManagerInterface $manager)
    {   
        $username = $this->getUserNameWhenConnected();

        $imageToDelete = $request->get('deletePhoto');
        $imageToFeatured = $request->get('editPhoto');

        $videoToDelete = $request->get('deleteVideo');

        $imageToUnFeatured = $request->get('editFeatured');
        $imageFeaturedToDelete = $request->get('deleteFeatured');

        $formEdit = $this->createForm(EditType::class, $trick);
        $formEdit->handleRequest($request);

        $formPhotoEdit = $this->createForm(UploadType::class, $trick);
        $formPhotoEdit->handleRequest($request);

        $formVideoEdit = $this->createForm(VideoType::class, $video);
        $formVideoEdit->handleRequest($request);

        if($formEdit->isSubmitted() && $formEdit->isValid()) {
            $trick->setModifiedAt(new \DateTime());
            $manager->persist($trick);
            $manager->flush();
        }

        if (isset($videoToDelete))
        {
            $repo = $this->getDoctrine()->getRepository(Video::class);
            $video = $repo->findOneBy(['pathUrl' => $videoToDelete]);
            $manager->remove($video);
            $manager->flush();
        }

        if (isset($imageFeaturedToDelete))
        {
            $repo = $this->getDoctrine()->getRepository(Photo::class);
 
            $photo = $repo->findOneBy(['pathUrl' => $imageFeaturedToDelete]);
            unlink($imageFeaturedToDelete);
            $manager->remove($photo);
            $manager->flush();
            $trick->setFeaturedPhoto('uploads/homeImage.jpg');
            $manager->persist($trick);
            $manager->flush();
        }

        if (isset($imageToUnFeatured))
        {
            $repo = $this->getDoctrine()->getRepository(Trick::class);
 
            $trick = $repo->findOneBy(['featuredPhoto' => $imageToUnFeatured]);
            $trick->setFeaturedPhoto('uploads/homeImage.jpg');
            $manager->persist($trick);
            $manager->flush();
        }

        if (isset($imageToDelete))
        {
            $repo = $this->getDoctrine()->getRepository(Photo::class);
 
            $photo = $repo->findOneBy(['pathUrl' => $imageToDelete]);
            unlink($imageToDelete);
            $manager->remove($photo);
            $manager->flush();
        }

        if (isset($imageToFeatured))
        {
            $trick->setFeaturedPhoto($imageToFeatured);
            $manager->persist($trick);
            $manager->flush();
        }

        if($formPhotoEdit->isSubmitted() && $formPhotoEdit->isValid()) 
        {

            $trick->setModifiedAt(new \DateTime())
            ;
            $manager->persist($trick);
            $manager->flush();


            $uploads_directory = $this->getParameter('uploads_directory');
            //get array of photos
            $photoFiles = $formPhotoEdit->get('photos')->getData();
            //loop throught the photoFiles

            foreach($photoFiles as $photoFile)
            {
                $filename = md5(uniqid()) . '.' . $photoFile->guessExtension();

                $photoFile->move(
                    $uploads_directory,
                    $filename
                );
                
                    $photo = new Photo();
                    $photo->setCreatedAt(new \DateTime())
                            ->setPathUrl('uploads/'.$filename)
                            ->setTrick($trick);
                        ;
                    $manager->persist($photo);
                    $manager->flush();

                    if($trick->getFeaturedPhoto() === null)
                    {
                        $trick->setFeaturedPhoto($photo->getPathUrl());
                        $manager->persist($trick);
                        $manager->flush();
                    }
            }
        }

        if($formVideoEdit->isSubmitted() && $formVideoEdit->isValid()) 
        {
            $videoFullUrl = $formVideoEdit->get('pathUrl')->getData();
            parse_str( parse_url( $videoFullUrl, PHP_URL_QUERY ), $videoPathUrl );
            var_dump($videoPathUrl);
            $video = new Video();
                    $video->setCreatedAt(new \DateTime())
                          ->setPathUrl($videoPathUrl["v"])
                          ->setTrick($trick);
                      ;
                    $trick->addVideo($video);
                    $trick->setModifiedAt(new \DateTime());
                    $manager->persist($trick);
                    $manager->persist($video);
                    $manager->flush();
            
        }
        
        return $this->render('blog/edit.html.twig', [
            'trick' => $trick,
            'username' => $username,
            'formEdit' => $formEdit->createView(),
            'formPhotoEdit' => $formPhotoEdit->createView(),
            'formVideoEdit' => $formVideoEdit->createView()
        ]);
    }

      /**
     * @Route("/admin/{id}/delete", name="app_delete")
     */
    public function delete(Trick $trick, EntityManagerInterface $manager)
    {

        if(!$trick)
        {
            throw $this->createNotFoundException("The trick doesnt exist");
        }
        $photos = $trick->getPhotos();
        $photoCollection = $photos->toArray();

        foreach ($photoCollection as $photo)
        {
            unlink($photo->getPathUrl());
        }
  
        $manager->remove($trick);
        $manager->flush();
        return $this->redirectToRoute('home');
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