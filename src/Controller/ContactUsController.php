<?php

namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Message;
use Doctrine\DBAL\Types\StringType;

class ContactUsController extends AbstractController
{
    #[Route('/contactus', name: 'app_contact_us')]
    public function index( ManagerRegistry $managerRegistry): Response
    {   
        $contact_repo = $managerRegistry->getRepository(Message::class);
        $contactUs = $contact_repo->findOneBy(['codeMessage'=> 'contact_Us']);
        return $this->render('contact_us/index.html.twig', [
            'controller_name' => 'ContactUsController',
            'contactUs' => $contactUs
        ]);
    }
}

