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
use Firebase\JWT\JWT;

use Symfony\Component\Validator\Validator\ValidatorInterface;



use Symfony\Component\Mailer\MailerInterface;


// require_once './vendor/autoload.php';

use Symfony\Config\Framework\MailerConfig;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// require_once './vendor/autoload.php';
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

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

        // Verificar si el correo electrónico ya está en uso
        $existingUser = $this->usuarioRepository->findOneByEmail($data['email']);
        if ($existingUser) {
            return new JsonResponse(['status' => 'KO', 'message' => 'El correo electrónico ya está en uso'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $usuario->setNombre($data['nombre']);
        $usuario->setApellidos($data['apellidos']);
        $usuario->setEmail($data['email']);
        $usuario->setPassword($data['password']);

        // Establecer el rol automáticamente
        $usuario->setIdCargo(3); // Por ejemplo, aquí establecemos el rol como 'usuario'
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


    /**
     * @Route("/login", name="login_usuario")
     */
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Datos incompletos'], JsonResponse::HTTP_BAD_REQUEST);
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
        $rol = $usuario->getIdCargo();

        // Crear el payload del token
        $payload = [
            'id' => $usuario->getId(),
            'email' => $usuario->getEmail(),
            'nombre' => $nombreUsuario,
            'apellidos' => $usuario->getApellidos(),
            'rol' => $rol,
            'exp' => time() + 3600 // 3600 El token expira en una hora (puedes ajustar este valor según tu necesidad)
        ];

        // Firmar el token JWT
        $token = JWT::encode($payload, 'tu_clave_secreta', 'HS256');

        // Mensaje de bienvenida según el rol con el nombre del usuario
        $mensajeBienvenida = '';
        switch ($rol) {
            case '4':
                $mensajeBienvenida = '¡Bienvenido/a ' . $nombreUsuario . ', como usuario!';
                break;
            case '3':
                $mensajeBienvenida = '¡Hola ' . $nombreUsuario . ', bienvenido/a como empleado hardware!';
                break;
            case '2':
                $mensajeBienvenida = '¡Saludos ' . $nombreUsuario . ', bienvenido/a como empleado software!';
                break;
            case '1':
                $mensajeBienvenida = '¡Saludos ' . $nombreUsuario . ', bienvenido/a como administrador!';
                break;
            default:
                $mensajeBienvenida = '¡Hola ' . $nombreUsuario . ', bienvenido/a!';
                break;
        }

        // Devolver el token en la respuesta
        return new JsonResponse(['status' => 'OK', 'token' => $token, 'message' => $mensajeBienvenida], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/editarPerfil", name="editarPerfil")
     */
    public function editarPerfil(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['email']) || !isset($data['token'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Datos incompletos'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar la validez del token JWT enviado por el cliente
        $token = $data['token'];

        try {
            // Decodificar el token JWT y verificar el correo electrónico
            $decodedToken = $this->decodeJwtToken($token, $data['email']);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Obtener el usuario por su correo electrónico
        $usuario = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $data['email']]);

        // Verificar si el usuario existe
        if (!$usuario) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Usuario no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Actualizar el nombre si se proporciona en los datos
        if (isset($data['nombre'])) {
            $usuario->setNombre($data['nombre']);
        }

        // Actualizar los apellidos si se proporcionan en los datos
        if (isset($data['apellidos'])) {
            $usuario->setApellidos($data['apellidos']);
        }

        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => 'Los datos ingresados no son válidos', 'errors' => $errorMessages], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Perfil actualizado correctamente'], JsonResponse::HTTP_OK);
    }


    /**
     * @Route("/cambiarcontraseña", name="cambiar_contraseña")
     */
    public function cambiarContraseña(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['token']) || !isset($data['password']) || !isset($data['newPassword'])|| !isset($data['email'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Datos incompletos'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Verificar la validez del token JWT enviado por el cliente
        $token = $data['token'];

        try {
            // Decodificar el token JWT y verificar el correo electrónico
            $decodedToken = $this->decodeJwtToken($token, $data['email']);
        } catch (AccessDeniedException $e) {
            return new JsonResponse(['status' => 'KO', 'message' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Obtener el usuario por su correo electrónico
        $usuario = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $decodedToken['email']]);

        // Verificar si el usuario existe
        if (!$usuario) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Usuario no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Verificar si la contraseña actual coincide con la almacenada en la base de datos
        if ($usuario->getPassword() !== $data['password']) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Contraseña actual incorrecta'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Establecer la nueva contraseña
        $usuario->setPassword($data['newPassword']);

        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => 'Los datos ingresados no son válidos'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Contraseña actualizada correctamente'], JsonResponse::HTTP_OK);
    }


    private function decodeJwtToken(string $token, string $email)
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

        // Devolver el payload decodificado
        return $payload;
    }
    /**
     * @Route("/resetpassword", name="actualizar_contrasenya")
     */
    public function resetpassword(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['email']) || !isset($data['password'])||!isset($data['id'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Datos incompletos'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener el usuario por su correo electrónico
        $usuario = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $data['email']]);

        // Verificar si el usuario existe
        if (!$usuario) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Usuario no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }
        if ($usuario->getId() !== $data['id']) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Contraseña incorrecta'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        // Permitir la modificación de la contra

        $usuario->setPassword($data['password']);

        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => 'Los datos ingresados no son válidos'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'OK', 'message' => 'Perfil actualizado correctamente'], JsonResponse::HTTP_OK);
    }

    /**
     * @Route("/enviarcorreo", name="enviar_correo")
     */
    public function enviarcorreo(Request $request, ValidatorInterface $validator, MailerInterface $mailer): JsonResponse
    {

        $data = json_decode($request->getContent(), true);

        // Verificar si los datos esperados están presentes en el arreglo $data
        if (!isset($data['email'])) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Datos incompletos'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Obtener el usuario por su correo electrónico
        $usuario = $this->entityManager->getRepository(Usuario::class)->findOneBy(['email' => $data['email']]);
        // Verificar si el usuario existe
        if (!$usuario ==$id) {
            return new JsonResponse(['status' => 'KO', 'message' => 'Usuario no encontrado'], JsonResponse::HTTP_NOT_FOUND);
        }


        // Validar el usuario utilizando el validador
        $errors = $validator->validate($usuario);

        if (count($errors) > 0) {
            // Construir un arreglo con los mensajes de error
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['status' => 'KO', 'message' => 'Los datos ingresados no son válidos'], JsonResponse::HTTP_BAD_REQUEST);
        }
        // creant un objecte Transporte
        $transport = Transport::fromDsn('smtp://truetrech.s.a@gmail.com:prnkijuhefdtmfku@smtp.gmail.com:587');

        // creant un objecte Mailer
        $mailer = new Mailer($transport);

        // creant un objecte Email
        $email = (new Email());

        // establiu l'adreça "des de"
        $email->from('truetrech.s.a@gmail.com');

        //establir "a l'adreça"
        $email->to($usuario->getEmail());
        $id = ($usuario->getId());
        // establir un "assumpte"
        $email->subject('Cambiar Contraseña TRUETECH');

        // establiu el "cos" de text sense format
        $email->text(
            'Este enlace sirve para cambiar la contraseña de nuestra página web: ' .
                ' http://localhost:4200/resetpassword ' .
                ' identificador para verificar el usuario que quieres cambiar la contraseña: '.$id
        );

        // Envia un correu electrònic
        $mailer->send($email);

        // // $destino="truetrech.s.a@gmail.com";
        // //$contenido="hola:";
        // // mail($usuario,"contacto",$contenido);
        // // mail($usuario->getEmail(),"contacto",$contenido);
        // // header("");
        // // configuracion con JOSE PORTUGAL
        //     // $email = (new Email());
        //     // $email->to($usuario->getEmail());
        //     // $email->replyTo('mailtrap@truetech.com');
        //     // $mailer->send($email);
        // //CONFIGURACIO CON ADRIA NIVI


        //     $to = $usuario->getEmail();
        //     $subject = "la teva contrasenya";
        //     $from="truetrech.s.a@gmail.com";
        //     mail($to , $subject , $from);



        return new JsonResponse(['status' => 'OK', 'message' => 'Perfil actualizado correctamente'], JsonResponse::HTTP_OK);
    }
}
