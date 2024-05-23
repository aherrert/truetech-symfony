<?php

namespace App\Controller;

use App\Entity\Incidencia;
use App\Entity\IncidenciaHistorial;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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

            // Crear un nuevo registro en la tabla incidencia_historial
            $incidenciaHistorial = new IncidenciaHistorial();
            $incidenciaHistorial->setIncidencia($incidencia);
            $incidenciaHistorial->setEstado($estado);

            // Persistir el historial de incidencia en la base de datos
            $this->entityManager->persist($incidenciaHistorial);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error al persistir la incidencia o el historial: ' . $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
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
     * @Route("/incidencias/empleado", name="listar_incidencias_empleado", methods={"POST"})
     */
    public function listarIncidenciasEmpleado(Request $request, ValidatorInterface $validator): JsonResponse
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
            // Decodificar el token JWT y obtener el ID del empleado y su rol
            $decodedToken = $this->decodeJwtToken3($token);
            $empleadoId = $decodedToken['id'];
            $rol = $decodedToken['rol'];
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar si el rol es igual a 2 (representando el rol del empleado)
        if ($rol !== 2) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No tiene permiso para acceder a esta funcionalidad'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Obtener las incidencias asociadas al empleado_id
        $incidencias = $this->entityManager->getRepository(Incidencia::class)->findBy(['empleado' => $empleadoId]);

        // Verificar si se encontraron incidencias
        if (empty($incidencias)) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se encontraron incidencias asociadas al empleado_id proporcionado'], JsonResponse::HTTP_NOT_FOUND);
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
     * @Route("/incidencias/empleado", name="listar_incidencias_empleado", methods={"POST"})
     */
    public function listarIncidenciasEmpleado2(Request $request, ValidatorInterface $validator): JsonResponse
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
            // Decodificar el token JWT y obtener el ID del empleado y su rol
            $decodedToken = $this->decodeJwtToken3($token);
            $empleadoId = $decodedToken['id'];
            $rol = $decodedToken['rol'];
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar si el rol es igual a 2 (representando el rol del empleado)
        if ($rol !== 3) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No tiene permiso para acceder a esta funcionalidad'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Obtener las incidencias asociadas al empleado_id
        $incidencias = $this->entityManager->getRepository(Incidencia::class)->findBy(['empleado' => $empleadoId]);

        // Verificar si se encontraron incidencias
        if (empty($incidencias)) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se encontraron incidencias asociadas al empleado_id proporcionado'], JsonResponse::HTTP_NOT_FOUND);
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
     * @Route("/incidencias/actualizar", name="actualizar_incidencia", methods={"POST"})
     */
    public function actualizarIncidencia(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Obtener el cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);

        // Obtener el token del cuerpo de la solicitud
        $token = $data['token'] ?? null;

        // Verificar si se proporcionó el token
        if (!$token) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se proporcionó el token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar la validez del token JWT enviado por el cliente
        try {
            // Decodificar el token JWT y obtener el ID del empleado y su rol
            $decodedToken = $this->decodeJwtToken3($token);
            $empleadoId = $decodedToken['id'];
            $rol = $decodedToken['rol'];
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar si el rol es igual a 2 (representando el rol del empleado)
        if ($rol !== 2) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No tiene permiso para acceder a esta funcionalidad'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Obtener el ID del ticket a actualizar y el nuevo estado del cuerpo de la solicitud
        $ticketId = $data['id'] ?? null;
        $nuevoEstado = $data['estado'] ?? null;

        // Verificar si se proporcionó el ID del ticket y el nuevo estado
        if ($ticketId === null || $nuevoEstado === null) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Se requiere proporcionar el ID del ticket y el nuevo estado'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener la incidencia asociada al ID del ticket
        $incidencia = $this->entityManager->getRepository(Incidencia::class)->find($ticketId);

        // Verificar si se encontró la incidencia
        if (!$incidencia) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se encontró la incidencia asociada al ID proporcionado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Actualizar el estado del ticket
        $incidencia->setEstado($nuevoEstado);

        // Crear una nueva entrada en incidencia_historial
        $historial = new IncidenciaHistorial();
        $historial->setIncidencia($incidencia);
        $historial->setEstado($nuevoEstado);

        // Persistir el historial en la base de datos
        $this->entityManager->persist($historial);

        // Guardar los cambios en la incidencia y el historial
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error al persistir la incidencia y el historial'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'OK', 'message' => 'Estado del ticket actualizado correctamente'], JsonResponse::HTTP_OK);
    }


    /**
     * @Route("/incidencias/actualizar", name="actualizar_incidencia", methods={"POST"})
     */
    public function actualizarIncidencia2(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Obtener el cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);

        // Obtener el token del cuerpo de la solicitud
        $token = $data['token'] ?? null;

        // Verificar si se proporcionó el token
        if (!$token) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se proporcionó el token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar la validez del token JWT enviado por el cliente
        try {
            // Decodificar el token JWT y obtener el ID del empleado y su rol
            $decodedToken = $this->decodeJwtToken3($token);
            $empleadoId = $decodedToken['id'];
            $rol = $decodedToken['rol'];
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar si el rol es igual a 2 (representando el rol del empleado)
        if ($rol !== 3) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No tiene permiso para acceder a esta funcionalidad'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Obtener el ID del ticket a actualizar y el nuevo estado del cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);
        $ticketId = $data['id'] ?? null;
        $nuevoEstado = $data['estado'] ?? null;

        // Verificar si se proporcionó el ID del ticket y el nuevo estado
        if ($ticketId === null || $nuevoEstado === null) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Se requiere proporcionar el ID del ticket y el nuevo estado'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener la incidencia asociada al ID del ticket
        $incidencia = $this->entityManager->getRepository(Incidencia::class)->find($ticketId);

        // Verificar si se encontró la incidencia
        if (!$incidencia) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No se encontró la incidencia asociada al ID proporcionado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Actualizar el estado del ticket
        $incidencia->setEstado($nuevoEstado);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Estado del ticket actualizado correctamente'], JsonResponse::HTTP_OK);
    }


    /**
     * @Route("/incidencias/historial", name="listar_historial_incidencias", methods={"POST"})
     */
    public function listarHistorialIncidencias(Request $request): Response
    {
        // Obtener los datos JSON de la solicitud
        $data = json_decode($request->getContent(), true);

        // Verificar si se proporcionó el ID y el token
        if (!isset($data['id']) || !isset($data['token'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Se requiere proporcionar el ID y el token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $id = $data['id'];
        $token = $data['token'];

        // Verificar la validez del token JWT enviado por el cliente
        try {
            $decodedToken = $this->decodeJwtToken3($token);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Error en la decodificación del token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener el ID del empleado y su rol del token decodificado
        $empleadoId = $decodedToken['id'];
        $rol = $decodedToken['rol'];

        // Verificar si el rol es igual a 2 (representando el rol del empleado)
        if ($rol !== 2 && $rol !== 3) {
            return new JsonResponse(['status' => 'KO', 'message' => 'No tiene permiso para acceder a esta funcionalidad'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Obtener el historial de incidencias asociado al ID de incidencia
        $historialIncidencias = $this->entityManager->getRepository(IncidenciaHistorial::class)->findBy(['incidencia' => $id]);

        // Convertir el historial de incidencias a un arreglo para la respuesta JSON
        $historialArray = [];
        foreach ($historialIncidencias as $historial) {
            $historialArray[] = [
                'id' => $historial->getId(),
                'incidencia_id' => $historial->getIncidencia()->getId(),
                'estado' => $historial->getEstado(),
                'created_at' => $historial->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $historial->getUpdatedAt()->format('Y-m-d H:i:s')
            ];
        }

        return new JsonResponse(['status' => 'OK', 'historial_incidencias' => $historialArray], JsonResponse::HTTP_OK);
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

    
    private function decodeJwtToken3(string $token)
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

        // Verificar si se incluye el ID del usuario y el rol en el payload
        if (!isset($payload['id']) || !isset($payload['rol'])) {
            throw new AccessDeniedException('ID de usuario o rol no encontrados en el token');
        }

        // Devolver el payload decodificado junto con el ID del usuario y el rol
        return [
            'id' => $payload['id'],
            'rol' => $payload['rol'],
            'payload' => $payload
        ];
    }
}
