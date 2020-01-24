<?php

namespace App\Controller;


use App\Entity\Photo;
use App\Entity\Trick;
use App\Entity\Video;
use App\Form\EditType;
use App\Entity\Comment;
use App\Form\TrickType;
use App\Form\VideoType;
use App\Form\PhotoType;
use App\Form\CommentType;
use App\Repository\PhotoRepository;
use App\Repository\TrickRepository;
use App\Repository\VideoRepository;
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
    public function index(TrickRepository $trickRepository)
    {
    
        $tricks = $trickRepository->findAll();

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
    public function show(Trick $trick, Request $request, EntityManagerInterface $manager, TrickRepository $trickRepository, CommentRepository $commentRepository)
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

            $commentRepository->editComment($comment,$trick,$user,$manager);
            return $this->redirectToRoute('blog_show', ['id' => $trick->getId()]);
        }

        return $this->render('blog/show.html.twig', [
            'trick' => $trick,
            'commentForm' => $formComment->createView(),
            'username' => $username,
            'comments' => $comments,
            'limit' => (int)$limit,
          
        ]);
    }

     /**
     * @Route("/admin/new", name="blog_create")
     */
    public function create(TrickRepository $trickRepository,PhotoRepository $photoRepository,VideoRepository $videoRepository, Request $request, EntityManagerInterface $manager)
    {
        
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $trick = new Trick();

        $formTrick = $this->createForm(TrickType::class, $trick);
        $formTrick->handleRequest($request);

        if($formTrick->isSubmitted() && $formTrick->isValid()) 
        {   
            $trickRepository->editTrick($trick,$manager,$user);

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
                $photoRepository->uploadAndAddPhoto($photo,$filename,$trick,$manager);
                if($trick->getFeaturedPhoto() === null)
                {
                    $trickRepository->setFeaturedPhoto($trick,$manager,$photo);
                }
            }

            $videoFullUrl = $formTrick->get('videos')->getData();
            if (!empty($videoFullUrl))
            {
                parse_str( parse_url( $videoFullUrl, PHP_URL_QUERY ), $videoPathUrl );
                $video = new Video();
                $videoRepository->editVideo($video,$videoPathUrl,$trick);
                $trickRepository->addVideoToCollection($trick,$manager,$video);
            }

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
    public function edit(TrickRepository $trickRepository, VideoRepository $videoRepository, PhotoRepository $photoRepository, Video $video = null, Trick $trick, Request $request, EntityManagerInterface $manager)
    {   
        $username = $this->getUserNameWhenConnected();

        $imageToDelete = $request->get('deletePhoto');
        $imageToFeatured = $request->get('editPhoto');

        $videoToDelete = $request->get('deleteVideo');

        $imageToUnFeatured = $request->get('editFeatured');
        $imageFeaturedToDelete = $request->get('deleteFeatured');

        $formEdit = $this->createForm(TrickType::class, $trick);
        $formEdit->handleRequest($request);

        $formPhotoEdit = $this->createForm(PhotoType::class, $trick);
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
            $videoRepository->deleteVideo($video,$videoToDelete,$manager);
        }

        if (isset($imageFeaturedToDelete))
        {
            $photoRepository->deletePhoto($imageFeaturedToDelete,$manager);
            $trickRepository->setDefaultImageFeatured($trick,$manager);
        }

        if (isset($imageToUnFeatured))
        {
            $trickRepository->setDefaultImageFeatured($trick,$manager);
        }

        if (isset($imageToDelete))
        {
            $photoRepository->deletePhoto($imageToDelete,$manager);
        }

        if (isset($imageToFeatured))
        {
            $trickRepository->setFeaturedPhoto($trick,$manager,$imageToFeatured);
        }

        if($formPhotoEdit->isSubmitted() && $formPhotoEdit->isValid()) 
        {

            $trickRepository->modifyTrick($trick,$manager);

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
                $photoRepository->uploadAndAddPhoto($photo,$filename,$trick,$manager);
                if($trick->getFeaturedPhoto() === null)
                {
                    $trickRepository->setFeaturedPhoto($trick,$manager,$photo);
                }
            }
        }

        if($formVideoEdit->isSubmitted() && $formVideoEdit->isValid()) 
        {
            $videoFullUrl = $formVideoEdit->get('pathUrl')->getData();
            parse_str( parse_url( $videoFullUrl, PHP_URL_QUERY ), $videoPathUrl );
            $video = new Video();
            $videoRepository->editVideo($video,$videoPathUrl,$trick);
            $trickRepository->addVideoToCollection($trick,$manager,$video);
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
    public function delete(TrickRepository $trickRepository,Trick $trick, EntityManagerInterface $manager)
    {
        $trickRepository->deleteTrick($trick,$manager);
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