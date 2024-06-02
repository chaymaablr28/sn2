<?php

namespace App\Controller;

use App\Entity\Favoris;
use App\Entity\Recette;
use App\Entity\User;
use App\Repository\FavorisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class FavorisController extends AbstractController
{
    #[Route('/favoris/{id}/add/', name: 'app_favoris_add')]
    public function add(#[CurrentUser] ?User $user, Recette $recette, EntityManagerInterface $entityManager): Response
    {
        $favorise = new Favoris();
        $favorise->setCreatedBy($user);
        $favorise->setRecette($recette);
        
        $entityManager->persist($favorise);
        $entityManager->flush();

        return $this->redirectToRoute('app_recette_show', ['id' => $recette->getId()]);
    }

    #[Route('/favoris/{id}/remove/', name: 'app_favoris_remove')]
    public function remove(#[CurrentUser] ?User $user, Recette $recette, FavorisRepository $favorisRepository, EntityManagerInterface $entityManager): Response
    {
        $favoris = $favorisRepository->findOneBy(['recette' => $recette, 'createdBy' => $user]);
        $entityManager->remove($favoris);
        $entityManager->flush();

        return $this->redirectToRoute('app_recette_show', ['id' => $recette->getId()]);
    }
}
