<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="app_damages")
 * @ORM\Entity(repositoryClass="App\Repository\DamageRepository")
 */
class Damage
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     */
    private $text;

    /**
     * @ORM\OneToMany(targetEntity="Job", mappedBy="damage")
     */
    protected $job;

    /**
     * @ORM\Column(type="integer")
     */
    private $gdv;

    public function __construct()
    {
        $this->job = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return Collection|Job[]
     */
    public function getJob(): Collection
    {
        return $this->job;
    }

    public function addJob(Job $job): self
    {
        if (!$this->job->contains($job)) {
            $this->job[] = $job;
            $job->setDamage($this);
        }

        return $this;
    }

    public function removeJob(Job $job): self
    {
        if ($this->job->contains($job)) {
            $this->job->removeElement($job);
            // set the owning side to null (unless already changed)
            if ($job->getDamage() === $this) {
                $job->setDamage(null);
            }
        }

        return $this;
    }

    public function getGdv(): ?int
    {
        return $this->gdv;
    }

    public function setGdv(int $gdv): self
    {
        $this->gdv = $gdv;

        return $this;
    }
}
