<?php

namespace App\Entity;

use App\Enum\Body;
use App\Repository\ModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ModelRepository::class)]
#[ORM\Table(name: 'car_model')]
class Model
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'models')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Brand $brand = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(enumType: Body::class)]
    #[Assert\NotNull]
    private ?Body $body = null;

    /** Inconnu pour les modèles venus de l'ADEME */
    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 9)]
    private ?int $seats = null;

    /**
     * Photo trouvée sur Wikimedia Commons (vignette de 960 px). null : pas encore cherchée ; '' : cherchée, rien trouvé.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imagePage = null;

    /** Auteur et licence, à afficher avec la photo */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageCredit = null;

    /** @var Collection<int, Version> */
    #[ORM\OneToMany(targetEntity: Version::class, mappedBy: 'model', orphanRemoval: true)]
    private Collection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return trim($this->brand?->getName().' '.$this->name);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;

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

    public function getBody(): ?Body
    {
        return $this->body;
    }

    public function setBody(?Body $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getSeats(): ?int
    {
        return $this->seats;
    }

    public function setSeats(?int $seats): static
    {
        $this->seats = $seats;

        return $this;
    }

    /** @return Collection<int, Version> */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getImagePage(): ?string
    {
        return $this->imagePage;
    }

    public function setImagePage(?string $imagePage): static
    {
        $this->imagePage = $imagePage;

        return $this;
    }

    public function getImageCredit(): ?string
    {
        return $this->imageCredit;
    }

    public function setImageCredit(?string $imageCredit): static
    {
        $this->imageCredit = $imageCredit;

        return $this;
    }

    /** La photo, s'il y en a une */
    public function hasImage(): bool
    {
        return (bool) $this->imageUrl;
    }
}
