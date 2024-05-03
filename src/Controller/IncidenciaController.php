<?php

namespace App\Controller;

use App\Entity\Incidencia;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;


class IncidenciaController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/incidencia/nueva", name="nueva_incidencia", methods={"POST"})
     */
    public function nuevaIncidencia(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verificar si falta algún dato obligatorio
        if (!isset($data['asunto_reparacion']) || !isset($data['mensaje_reparacion']) || !isset($data['email']) || !isset($data['id_empleado'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Faltan datos en la solicitud'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar si el correo electrónico ya existe en la base de datos de usuario
        $usuarioExistente = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $data['email']]);
        if ($usuarioExistente === null) {
            return new JsonResponse(['status' => 'KO', 'message' => 'El correo electrónico no está registrado en la base de datos de usuario'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener el usuario con el id de cargo 2 o 3
        $usuarioCargo = $this->entityManager->getRepository(Usuario::class)->findOneBy(['id' => $data['id_empleado']]);
        if ($usuarioCargo === null || !in_array($usuarioCargo->getIdCargo(), [2, 3])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'El empleado no tiene el cargo requerido'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar si ya existe una incidencia activa asociada al cliente por su ID
        $incidenciaActiva = $this->entityManager->getRepository(Incidencia::class)->findOneBy(['clienteId' => $usuarioExistente->getId(), 'estado' => 'activo']);
        if ($incidenciaActiva !== null) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Ya tienes una incidencia activa. No puedes abrir una nueva'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Crear una nueva incidencia
        $incidencia = new Incidencia();

        // Establecer el estado de la incidencia como activa por defecto 
        $estado = $data['estado'] ?? 'activo';
        $incidencia->setEstado($estado);

        // Asignar datos de la incidencia
        $incidencia->setAsuntoReparacion($data['asunto_reparacion']);
        $incidencia->setMensajeReparacion($data['mensaje_reparacion']);

        // Verificar si la imagen está presente en la solicitud
        if ($request->files->has('imagen')) {
            $imagen = $request->files->get('imagen');
            // Verificar si la imagen tiene un formato admitido
            $formatoImagen = $imagen->guessExtension();
            if (!in_array($formatoImagen, ['jpg', 'jpeg', 'png'])) {
                return new JsonResponse(['status' => 'KO', 'message' => 'Formato de imagen no admitido. Los formatos admitidos son JPEG, JPG y PNG.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            // Guardar la imagen en el directorio de carga
            $nombreImagen = 'incidencia_' . $incidencia->getId() . '.' . $formatoImagen;
            try {
                $imagen->move($this->getParameter('ruta_directorio_carga_imagenes'), $nombreImagen);
            } catch (FileException $e) {
                return new JsonResponse(['status' => 'KO', 'message' => 'Error al cargar la imagen'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            // Almacenar la ruta de la imagen en la entidad de incidencia
            $rutaImagen = $this->getParameter('ruta_directorio_carga_imagenes') . '/' . $nombreImagen;
            $incidencia->setImagen($rutaImagen);
        } else {
            return new JsonResponse(['status' => 'KO', 'message' => 'La imagen no se ha proporcionado'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Asignar el usuario con el cargo 2 o 3 a la incidencia
        $incidencia->setEmpleado($usuarioCargo);

        // Asignar el ID del usuario existente a la incidencia
        $incidencia->setClienteId($usuarioExistente->getId());

        // Validar la incidencia utilizando el validador
        $errors = $validator->validate($incidencia);
        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => 'Los datos de la incidencia son inválidos', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Persistir la incidencia en la base de datos
        $this->entityManager->persist($incidencia);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Incidencia creada correctamente'], JsonResponse::HTTP_CREATED);
    }


    /**
     * @Route("/incidencias/ver", name="ver_incidencias_usuario_cargo_2", methods={"GET"})
     */
    public function verIncidenciasUsuarioCargo2(): JsonResponse
    {
        // Obtener el usuario con id_cargo 2
        $usuarioCargo2 = $this->entityManager->getRepository(Usuario::class)->findOneBy(['idCargo' => 2]);
        if ($usuarioCargo2 === null) {
            throw new AccessDeniedException('No se encontró un usuario con el cargo requerido');
        }

        // Obtener las incidencias asociadas al usuario con id_cargo 2
        $incidencias = $this->entityManager->getRepository(Incidencia::class)->findBy(['empleado' => $usuarioCargo2]);

        // Convertir las incidencias a un arreglo para la respuesta JSON
        $incidenciasArray = [];
        foreach ($incidencias as $incidencia) {
            $incidenciasArray[] = [
                'id' => $incidencia->getId(),
                'nombre_completo' => $incidencia->getNombreCompleto(),
                'asunto_reparacion' => $incidencia->getAsuntoReparacion(),
                'mensaje_reparacion' => $incidencia->getMensajeReparacion(),
                'estado' => $incidencia->getEstado(),
                'email' => $incidencia->getEmail(),
            ];
        }

        return new JsonResponse(['status' => 'OK', 'incidencias' => $incidenciasArray], JsonResponse::HTTP_OK);
    }
    
    /**
     * @Route("/actualizarTicket", name="actualizar_ticket", methods={"POST"})
     */
    public function actualizarTicket(Request $request, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Verificar si los datos esperados están presentes en el arreglo $data


        if (!isset($data['id']) || !isset($data['nuevoEstado'])) {
            return $this->json(['status' => 'KO', 'message' => 'Faltan datos requeridos para actualizar el ticket'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Buscar el ticket por su ID
        $ticket = $entityManager->getRepository(Incidencia::class)->find($data['id']);

        // Verificar si el ticket existe
        if (!$ticket) {
            return $this->json(['status' => 'KO', 'message' => 'No se encontró el ticket con el ID proporcionado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Actualizar el estado del ticket
        $ticket->setEstado($data['nuevoEstado']);

        // Validar el ticket actualizado utilizando el validador
        $errors = $validator->validate($ticket);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['status' => 'KO', 'message' => 'Los datos del ticket son inválidos', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Guardar los cambios en la base de datos
        $entityManager->flush();

        return $this->json(['status' => 'OK', 'message' => 'Estado del ticket actualizado correctamente'], JsonResponse::HTTP_OK);
    }
}
