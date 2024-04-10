<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Message
 *
 * @ORM\Table(name="message")
 * @ORM\Entity
 */
class Message
{
    /**
     * @var int
     *
     * @ORM\Column(name="id_message", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $idMessage;

    /**
     * @var string|null
     *
     * @ORM\Column(name="code_message", type="text", length=65535, nullable=true, options={"default"="NULL"})
     */
    private $codeMessage = 'NULL';

    /**
     * @var string|null
     *
     * @ORM\Column(name="body_message", type="text", length=65535, nullable=true, options={"default"="NULL"})
     */
    private $bodyMessage = 'NULL';
    public function getId(): ?int
    {
        return $this->idMessage;
    }

    public function getCodeMessage(): ?string
    {
        return $this->codeMessage;
    }

    public function setCodeMessage(?string $codeMessage): static
    {
        $this->codeMessage = $codeMessage;

        return $this;
    }

    public function getBodyMessage(): ?string
    {
        return $this->bodyMessage;
    }

    public function setBodyMessage(?string $bodyMessage): static
    {
        $this->bodyMessage = $bodyMessage;

        return $this;
    }


}