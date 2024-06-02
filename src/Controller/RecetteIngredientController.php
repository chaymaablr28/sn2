<?php

namespace App\Controller;

use App\Repository\RecetteIngredientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecetteIngredientController extends AbstractController
{
    #[Route('/recette/ingredient/{id}', name: 'app_recette_ingredient')]
    public function index(int $id, RecetteIngredientRepository $recetteIngredientRepository): Response
    {
        return $this->render('recette_ingredient/index.html.twig', [
            'recetteIngredients' => $recetteIngredientRepository->findByRecette($id)
        ]);
    }
}
