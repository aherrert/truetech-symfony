<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Repository\UsuarioRepository;


use Symfony\Component\Validator\Validator\ValidatorInterface;

class AdminController extends AbstractController
{
    private $entityManager;
    private $usuarioRepository; // Define una propiedad para el repositorio de Usuario

    public function __construct(EntityManagerInterface $entityManager, UsuarioRepository $usuarioRepository)
    {
        $this->entityManager = $entityManager;
        $this->usuarioRepository = $usuarioRepository; // Inyecta el repositorio de Usuario
    }

    /**
     * @Route("/registro", name="registro_admin")
     */
    public function registro_admin(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $usuario = new Usuario();

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['nombre']) || !isset($data['apellidos']) || !isset($data['email']) || !isset($data['password']) || !isset($data['id_cargo']) || !isset($data['token'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Faltan datos requeridos para el registro'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar la validez del token JWT enviado por el cliente
        $token = $data['token'];

        try {
            // Decodificar el token JWT
            $decodedToken = $this->decodeJwtToken($token);

            // Verificar si el token contiene la información necesaria (por ejemplo, el correo electrónico)
            if (!isset($decodedToken['email'])) {
                throw new AccessDeniedException('Token inválido: falta información del usuario');
            }
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Verificar si el correo electrónico ya está en uso
        $existingUser = $this->usuarioRepository->findOneByEmail($data['email']);
        if ($existingUser) {
            return new JsonResponse(['status' => 'KO', 'message' => 'El correo electrónico ya está en uso'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $usuario->setNombre($data['nombre']);
        $usuario->setApellidos($data['apellidos']);
        $usuario->setEmail($data['email']);
        $usuario->setPassword($data['password']);
        $usuario->setIdCargo($data['id_cargo']);

        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Usuario registrado correctamente'], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/usuarios", name="obtenerUsuariosAdmin", methods={"GET"})
     */
    public function obtenerUsuariosAdmin(): JsonResponse
    {

        // Obtener todos los usuarios de la base de datos
        $usuarios = $this->entityManager->getRepository(Usuario::class)->findAll();

        // Convertir los usuarios a un arreglo para la respuesta JSON
        $usuariosArray = [];
        foreach ($usuarios as $usuario) {
            $usuariosArray[] = [
                'id' => $usuario->getId(),
                'nombre' => $usuario->getNombre(),
                'apellidos' => $usuario->getApellidos(),
                'email' => $usuario->getEmail(),
                'password' => $usuario->getPassword(),
                'id_cargo' => $usuario->getIdCargo()
            ];
        }

        return new JsonResponse(['status' => 'OK', 'usuarios' => $usuariosArray], JsonResponse::HTTP_OK);
    }
    /**
     * @Route("/eliminar-usuario", name="eliminar_usuario", methods={"DELETE"})
     */
    public function eliminarUsuario(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Decodificar el contenido JSON de la solicitud
        $data = json_decode($request->getContent(), true);

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['token']) || !isset($data['id'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Faltan datos requeridos para eliminar el usuario'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener el token del arreglo de datos
        $token = $data['token'];

        try {
            // Decodificar el token JWT
            $decodedToken = $this->decodeJwtToken($token);

            // Verificar si el token contiene la información necesaria (por ejemplo, el correo electrónico)
            if (!isset($decodedToken['email'])) {
                throw new AccessDeniedException('Token inválido: falta información del usuario');
            }
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Obtener el ID del usuario del arreglo de datos
        $id = $data['id'];

        // Obtener el usuario por su ID
        $usuario = $this->entityManager->getRepository(Usuario::class)->find($id);

        // Verificar si el usuario existe
        if (!$usuario) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Usuario no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Eliminar el usuario
        $this->entityManager->remove($usuario);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Usuario eliminado correctamente'], JsonResponse::HTTP_OK);
    }

    private function decodeJwtToken(string $token)
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

        // Verificar el rol del usuario
        $rol = $payload['rol'];
        if ($rol !== 1) {
            throw new AccessDeniedException('No tienes permiso para realizar esta acción');
        }

        // Devolver el payload decodificado
        return $payload;
    }
}
