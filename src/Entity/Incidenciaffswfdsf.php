<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\IncidenciaRepository")
 */
class Incidencia
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nombre_completo;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $asunto_reparacion;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $mensaje_reparacion;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $estado;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Usuario", inversedBy="incidencias")
     */
    private $usuario;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombreCompleto(): ?string
    {
        return $this->nombre_completo;
    }

    public function setNombreCompleto(string $nombre_completo): self
    {
        $this->nombre_completo = $nombre_completo;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAsuntoReparacion(): ?string
    {
        return $this->asunto_reparacion;
    }

    public function setAsuntoReparacion(string $asunto_reparacion): self
    {
        $this->asunto_reparacion = $asunto_reparacion;

        return $this;
    }

    public function getMensajeReparacion(): ?string
    {
        return $this->mensaje_reparacion;
    }

    public function setMensajeReparacion(string $mensaje_reparacion): self
    {
        $this->mensaje_reparacion = $mensaje_reparacion;

        return $this;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): self
    {
        $this->usuario = $usuario;

        return $this;
    }
}
