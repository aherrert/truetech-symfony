<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Repository\UsuarioRepository; // Importa el repositorio de la entidad Usuario

use Symfony\Component\Validator\Validator\ValidatorInterface;

class UsuarioController extends AbstractController
{
    private $entityManager;
    private $usuarioRepository; // Define una propiedad para el repositorio de Usuario



    public function __construct(EntityManagerInterface $entityManager, UsuarioRepository $usuarioRepository)
    {
        $this->entityManager = $entityManager;
        $this->usuarioRepository = $usuarioRepository; // Inyecta el repositorio de Usuario

    }


    /*REGISTRO USUARIO*/
    /**
     * @Route("/registro", name="registro_usuario")
     */
    public function registro(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $usuario = new Usuario();

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['nombre']) || !isset($data['apellidos']) || !isset($data['email']) || !isset($data['password'])) {
        }
        $usuario->setNombre($data['nombre']);
        $usuario->setApellidos($data['apellidos']);
        $usuario->setEmail($data['email']);
        $usuario->setPassword($data['password']);

        // Establecer el rol automáticamente
        $usuario->setRol('usuario'); // Por ejemplo, aquí establecemos el rol como 'usuario'
        // $usuario->setRol('empleado'); // Por ejemplo, aquí establecemos el rol como 'usuario'
        // $usuario->setRol('administrador'); // Por ejemplo, aquí establecemos el rol como 'usuario'

        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => 'El correo electrónico ya está en uso'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $this->entityManager->persist($usuario);
        $this->entityManager->flush();
        return new JsonResponse(['status' => 'OK', 'message' => 'Usuario registrado correctamente'], JsonResponse::HTTP_OK);
    }


    /*LOGIN USUARIO*/
    /**
     * @Route("/login", name="login_usuario")
     */
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['email']) || !isset($data['password'])) {
        }
        // Buscar el usuario por su correo electrónico en el repositorio
        $usuario = $this->usuarioRepository->findOneBy(['email' => $data['email']]);
        if (!$usuario) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Correo electrónico no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }
        // Verificar la contraseña sin cifrar
        if ($usuario->getPassword() !== $data['password']) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Contraseña incorrecta'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        // Obtener el nombre del usuario y su rol
        $nombreUsuario = $usuario->getNombre();
        $rol = $usuario->getRol();
        // Mensaje de bienvenida según el rol con el nombre del usuario
        $mensajeBienvenida = '';
        switch ($rol) {
            case 'usuario':
                $mensajeBienvenida = '¡Bienvenido/a ' . $nombreUsuario . ', como usuario';
                break;
            case 'empleado':
                $mensajeBienvenida = '¡Hola ' . $nombreUsuario . ', bienvenido/a como empleado!';
                break;
            case 'administrador':
                $mensajeBienvenida = '¡Saludos ' . $nombreUsuario . ', bienvenido/a como administrador!';
                break;
            default:
                $mensajeBienvenida = '¡Hola ' . $nombreUsuario . ', bienvenido/a!';
                break;
        }
        return $this->json(['status' => 'OK', 'message' => $mensajeBienvenida], JsonResponse::HTTP_OK);
    }

    /*EDITAR USUARIO*/
    /**
     * @Route("/editarPerfil", name="editarPerfil")
     */
    public function editarPerfil(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['email']) || !isset($data['nombre']) || !isset($data['apellidos']) || !isset($data['password'])) {
        }
        // Obtener el usuario por su correo electrónico
        $usuario = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $data['email']]);
        // Verificar si el usuario existe
        if (!$usuario) {
            return $this->json(['status' => 'KO', 'message' => 'Usuario no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }
        // No permitir la modificación del correo electrónico
        if ($data['email'] !== $usuario->getEmail()) {
            return $this->json(['status' => 'KO', 'message' => 'No está permitido modificar el correo electrónico'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $usuario->setNombre($data['nombre']);
        $usuario->setApellidos($data['apellidos']);
        $usuario->setPassword($data['password']);
        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);
        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['status' => 'KO', 'message' => 'Los datos ingresados no son válidos'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();
        return $this->json(['status' => 'OK', 'message' => 'Perfil actualizado correctamente'], JsonResponse::HTTP_OK);
    }
}