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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;



class BlogController extends AbstractController
{
    /**
     * @Route("/blog", name="blog")
     */

    public function index(TrickRepository $trickRepository)
    {
        //query for all tricks and load them all in the template
        $tricks = $trickRepository->findAll();
        //if username is the same as trick, the user will be able to edit them
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
        //result of form which is related to the button LoadMore
        $loadMore = $request->get('loadMore');
        $limitPost = (int)$request->get('limit');

        if(isset($loadMore))
        {   
            //if LoadMore is valid, the limitpost increase of 5  eachtime it s click on
            $offset = 0;
            $limit = $limitPost;
            $tricks = $trickRepository->findSomeTrickOrderedByNewest($offset,$limit);
        }
        else
        {   
            //if LoadMore is not valid, just 5 tricks are loaded
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
     * @Route("/blog/{slug}", name="blog_show",)
     */
    public function show(Trick $trick, Request $request, EntityManagerInterface $manager, TrickRepository $trickRepository, CommentRepository $commentRepository)
    {    
      
        //LoadMore for comments 
        $loadMore = $request->get('loadMore');
        $limitPost = (int)$request->get('limit');

        if(isset($loadMore))
        {
            //each time button LoadMore is click on, 5 more comments appear
            $offset = 0;
            $limit = $limitPost;
            $comments = $commentRepository->findSomeCommentOrderedByNewest($trick,$offset,$limit);
        }
        else
        {
            //The page come with 5 comments at beggining
            $offset = 0;
            $limit = 5;
            $comments = $commentRepository->findSomeCommentOrderedByNewest($trick,$offset,$limit);
        }

        //Get the username who is connected and see if he is able to set up the trick
        $username = $this->getUserNameWhenConnected();
        $user = $this->getUserWhenConnected();

        $comment = new Comment();
        $formComment = $this->createForm(CommentType::class, $comment);

        $formComment->handleRequest($request);

        if($formComment->isSubmitted() && $formComment->isValid()) {

            //if comment is submit and valid, its save in database and publish right away
            $commentRepository->editComment($comment,$trick,$user,$manager);
            return $this->redirectToRoute('blog_show', ['slug' => $trick->getSlug()]);
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
        //get the User informations to put relate him to the new trick automaticly
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $trick = new Trick();

        $formTrick = $this->createForm(TrickType::class, $trick);
        $formTrick->handleRequest($request);

        if($formTrick->isSubmitted() && $formTrick->isValid()) 
        { 
            $title = $trick->getTitle();  
            $slug = preg_replace('~[^\pL\d]+~u', '', $title);
            $trickRepository->editTrick($trick,$manager,$user,$slug);

            $uploads_directory = $this->getParameter('uploads_directory');
            //get array of photos
            $photoFiles = $request->files->get('trick')['photos'];
            //loop throught the photoFiles
            
            foreach($photoFiles as $photoFile)
            {
                //create a unique name for the photo
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

            //get the video Url and formate it to be able to read it with the youtube embed media 
            $videoFullUrl = $formTrick->get('videos')->getData();
            if (!empty($videoFullUrl))
            {
                parse_str( parse_url( $videoFullUrl, PHP_URL_QUERY ), $videoPathUrl );
                $video = new Video();
                $videoRepository->editVideo($video,$videoPathUrl,$trick);
                $trickRepository->addVideoToCollection($trick,$manager,$video);
            }

            return $this->redirectToRoute('blog_show', ['slug' => $trick->getSlug()]);
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

        //get the needed information to be able to set photo and video
        $imageToDelete = $request->get('deletePhoto');
        $imageToFeatured = $request->get('editPhoto');
        $videoToDelete = $request->get('deleteVideo');
        $imageToUnFeatured = $request->get('editFeatured');
        $imageFeaturedToDelete = $request->get('deleteFeatured');

        //create form to edit trick
        $formEdit = $this->createForm(TrickType::class, $trick);
        $formEdit->handleRequest($request);

        //create form to edit photo
        $formPhotoEdit = $this->createForm(PhotoType::class, $trick);
        $formPhotoEdit->handleRequest($request);

        //create form to edit video
        $formVideoEdit = $this->createForm(VideoType::class, $video);
        $formVideoEdit->handleRequest($request);

        if($formEdit->isSubmitted() && $formEdit->isValid()) {
            $trick->setModifiedAt(new \DateTime());
            $manager->persist($trick);
            $manager->flush();
        }

        if (isset($videoToDelete))
        {
            //delete the video
            $videoRepository->deleteVideo($video,$videoToDelete,$manager);
        }

        if (isset($imageFeaturedToDelete))
        {
            //delete the featured image of the trick and set a Default one
            $photoRepository->deletePhoto($imageFeaturedToDelete,$manager);
            $trickRepository->setDefaultImageFeatured($trick,$manager);
        }

        if (isset($imageToUnFeatured))
        {
            //set the featured image back to normal
            $trickRepository->setDefaultImageFeatured($trick,$manager);
        }

        if (isset($imageToDelete))
        {
            //delete the photo
            $photoRepository->deletePhoto($imageToDelete,$manager);
        }

        if (isset($imageToFeatured))
        {
            //set the image to featured
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