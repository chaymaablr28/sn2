<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Ingredient;
use App\Entity\Recette;
use App\Entity\User;
use App\Form\CommentaireType;
use App\Form\RecetteType;
use App\Form\SearchType;
use App\Repository\FavorisRepository;
use App\Repository\RecetteRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/recette')]
class RecetteController extends AbstractController
{
    // #[Route('/', name: 'app_recette_index', methods: ['GET'])]
    // public function index(RecetteRepository $recetteRepository, FavorisRepository $favorisRepository): Response
    // {
    //     $user = $this->getUser();
    //     // Get all recipes
    //     $recettes = $recetteRepository->findAll();

    //     // Get user's favorite recipes
    //     $userFavoris = $favorisRepository->findBy(['createdBy' => $user]);

    //     // Extract recipes favorited by the user
    //     $favoritedRecettes = [];
    //     foreach ($userFavoris as $favori) {
    //         $favoritedRecettes[] = $favori->getRecette();
    //     }

    //     // Get the IDs of favorited recipes
    //     $favoritedRecetteIds = array_map(function($recette) {
    //         return $recette->getId();
    //     }, $favoritedRecettes);

    //     // Separate non-favorited recipes
    //     $nonFavoritedRecettes = array_filter($recettes, function($recette) use ($favoritedRecetteIds) {
    //         return !in_array($recette->getId(), $favoritedRecetteIds);
    //     });

    //     // Combine favorited recipes first, followed by non-favorited recipes
    //     $orderedRecettes = array_merge($favoritedRecettes, $nonFavoritedRecettes);


    //     return $this->render('recette/index.html.twig', [
    //         'recettes' => $orderedRecettes,
    //     ]);
    // }

    private function isRecetteFavorited(Recette $recette, FavorisRepository $favorisRepository): bool
    {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }
        $favori = $favorisRepository->findOneBy(['createdBy' => $user, 'recette' => $recette]);
        return $favori !== null;
    }


    #[Route('/', name: 'app_recette_index', methods: ['GET', 'POST'])]
    public function index(Request $request, RecetteRepository $recetteRepository, FavorisRepository $favorisRepository)
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        $recettes = [];
        $searchResults = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $ingredients = $form->get('ingredients')->getData();

            $ingredientNames = array_map(function($ingredient) {
                return $ingredient->getNom();
            }, $ingredients->toArray());

            $recettes = $recetteRepository->findAll();

            foreach ($recettes as $recette) {
                $recipeIngredients = array_map(function($recetteIngredient) {
                    return $recetteIngredient->getIngredient()->getNom();
                }, $recette->getRecetteIngredients()->toArray());

                $matchedIngredients = array_intersect($ingredientNames, $recipeIngredients);
                $percentage = (count($matchedIngredients) / count($ingredientNames)) * 100;

                if($percentage==0) continue;

                // Check if the recipe is favorited by the current user
                $isFavorised = $this->isRecetteFavorited($recette, $favorisRepository);

                $searchResults[] = [
                    'recette' => $recette,
                    'percentage' => $percentage,
                    'isFavorised' => $isFavorised,
                ];
            }

            usort($searchResults, function ($a, $b) {
                return $b['percentage'] <=> $a['percentage'];
            });

            
            usort($searchResults, function ($a, $b) {
                return $b['isFavorised'] <=> $a['isFavorised'];
            });

            
            return $this->render('recette/index.html.twig', [
                'form' => $form->createView(),
                'searchResults' => $searchResults,
            ]);
        }

        $recettes = $recetteRepository->findAll();
        foreach ($recettes as $recette) {
            $percentage = "-";
            $isFavorised = $this->isRecetteFavorited($recette, $favorisRepository);

            $searchResults[] = [
                'recette' => $recette,
                'percentage' => $percentage,
                'isFavorised' => $isFavorised,
            ];
        }

        usort($searchResults, function ($a, $b) {
            return $b['isFavorised'] <=> $a['isFavorised'];
        });

        return $this->render('recette/index.html.twig', [
            'form' => $form->createView(),
            'searchResults' => $searchResults,
        ]);
    }


    #[Route('/new', name: 'app_recette_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] ?User $user, EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        $recette = new Recette();
        $form = $this->createForm(RecetteType::class, $recette);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $recetteFile = $form->get('photoFile')->getData();

            if ($recetteFile) {
                $recetteFileName = $fileUploader->upload($recetteFile);
                $recette->setPhoto($recetteFileName);
            }
            $recette->setCreatedBy($user);
            $entityManager->persist($recette);
            $entityManager->flush();

            return $this->redirectToRoute('app_recette_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recette/new.html.twig', [
            'recette' => $recette,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/show', name: 'app_recette_show', methods: ['GET', 'POST'])]
    public function show(Recette $recette, Request $request, EntityManagerInterface $entityManager, FavorisRepository $favorisRepository): Response
    {        
        $user = $this->getUser();
        $isFavorited = $favorisRepository->findOneBy(['createdBy' => $user, 'recette' => $recette]);

        $comment = new Commentaire();
        $commentForm = $this->createForm(CommentaireType::class, $comment);
        $commentForm->handleRequest($request);
    
        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setRecette($recette);
            // Set the user who submitted the comment
            $comment->setUser($this->getUser());
            $comment->setRecette($recette);
            $comment->setDate(new \DateTime());
    
            $entityManager->persist($comment);
            $entityManager->flush();
    
            // Redirect to prevent form resubmission
            return $this->redirectToRoute('app_recette_show', ['id' => $recette->getId()]);
        }
    
        // Fetch existing comments for the recipe
        $comments = $recette->getCommentaires();

        return $this->render('recette/show.html.twig', [
            'recette' => $recette,
            'isFavorited' => $isFavorited,
            'commentForm' => $commentForm->createView(),
            'comments' => $comments,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recette_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recette $recette, EntityManagerInterface $entityManager, FileUploader $fileUploader): Response
    {
        $form = $this->createForm(RecetteType::class, $recette);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $recetteFile = $form->get('photoFile')->getData();

            if ($recetteFile) {
                $recetteFileName = $fileUploader->upload($recetteFile);
                $recette->setPhoto($recetteFileName);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_recette_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('recette/edit.html.twig', [
            'recette' => $recette,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/by_ingredients', name: "app_recette_ingredient_show")]
    public function listByIngredients(Ingredient $ingredient)
    {
        $recettes = $ingredient->getRecetteIngredients();

        return $this->render('recette/list_by_ingredients.html.twig', [
            'ingredient' => $ingredient,
            'recettes' => $recettes,
        ]);
    }

    #[Route('/{id}', name: 'app_recette_delete', methods: ['POST'])]
    public function delete(Request $request, Recette $recette, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recette->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($recette);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_recette_index', [], Response::HTTP_SEE_OTHER);
    }
}
