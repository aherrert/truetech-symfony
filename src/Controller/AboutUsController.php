<?php

namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Message;
use Doctrine\DBAL\Types\StringType;

class AboutUsController extends AbstractController
{
    #[Route('/aboutus', name: 'app_about_us')]
    public function index( ManagerRegistry $managerRegistry): Response
    {   
        $about_repo = $managerRegistry->getRepository(Message::class);
        $aboutUs = $about_repo->findOneBy(['codeMessage'=> 'about_us']);
        return $this->render('about_us/index.html.twig', [
            'controller_name' => 'AboutUsController',
            'aboutUs' => $aboutUs
        ]);
    }
}