<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Cliente;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\JsonResponse;


class ClienteController extends AbstractController
{
    /**
     * @Route("/clientes", name="cliente_listar")
     */
    public function listarClientes(ManagerRegistry $managerRegistry, Request $request): Response
    {
        $clientes = $managerRegistry->getRepository(Cliente::class)->findAll();

        return $this->render('cliente/listar.html.twig', ['clientes' => $clientes]);
    }

    /**
     * @Route("/clientes/json", name="cliente_json")
     */
    public function listarClientesJson(ManagerRegistry $managerRegistry, Request $request): JsonResponse
    {
    
        $clientes = $managerRegistry->getRepository(Cliente::class)->findAll();

        $clientesArray = [];
        foreach($clientes as $cliente){
            $clientesArray[]=[
                'id' => $cliente->getId(),
                'nombre' => $cliente->getNombre(),
                'apellidos' => $cliente->getApellidos(),
                'email' => $cliente->getEmail(),
                'fechaNacimiento' => $cliente->getFechaNacimiento(),
                'direccion' => $cliente->getDireccion(),

            ];

        }
        return new JsonResponse($clientesArray);
    }
    public function listarClientesId($id, ManagerRegistry $managerRegistry): JsonResponse
    {
        $cliente = $managerRegistry->getRepository(Cliente::class)->find($id);

        if (!$cliente) {
            throw $this->createNotFoundException('Cliente no encontrado');
        }

        $clienteArray = [
            'id' => $cliente->getId(),
            'nombre' => $cliente->getNombre(),
            'apellidos' => $cliente->getApellidos(),
            'email' => $cliente->getEmail(),
            'fechaNacimiento' => $cliente->getFechaNacimiento(),
            'direccion' => $cliente->getDireccion()

        ];

        return new JsonResponse($clienteArray);
    }


    /**
     * @Route("/clientes/nuevo", name="cliente_nuevo")
     */
    public function nuevoCliente(Request $request, ManagerRegistry $managerRegistry, ValidatorInterface $validator): Response
    {
        if ($request->isMethod('POST')) {
            $nombre = $request->request->get('nombre');
            $apellidos = $request->request->get('apellidos');
            $email = $request->request->get('email');
            $fechaNacimiento = $request->request->get('fecha_nacimiento');
            $direccion = $request->request->get('direccion');

            // Validar el formato del correo electrónico
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Manejar el error de formato de correo electrónico incorrecto
                return $this->render('cliente/nuevo.html.twig', ['errorEmail' => 'El formato del correo electrónico no es válido']);
            }

            // Validar los datos antes de crear el cliente (sin validaciones Symfony)

            // Si la validación es exitosa, crear el cliente
            $this->createCliente($managerRegistry, $nombre, $apellidos, $email, $fechaNacimiento, $direccion);

            return $this->redirectToRoute('cliente_listar');
        }

        return $this->render('cliente/nuevo.html.twig');
    }

    private function createCliente(ManagerRegistry $managerRegistry, $nombre, $apellidos, $email, $fechaNacimiento, $direccion): void
    {
        $cliente = new Cliente();
        $cliente->setNombre($nombre);
        $cliente->setApellidos($apellidos);
        $cliente->setEmail($email);
        $cliente->setFechaNacimiento(new \DateTime($fechaNacimiento));
        $cliente->setDireccion($direccion);

        $entityManager = $managerRegistry->getManager();
        $entityManager->persist($cliente);
        $entityManager->flush();
    }

    /**
     * @Route("/clientes/actualizar/{id}", name="cliente_actualizar")
     */
    public function actualizarCliente(Request $request, ManagerRegistry $managerRegistry, ValidatorInterface $validator, $id): Response
    {
        $entityManager = $managerRegistry->getManager();
        $cliente = $entityManager->getRepository(Cliente::class)->find($id);

        if (!$cliente) {
            throw $this->createNotFoundException('No se encontró el cliente con el ID ' . $id);
        }

        $form = $this->createFormBuilder($cliente)
            ->add('nombre')
            ->add('apellidos')
            ->add('email')
            ->add('fechaNacimiento', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
            ])
            ->add('direccion')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('cliente_listar');
        }

        return $this->render('cliente/actualizar.html.twig', [
            'cliente' => $cliente,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * @Route("/clientes/borrar/{id}", name="cliente_borrar")
     */
    public function borrarCliente(Request $request, ManagerRegistry $managerRegistry, $id): Response
    {
        $entityManager = $managerRegistry->getManager();
        $cliente = $entityManager->getRepository(Cliente::class)->find($id);

        if (!$cliente) {
            throw $this->createNotFoundException('No se encontró el cliente con el ID ' . $id);
        }

        $entityManager->remove($cliente);
        $entityManager->flush();

        // Puedes redirigir a la lista de clientes u otra página después de borrar
        return $this->redirectToRoute('cliente_listar');
    }



}
