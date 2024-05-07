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
use Symfony\Component\Filesystem\Filesystem;



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
        // Obtener los datos del formulario
        $data = $request->request->all(); // Aquí se encuentran los datos del formulario

        // Verificar si falta algún dato obligatorio
        if (!isset($data['asunto_reparacion']) || !isset($data['mensaje_reparacion']) || !isset($data['email']) || !isset($data['id_cargo']) || !isset($data['token'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Faltan datos en la solicitud'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $idCargo = $data['id_cargo']; // Asegúrate de que estás accediendo correctamente al id_cargo

        // Obtener los usuarios con el id de cargo correspondiente
        $usuariosCargo = $this->entityManager->getRepository(Usuario::class)->findBy(['idCargo' => $idCargo]);

        if (empty($usuariosCargo)) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se encontraron usuarios con el cargo correspondiente'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar la validez del token JWT enviado por el cliente
        $token = $data['token'];

        try {
            // Decodificar el token JWT y verificar el correo electrónico
            $decodedToken = $this->decodeJwtToken1($token, $data['email']);
            $userId = $decodedToken['id']; // Obtener el ID del usuario
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Crear el directorio del usuario si no existe
        $filesystem = new Filesystem();
        $directoryPath = $this->getParameter('ruta_directorio_carga_imagenes') . '/' . 'id_cliente_' . $userId;
        if (!$filesystem->exists($directoryPath)) {
            $filesystem->mkdir($directoryPath);
        }

        // Seleccionar el usuario siguiente en la lista de usuarios con el cargo correspondiente
        $index = 0;
        $numUsuarios = count($usuariosCargo);
        foreach ($usuariosCargo as $index => $usuario) {
            if ($usuario->getId() !== $userId) {
                break;
            }
        }
        $index = ($index + 1) % $numUsuarios; // Avanzar al siguiente usuario
        $usuarioCargo = $usuariosCargo[$index];

        // Verificar si ya existe una incidencia activa asociada al cliente por su ID
        $incidenciaActiva = $this->entityManager->getRepository(Incidencia::class)->findOneBy(['clienteId' => $userId, 'estado' => 'activo']);
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

        // Asignar el usuario correspondiente a la incidencia
        $incidencia->setEmpleado($usuarioCargo);
        $incidencia->setClienteId($userId);

        // Verificar si la imagen está presente en la solicitud
        if ($request->files->has('imagen')) {
            $imagen = $request->files->get('imagen');
            // Verificar si la imagen tiene un formato admitido
            $formatoImagen = $imagen->guessExtension();
            $allowedFormats = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($formatoImagen, $allowedFormats)) {
                return new JsonResponse(['status' => 'KO', 'message' => 'Formato de imagen no admitido. Los formatos admitidos son JPEG, JPG, PNG y GIF.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // Verificar si el archivo ya existe
            $nombreImagen = pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME);
            $nombreImagen .= '_' . uniqid() . '.' . $formatoImagen;
            $rutaGuardadoImagen = $directoryPath . '/' . $nombreImagen; // Guardar la imagen dentro del directorio del usuario
            if (file_exists($rutaGuardadoImagen)) {
                return new JsonResponse(['status' => 'KO', 'message' => 'El archivo ya existe.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // Verificar el tamaño del archivo
            $maxFileSize = 500000; // 500 KB
            if ($imagen->getSize() > $maxFileSize) {
                return new JsonResponse(['status' => 'KO', 'message' => 'El archivo es demasiado grande. El tamaño máximo permitido es de 500 KB.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // Mover la imagen al directorio deseado
            try {
                $imagen->move($directoryPath, $nombreImagen);
            } catch (FileException $e) {
                return new JsonResponse(['status' => 'KO', 'message' => 'Error al cargar la imagen'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            // Almacenar la ruta de la imagen en la entidad de incidencia
            $incidencia->setImagen($rutaGuardadoImagen);
        } else {
            return new JsonResponse(['status' => 'KO', 'message' => 'La imagen no se ha proporcionado'], JsonResponse::HTTP_BAD_REQUEST);
        }

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
        try {
            $this->entityManager->persist($incidencia);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error al persistir la incidencia'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'OK', 'message' => 'Incidencia creada correctamente'], JsonResponse::HTTP_CREATED);
    }


    /**
     * @Route("/incidencias/usuario", name="listar_incidencias_usuario", methods={"POST"})
     */
    public function listarIncidenciasUsuario(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Obtener el token del encabezado de la solicitud
        $token = $request->headers->get('Authorization');

        // Verificar si se proporcionó el token
        if (!$token) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se proporcionó el token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Separar el token del prefijo "Bearer"
        $token = str_replace('Bearer ', '', $token);

        // Verificar la validez del token JWT enviado por el cliente
        try {
            // Decodificar el token JWT y obtener el ID del usuario
            $decodedToken = $this->decodeJwtToken2($token);
            $userId = $decodedToken['id'];
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener las incidencias asociadas al cliente_id (que es el userId en este caso)
        $incidencias = $this->entityManager->getRepository(Incidencia::class)->findBy(['clienteId' => $userId]);

        // Verificar si se encontraron incidencias
        if (empty($incidencias)) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se encontraron incidencias asociadas al cliente_id proporcionado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Convertir las incidencias a un arreglo para la respuesta JSON
        $incidenciasArray = [];
        foreach ($incidencias as $incidencia) {
            // Construir la ruta relativa para la imagen

            $incidenciasArray[] = [
                'id' => $incidencia->getId(),
                'asunto_reparacion' => $incidencia->getAsuntoReparacion(),
                'mensaje_reparacion' => $incidencia->getMensajeReparacion(),
                'estado' => $incidencia->getEstado(),
                'imagen' => $incidencia->getImagen(),
                // Agrega más campos si es necesario
            ];
        }

        return new JsonResponse(['status' => 'OK', 'incidencias' => $incidenciasArray], JsonResponse::HTTP_OK);
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
    private function decodeJwtToken1(string $token, string $email)
    {
        // Dividir el token en partes separadas
        $tokenParts = explode('.', $token);

        // Verificar si el token tiene tres partes
        if (count($tokenParts) !== 3) {
            throw new AccessDeniedException('Token inválido');
        }

        // Decodificar la segunda parte (payload) del token
        $payload = json_decode(base64_decode($tokenParts[1]), true);

        // Verificar si se pudo decodificar el payload
        if (!$payload) {
            throw new AccessDeniedException('Token inválido');
        }

        // Verificar si el token ha expirado
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new AccessDeniedException('Token expirado');
        }

        // Verificar si el correo electrónico coincide
        if (isset($payload['email']) && $payload['email'] !== $email) {
            throw new AccessDeniedException('El correo electrónico del token no coincide con el proporcionado');
        }

        // Verificar si se incluye el ID del usuario en el payload
        if (!isset($payload['id'])) {
            throw new AccessDeniedException('ID de usuario no encontrado en el token');
        }

        // Devolver el payload decodificado junto con el ID del usuario
        return [
            'id' => $payload['id'],
            'payload' => $payload
        ];
    }
    private function decodeJwtToken2(string $token)
    {
        // Dividir el token en partes separadas
        $tokenParts = explode('.', $token);

        // Verificar si el token tiene tres partes
        if (count($tokenParts) !== 3) {
            throw new AccessDeniedException('Token inválido');
        }

        // Decodificar la segunda parte (payload) del token
        $payload = json_decode(base64_decode($tokenParts[1]), true);

        // Verificar si se pudo decodificar el payload
        if (!$payload) {
            throw new AccessDeniedException('Token inválido');
        }

        // Verificar si el token ha expirado
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new AccessDeniedException('Token expirado');
        }


        // Verificar si se incluye el ID del usuario en el payload
        if (!isset($payload['id'])) {
            throw new AccessDeniedException('ID de usuario no encontrado en el token');
        }

        // Devolver el payload decodificado junto con el ID del usuario
        return [
            'id' => $payload['id'],
            'payload' => $payload
        ];
    }
}
