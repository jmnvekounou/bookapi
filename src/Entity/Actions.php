<?php

namespace App\Entity;

use App\Repository\ActionsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ActionsRepository::class)
 */
class Actions
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
     * @ORM\ManyToOne(targetEntity=SubRubric::class, inversedBy="actions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $subRubric;

    /**
     * @ORM\ManyToOne(targetEntity=Profile::class, inversedBy="userActions")
     */
    private $profile;

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

    public function getSubRubric(): ?SubRubric
    {
        return $this->subRubric;
    }

    public function setSubRubric(?SubRubric $subRubric): self
    {
        $this->subRubric = $subRubric;

        return $this;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): self
    {
        $this->profile = $profile;

        return $this;
    }
}
