<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CgvController extends AbstractController
{
    #[Route('/conditions-generales-de-vente', name: 'app_cgv', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/information/cgv.html.twig');
    }
}