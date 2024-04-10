<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Incidencia
 *
 * @ORM\Table(name="incidencia", indexes={@ORM\Index(name="empleado_id", columns={"empleado_id"})})
 * @ORM\Entity
 */
class Incidencia
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;
    public function getId(): ?int
    {
        return $this->id;
    } 
    /**
     * @var string|null
     *
     * @ORM\Column(name="nombre_completo", type="string", length=255, nullable=true)
     */
    private $nombreCompleto;
    public function getNombreCompleto(): ?string
    {
        return $this->nombreCompleto;
    }
    /**
     * @var string|null
     *
     * @ORM\Column(name="asunto_reparacion", type="string", length=255, nullable=true)
     */
    private $asuntoReparacion;

    /**
     * @var string|null
     *
     * @ORM\Column(name="mensaje_reparacion", type="string", length=255, nullable=true)
     */
    private $mensajeReparacion;

    /**
     * @var string
     *
     * @ORM\Column(name="estado", type="string", length=255, nullable=false, options={"default"="activo"})
     */
    private $estado = 'activo'; // Por defecto, el estado se establece en "activo"


    /**
     * @var string|null
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=true)
     */
    private $email;

    /**
     * @var \Usuario
     *
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="empleado_id", referencedColumnName="id")
     * })
     */
    private $empleado;

    public function setEmpleado($empleado)
    {
        $this->empleado = $empleado;
    }

    /**
     * Set the value of email
     *
     * @param string|null $email
     * @return self
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Set the value of nombreCompleto
     *
     * @param string|null $nombreCompleto
     * @return self
     */
    public function setNombreCompleto(?string $nombreCompleto): self
    {
        $this->nombreCompleto = $nombreCompleto;

        return $this;
    }

    /**
     * Set the value of asuntoReparacion
     *
     * @param string|null $asuntoReparacion
     * @return self
     */
    public function setAsuntoReparacion(?string $asuntoReparacion): self
    {
        $this->asuntoReparacion = $asuntoReparacion;

        return $this;
    }

    /**
     * Set the value of mensajeReparacion
     *
     * @param string|null $mensajeReparacion
     * @return self
     */
    public function setMensajeReparacion(?string $mensajeReparacion): self
    {
        $this->mensajeReparacion = $mensajeReparacion;

        return $this;
    }

    /**
     * Set the value of estado
     *
     * @param string|null $estado
     * @return self
     */
    public function setEstado(?string $estado): self
    {
        $this->estado = $estado;

        return $this;
    }
    public function getAsuntoReparacion(): ?string
    {
        return $this->asuntoReparacion;
    }

    public function getMensajeReparacion(): ?string
    {
        return $this->mensajeReparacion;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
