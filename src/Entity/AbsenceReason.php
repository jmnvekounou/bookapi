<?php

namespace App\Entity;

use App\Repository\AbsenceReasonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AbsenceReasonRepository::class)
 */
class AbsenceReason
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
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity=AbsenceStudent::class, inversedBy="reason")
     */
    private $absenceStudent;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAbsenceStudent(): ?AbsenceStudent
    {
        return $this->absenceStudent;
    }

    public function setAbsenceStudent(?AbsenceStudent $absenceStudent): self
    {
        $this->absenceStudent = $absenceStudent;

        return $this;
    }
}
