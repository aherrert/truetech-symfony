<?php

namespace App\Entity;
use App\Entity\Usuario;


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

    /**
     * @var string|null
     *
     * @ORM\Column(name="asunto_reparacion", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $asuntoReparacion = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="mensaje_reparacion", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $mensajeReparacion = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="estado", type="string", length=255, nullable=true, options={"default"="NULL"})
     */
    private $estado = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="imagen", type="string", length=255, nullable=true)
     */
    private $imagen;

    /**
     * @var \Usuario
     *
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="empleado_id", referencedColumnName="id")
     * })
     */
    private $empleado;/**
    * @var int|null
    *
    * @ORM\Column(name="cliente_id", type="integer", nullable=true)
    */
   private $clienteId;



    // Getters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getImagen(): ?string
    {
        return $this->imagen;
    }

    public function getClienteId(): ?int
    {
        return $this->clienteId;
    }

    // Setters

    public function setAsuntoReparacion(?string $asuntoReparacion): self
    {
        $this->asuntoReparacion = $asuntoReparacion;

        return $this;
    }

    public function setMensajeReparacion(?string $mensajeReparacion): self
    {
        $this->mensajeReparacion = $mensajeReparacion;

        return $this;
    }

    public function setEstado(?string $estado): self
    {  
        $this->estado = $estado;

        return $this;
    }

    public function setImagen(?string $imagen): self
    {
        $this->imagen = $imagen;
        return $this;
    }

    public function setEmpleado(?Usuario $empleado): self
    {
        $this->empleado = $empleado;

        return $this;
    }
    public function setClienteId(?int $clienteId): self
    {
        $this->clienteId = $clienteId;
    
        return $this;
    }
}
