<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Departamento;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\JsonResponse;


class DepartamentosController extends AbstractController
{
    /**
     * @Route("/departamentos", name="departamento_listar")
     */
    public function listarDepartamentos(ManagerRegistry $managerRegistry, Request $request): Response
    {
        $departamentos = $managerRegistry->getRepository(Departamento::class)->findAll();

        return $this->render('departamentos/listar.html.twig', ['departamentos' => $departamentos]);
    }

    /**
     * @Route("/departamentos/json", name="departamento_json")
     */
    public function listarDepartamentosJson(ManagerRegistry $managerRegistry, Request $request): JsonResponse
    {
    
        $departamentos = $managerRegistry->getRepository(Departamento::class)->findAll();

        $departamentosArray = [];
        foreach($departamentos as $departamento){
            $departamentosArray[]=[
                'id_DepartamentoS' => $departamento->getId(),
                'nombre' => $departamento->getNombre(),
            ];
        }
        return new JsonResponse($departamentosArray);
    }

    public function listarDepartamentosId($id, ManagerRegistry $managerRegistry): JsonResponse
    {
        $departamento = $managerRegistry->getRepository(Departamento::class)->find($id);

        if (!$departamento) {
            throw $this->createNotFoundException('Departamento no encontrado');
        }

        $departamentoArray = [
            'id_Departamento' => $departamento->getId(),
            'nombre' => $departamento->getNombre(),


        ];

        return new JsonResponse($departamentoArray);
    }



    /**
     * @Route("/departamento/nuevo", name="departamento_nuevo")
     */
    public function nuevoDepartamento(Request $request, ManagerRegistry $managerRegistry): Response
    {
        if ($request->isMethod('POST')) {
            $nombre = $request->request->get('nombre');


            $this->createDepartamento($managerRegistry, $nombre);

            return $this->redirectToRoute('departamento_listar');
        }

        return $this->render('departamento/nuevo.html.twig');
    }

    private function createDepartamento(ManagerRegistry $managerRegistry, $nombre): void
    {
        $cliente = new Departamento();
        $cliente->setNombre($nombre);

        $entityManager = $managerRegistry->getManager();
        $entityManager->persist($cliente);
        $entityManager->flush();
    }

    /**
     * @Route("/departamento/actualizar/{id}", name="departamento_actualizar")
     */
    public function actualizarDepartamento(Request $request, ManagerRegistry $managerRegistry, ValidatorInterface $validator, $id): Response
    {
        $entityManager = $managerRegistry->getManager();
        $departamento = $entityManager->getRepository(Departamento::class)->find($id);

        if (!$departamento) {
            throw $this->createNotFoundException('No se encontró el departamento con el ID ' . $id);
        }

        $form = $this->createFormBuilder($departamento)
            ->add('nombre')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('departamento_listar');
        }

        return $this->render('departamentos/actualizar.html.twig', [
            'departamento' => $departamento,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * @Route("/departamento/borrar/{id}", name="departamento_borrar")
     */
    public function borrarDepartamento(Request $request, ManagerRegistry $managerRegistry, $id): Response
    {
        $entityManager = $managerRegistry->getManager();
        $departamento = $entityManager->getRepository(Departamento::class)->find($id);

        if (!$departamento) {
            throw $this->createNotFoundException('No se encontró el departamento con el ID ' . $id);
        }

        $entityManager->remove($departamento);
        $entityManager->flush();

        // Puedes redirigir a la lista de departamento u otra página después de borrar
        return $this->redirectToRoute('departamento_listar');
    }





}
