<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InsuranceRepository")
 */
class Insurance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $dlNo;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $dlpNo;

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

    public function getDlNo(): ?string
    {
        return $this->dlNo;
    }

    public function setDlNo(string $dlNo): self
    {
        $this->dlNo = $dlNo;

        return $this;
    }

    public function getDlpNo(): ?string
    {
        return $this->dlpNo;
    }

    public function setDlpNo(string $dlpNo): self
    {
        $this->dlpNo = $dlpNo;

        return $this;
    }
}
