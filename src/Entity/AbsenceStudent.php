<?php

namespace App\Entity;

use App\Repository\AbsenceStudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AbsenceStudentRepository::class)
 */
class AbsenceStudent
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Student::class, inversedBy="absences")
     * @ORM\JoinColumn(nullable=false)
     */
    private $student;

    /**
     * @ORM\OneToMany(targetEntity=AbsenceReason::class, mappedBy="absenceStudent")
     */
    private $reason;

    /**
     * @ORM\Column(type="datetime")
     */
    private $startdate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $enddate;

    /**
     * @ORM\Column(type="integer")
     */
    private $numberDay;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isNoShow;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isLong;

    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $createdBy;

    public function __construct()
    {
        $this->reason = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): self
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return Collection|AbsenceReason[]
     */
    public function getReason(): Collection
    {
        return $this->reason;
    }

    public function addReason(AbsenceReason $reason): self
    {
        if (!$this->reason->contains($reason)) {
            $this->reason[] = $reason;
            $reason->setAbsenceStudent($this);
        }

        return $this;
    }

    public function removeReason(AbsenceReason $reason): self
    {
        if ($this->reason->removeElement($reason)) {
            // set the owning side to null (unless already changed)
            if ($reason->getAbsenceStudent() === $this) {
                $reason->setAbsenceStudent(null);
            }
        }

        return $this;
    }

    public function getStartdate(): ?\DateTimeInterface
    {
        return $this->startdate;
    }

    public function setStartdate(\DateTimeInterface $startdate): self
    {
        $this->startdate = $startdate;

        return $this;
    }

    public function getEnddate(): ?\DateTimeInterface
    {
        return $this->enddate;
    }

    public function setEnddate(\DateTimeInterface $enddate): self
    {
        $this->enddate = $enddate;

        return $this;
    }

    public function getNumberDay(): ?int
    {
        return $this->numberDay;
    }

    public function setNumberDay(int $numberDay): self
    {
        $this->numberDay = $numberDay;

        return $this;
    }

    public function getIsNoShow(): ?bool
    {
        return $this->isNoShow;
    }

    public function setIsNoShow(bool $isNoShow): self
    {
        $this->isNoShow = $isNoShow;

        return $this;
    }

    public function getIsLong(): ?bool
    {
        return $this->isLong;
    }

    public function setIsLong(bool $isLong): self
    {
        $this->isLong = $isLong;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
