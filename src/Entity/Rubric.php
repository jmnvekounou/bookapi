<?php

namespace App\Entity;

use App\Repository\RubricRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RubricRepository::class)
 */
class Rubric
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
     * @ORM\OneToMany(targetEntity=SubRubric::class, mappedBy="rubric", orphanRemoval=true)
     */
    private $subRubrics;

    public function __construct()
    {
        $this->subRubrics = new ArrayCollection();
    }

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

    /**
     * @return Collection|SubRubric[]
     */
    public function getSubRubrics(): Collection
    {
        return $this->subRubrics;
    }

    public function addSubRubric(SubRubric $subRubric): self
    {
        if (!$this->subRubrics->contains($subRubric)) {
            $this->subRubrics[] = $subRubric;
            $subRubric->setRubric($this);
        }

        return $this;
    }

    public function removeSubRubric(SubRubric $subRubric): self
    {
        if ($this->subRubrics->removeElement($subRubric)) {
            // set the owning side to null (unless already changed)
            if ($subRubric->getRubric() === $this) {
                $subRubric->setRubric(null);
            }
        }

        return $this;
    }
}
