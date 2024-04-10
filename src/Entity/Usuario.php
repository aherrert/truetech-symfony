<?php

namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * Usuario
 *
 * @ORM\Table(name="usuario", uniqueConstraints={@ORM\UniqueConstraint(name="email", columns={"email"})}, indexes={@ORM\Index(name="id_cargo", columns={"id_cargo"})})
 * @ORM\Entity
 */
class Usuario
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string|null
     *
     * @ORM\Column(name="nombre", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $nombre = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="apellidos", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $apellidos = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $email = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="password", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $password = 'NULL';

    /**
     * @var int
     *
     * @ORM\Column(name="id_cargo", type="integer", nullable=false)
     */
    private $idCargo;

    /**
     * Get the value of id
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }
     /**
     * Get the value of email
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }
}
