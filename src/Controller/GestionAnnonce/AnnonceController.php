<?php

namespace App\Controller\GestionAnnonce;

use App\Entity\Annonce;

use App\Entity\Categorie;
use App\Entity\Search;
use App\Form\AnnonceType;
use App\Form\SearchType;
use App\Repository\AdsRepository;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use MercurySeries\FlashyBundle\FlashyNotifier;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnonceController extends AbstractController
{


    //mise en place de l'injection de dependance
    /**
     * @var AnnonceRepository
     */
    private AnnonceRepository $repository;
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $manager;
    private $flashy;

    public function __construct(AnnonceRepository $repository, EntityManagerInterface $manager,FlashyNotifier $flashy)
    {
        $this->repository = $repository;
        $this->manager = $manager;
        $this->flashy = $flashy;
    }


    /**
     * @Route("/annonce", name="app_create_annonce")
     *
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function createAnnonce(Request $request): Response
    {
        if (!$this->isGranted("ROLE_RECRUTEUR")) {
            return $this->redirectToRoute("app_login");
        }

        //declaration d'une nouvelle annonce
        $annonce = new Annonce();
        //formulaire
        $form = $this->createForm(AnnonceType::class, $annonce);

        //gestion des requetes
        $form->handleRequest($request);

        //traitement du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            // On récupère les valeurs des enum 
            $domaines = [];
            foreach ($form->get('domaineEtudes')->getData() as$domaine) {
                $domaines[]=$domaine->getValue();
            }
            $annonce->setDomaineEtudes($domaines);
            $annonce->setProprietaire($this->getUser());
            //Mise à jour de la date de publication
            $annonce->setDatePublication(new \DateTime());
            $this->manager->persist($annonce);
            $this->manager->flush();
            $id = $annonce->getId();
            $this->flashy->success('Annonce créée avec succès');
            //on redirige vers la page d'affichage des annonces ou la page d'admin des annonces
            return $this->redirectToRoute('app_annonce_show', [
                'id' => $id
            ]);
        }

        return $this->render('annonce/publier.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView()
        ]);
    }


    //test affiche une annonce

    /**
     * @Route("/annonce/{id<\d+>}", name="app_annonce_details")
     * @param Annonce $annonce
     * @return Response
     */
    public function showOneAd(Annonce $annonce)
    {
        return $this->render("annonce/oneannonce.html.twig", [
            'current_menu' => 'annonce',
            'annonce' => $annonce
        ]);
    }

    // modification d'une annonce

    /**
     * @param Annonce $annonce
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/annonce/{id<\d+>}/edit", name="app_annonce_edit")
     * @throws Exception
     */
    public function edit(Annonce $annonce, Request $request)
    {
        $form = $this->createForm(AnnonceType::class, $annonce);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $this->manager->flush();


            $this->addFlash('success', 'Annonce modifer avec succès');
            $id = $this->getUser()->getId();
            //au lieu de home retourner la route d'admin des annonces de chaque user
            return $this->redirectToRoute('show_one_ad', [
                'id' => $id
            ]);
        }

        return $this->render('annonce/editer.html.twig', [
            'ads' => $annonce,
            'form' => $form->createView()
        ]);
    }

    //suppression d'une annonce

    /**
     * @param Annonce $annonce
     * @param Request $request
     * @return RedirectResponse
     * @Route("/annonce/{id<\d+>}/delete", name="app_annonce_delete", methods={"DELETE"})
     */
    public function delete(Annonce $annonce, Request $request)
    {
        if ($this->isCsrfTokenValid('delete' . $annonce->getId(), $request->get('_token'))) {
            $this->manager->remove($annonce);
            $this->manager->flush();

            $this->addFlash('success', 'Annonce supprimé avec succès');
        }

        $id = $this->getUser()->getId();
        //on redirige vers la page d'édition de l'admin des biens
        //Mais là on redirige vers la page d'acceuil
        return $this->redirectToRoute('app_espace_recruteur', [
            //'id' => $id
        ]);
    }


    //lister tous les annonces
    //implémenter le paginator pour les annonces
    //et aussi implémenter les la recherche avec Search
    //Page d'acceuil

    /**
     * @Route("/annonce/show/{id<\d+>}", name="app_annonce_show_id")
     *
     * @param Request $request
     *
     * @param Search|null $search
     * @return Response
     */
    public function showAds(Request $request,Annonce $annonce, ?Search $search)
    {
        if ($search == null) {
            $search = new Search();
        }
        $form = $this->createForm(SearchType::class, $search);

        $form->handleRequest($request);
        $a = $annonce->getDomaineEtudes();

        $annonces = $this->repository->getAllAnnoncesSearch($search)->getResult();

        //$ads = $paginator->paginate($this->repository->findAllAdsQuery($search), $request->query->getInt('page', 1), 3);
        /*$annonce = new Annonce();
         if($page < 1){
             throw $this->createNotFoundException("Page ".$page." innexistante");

         }
         $parpages = 3;
          $listesAnnonces = $this->getDoctrine()->getManager()->getRepository(Annonce::class)->getAnnonces($page, $parpages);
          $nbpages = ceil(count($listesAnnonces) / $parpages);

          /* if ($page>$nbpages){
               throw $this->createNotFoundException("Page ".$page. " inexistante");
           }**///
        return $this->render('annonce/list.html.twig', [
            //'listesAnnonces'=> $annonces,//$listesAnnonces,
            /* 'nbpages'=>$nbpages,
            'page'=>$page,*/
            'annonces' => $annonces,
            'annonce'=>$annonce,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/annonce/show", name="app_annonce_show"  )
     *
     * @param Request $request
     *
     * @param Search|null $search
     * @return Response
     */
    public function showAdsWithoutId(Request $request, ?Search $search)
    {
        if ($search == null) {
            $search = new Search();
        }
        $form = $this->createForm(SearchType::class, $search);

        $form->handleRequest($request);

        $annonces = $this->repository->getAllAnnoncesSearch($search)->getResult();

        //$ads = $paginator->paginate($this->repository->findAllAdsQuery($search), $request->query->getInt('page', 1), 3);
        /*$annonce = new Annonce();
         if($page < 1){
             throw $this->createNotFoundException("Page ".$page." innexistante");

         }
         $parpages = 3;
          $listesAnnonces = $this->getDoctrine()->getManager()->getRepository(Annonce::class)->getAnnonces($page, $parpages);
          $nbpages = ceil(count($listesAnnonces) / $parpages);

          /* if ($page>$nbpages){
               throw $this->createNotFoundException("Page ".$page. " inexistante");
           }**///

        return $this->render('annonce/list.html.twig', [
            //'listesAnnonces'=> $annonces,//$listesAnnonces,
            /* 'nbpages'=>$nbpages,
            'page'=>$page,*/
            'annonces' => $annonces,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route ("/espace-recruteur/mesAnnonces",name="app_recruteur_myAds")
     */
    public function showMyAds(): Response
    {
        if (!$this->isGranted("ROLE_RECRUTEUR")) {
            return $this->redirectToRoute('app_espace_recruteur');
        }

        $annonces = $this->getUser()->getAnnonces();

        return $this->render('annonce/myAds.html.twig', [
            'annonces' => $annonces
        ]);
    }
}
