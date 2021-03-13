<?php

namespace App\Entity;

use App\Repository\ClosedContractRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClosedContractRepository::class)
 */
class ClosedContractInfo
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $closedAt;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $closedBy;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $reason;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(\DateTimeInterface $closedAt): self
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getClosedBy(): ?string
    {
        return $this->closedBy;
    }

    public function setClosedBy(string $closedBy): self
    {
        $this->closedBy = $closedBy;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }
}
