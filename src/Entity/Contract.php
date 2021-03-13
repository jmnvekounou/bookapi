<?php

namespace App\Entity;

use App\Repository\ContractRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ContractRepository::class)
 */
class Contract
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
    private $reference;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $orderNumber;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $contractNumber;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $contractdate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $methodofsupply;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $location;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $address;

    /**
     * @ORM\Column(type="date")
     */
    private $startdate;

    /**
     * @ORM\Column(type="date")
     */
    private $enddate;

    /**
     * @ORM\Column(type="integer")
     */
    private $hourlyRate;

    /**
     * @ORM\Column(type="integer")
     */
    private $hourNumber;

    /**
     * @ORM\Column(type="float")
     */
    private $estimatedCost;

    /**
     * @ORM\Column(type="float")
     */
    private $totalHour;

    /**
     * @ORM\Column(type="float")
     */
    private $totalCost;

    /**
     * @ORM\Column(type="float")
     */
    private $initialHour;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $usedHourDate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $timeType;

    /**
     * @ORM\Column(type="date")
     */
    private $initialEndDate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $receivedAt;

    /**
     * @ORM\Column(type="smallint")
     */
    private $status;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="boolean")
     */
    private $confirmationSent;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function getContractNumber(): ?string
    {
        return $this->contractNumber;
    }

    public function setContractNumber(string $contractNumber): self
    {
        $this->contractNumber = $contractNumber;

        return $this;
    }

    public function getContractdate(): ?\DateTimeInterface
    {
        return $this->contractdate;
    }

    public function setContractdate(?\DateTimeInterface $contractdate): self
    {
        $this->contractdate = $contractdate;

        return $this;
    }

    public function getMethodofsupply(): ?string
    {
        return $this->methodofsupply;
    }

    public function setMethodofsupply(?string $methodofsupply): self
    {
        $this->methodofsupply = $methodofsupply;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

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

    public function getHourlyRate(): ?int
    {
        return $this->hourlyRate;
    }

    public function setHourlyRate(int $hourlyRate): self
    {
        $this->hourlyRate = $hourlyRate;

        return $this;
    }

    public function getHourNumber(): ?int
    {
        return $this->hourNumber;
    }

    public function setHourNumber(int $hourNumber): self
    {
        $this->hourNumber = $hourNumber;

        return $this;
    }

    public function getEstimatedCost(): ?float
    {
        return $this->estimatedCost;
    }

    public function setEstimatedCost(float $estimatedCost): self
    {
        $this->estimatedCost = $estimatedCost;

        return $this;
    }

    public function getTotalHour(): ?float
    {
        return $this->totalHour;
    }

    public function setTotalHour(float $totalHour): self
    {
        $this->totalHour = $totalHour;

        return $this;
    }

    public function getTotalCost(): ?float
    {
        return $this->totalCost;
    }

    public function setTotalCost(float $totalCost): self
    {
        $this->totalCost = $totalCost;

        return $this;
    }

    public function getInitialHour(): ?float
    {
        return $this->initialHour;
    }

    public function setInitialHour(float $initialHour): self
    {
        $this->initialHour = $initialHour;

        return $this;
    }

    public function getUsedHourDate(): ?\DateTimeInterface
    {
        return $this->usedHourDate;
    }

    public function setUsedHourDate(?\DateTimeInterface $usedHourDate): self
    {
        $this->usedHourDate = $usedHourDate;

        return $this;
    }

    public function getTimeType(): ?string
    {
        return $this->timeType;
    }

    public function setTimeType(?string $timeType): self
    {
        $this->timeType = $timeType;

        return $this;
    }

    public function getInitialEndDate(): ?\DateTimeInterface
    {
        return $this->initialEndDate;
    }

    public function setInitialEndDate(\DateTimeInterface $initialEndDate): self
    {
        $this->initialEndDate = $initialEndDate;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeInterface $receivedAt): self
    {
        $this->receivedAt = $receivedAt;

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

    public function getConfirmationSent(): ?bool
    {
        return $this->confirmationSent;
    }

    public function setConfirmationSent(bool $confirmationSent): self
    {
        $this->confirmationSent = $confirmationSent;

        return $this;
    }
}
