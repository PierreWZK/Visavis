<?php

namespace App\Entity;

use App\Enum\Energy;
use App\Repository\VersionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une version précise d'un modèle. Unités en base : euros, ch, L ou kWh/100 km, litres, g CO₂/km.
 */
#[ORM\Entity(repositoryClass: VersionRepository::class)]
class Version
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Model $model = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 1990, max: 2100)]
    private int $year = 2024;

    #[ORM\Column(enumType: Energy::class)]
    #[Assert\NotNull]
    private ?Energy $energy = null;

    #[ORM\Column]
    #[Assert\Positive]
    private int $price = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Positive]
    private int $power = 0;

    #[ORM\Column]
    #[Assert\Positive]
    private float $consumption = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\PositiveOrZero]
    private int $trunk = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\PositiveOrZero]
    private int $co2 = 0;

    public function __toString(): string
    {
        return $this->model.' '.$this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getEnergy(): ?Energy
    {
        return $this->energy;
    }

    public function setEnergy(?Energy $energy): static
    {
        $this->energy = $energy;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPower(): int
    {
        return $this->power;
    }

    public function setPower(int $power): static
    {
        $this->power = $power;

        return $this;
    }

    public function getConsumption(): float
    {
        return $this->consumption;
    }

    public function setConsumption(float $consumption): static
    {
        $this->consumption = $consumption;

        return $this;
    }

    public function getTrunk(): int
    {
        return $this->trunk;
    }

    public function setTrunk(int $trunk): static
    {
        $this->trunk = $trunk;

        return $this;
    }

    public function getCo2(): int
    {
        return $this->co2;
    }

    public function setCo2(int $co2): static
    {
        $this->co2 = $co2;

        return $this;
    }
}
