<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="app_jobs")
 * @ORM\Entity(repositoryClass="App\Repository\JobRepository")
 */
class Job
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceCountry;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceZip;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceCity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceStreet;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceContactName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceContactTelephone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceContactFax;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insuranceContactComment;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierCountry;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierTelephone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierFax;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierZip;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierCity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $supplierStreet;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientCountry;

    /**
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $clientZip;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientCity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientStreet;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientMobile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientTelephone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $clientFax;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $insuranceDamageNo;

    /**
     * @ORM\Column(type="datetime")
     */
    private $insuranceDamageDate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $insuranceDamageDateReport;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $insuranceContractNo;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $insuranceVuNr;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $damageDescription;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $damageJob;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $referenceNo;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createDateTime;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $damageName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $damageStreet;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $damageZip;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $damageCity;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $damageCountry;

    /**
     * @ORM\Column(type="boolean")
     */
    private $receipt;

    /**
     * @ORM\Column(type="boolean")
     */
    private $emailSent;

    /**
     * @ORM\Column(type="datetime")
     */
    private $receiptDate;

    /**
     * @ORM\Column(type="integer")
     */
    private $receiptStatus;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $receiptMessage;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finishDate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $jobEnter;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $dlNo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $dlpNo;

    /**
     * @ORM\Column(type="text")
     */
    private $crypt;

    /**
     * @ORM\ManyToMany(targetEntity="Action", inversedBy="job")
     */
    protected $action;

    /**
     * @ORM\ManyToOne(targetEntity="Damage", inversedBy="job")
     */
    protected $damage;

    /**
     * @ORM\ManyToOne(targetEntity="Area", inversedBy="job")
     */
    protected $area;

    /**
     * @ORM\ManyToOne(targetEntity="Contract", inversedBy="job")
     */
    protected $contract;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Result", mappedBy="job")
     */
    private $results;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\File", mappedBy="job", orphanRemoval=true)
     */
    private $files;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\MessageType", mappedBy="job")
     */
    private $messageTypes;

    public function __construct()
    {
        $this->action = new ArrayCollection();
        $this->results = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->messageTypes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInsuranceName(): ?string
    {
        return $this->insuranceName;
    }

    public function setInsuranceName(string $insuranceName): self
    {
        $this->insuranceName = $insuranceName;

        return $this;
    }

    public function getInsuranceCountry(): ?string
    {
        return $this->insuranceCountry;
    }

    public function setInsuranceCountry(string $insuranceCountry): self
    {
        $this->insuranceCountry = $insuranceCountry;

        return $this;
    }

    public function getInsuranceZip(): ?string
    {
        return $this->insuranceZip;
    }

    public function setInsuranceZip(string $insuranceZip): self
    {
        $this->insuranceZip = $insuranceZip;

        return $this;
    }

    public function getInsuranceCity(): ?string
    {
        return $this->insuranceCity;
    }

    public function setInsuranceCity(string $insuranceCity): self
    {
        $this->insuranceCity = $insuranceCity;

        return $this;
    }

    public function getInsuranceStreet(): ?string
    {
        return $this->insuranceStreet;
    }

    public function setInsuranceStreet(string $insuranceStreet): self
    {
        $this->insuranceStreet = $insuranceStreet;

        return $this;
    }

    public function getInsuranceContactName(): ?string
    {
        return $this->insuranceContactName;
    }

    public function setInsuranceContactName(string $insuranceContactName): self
    {
        $this->insuranceContactName = $insuranceContactName;

        return $this;
    }

    public function getInsuranceContactTelephone(): ?string
    {
        return $this->insuranceContactTelephone;
    }

    public function setInsuranceContactTelephone(?string $insuranceContactTelephone): self
    {
        $this->insuranceContactTelephone = $insuranceContactTelephone;

        return $this;
    }

    public function getInsuranceContactFax(): ?string
    {
        return $this->insuranceContactFax;
    }

    public function setInsuranceContactFax(?string $insuranceContactFax): self
    {
        $this->insuranceContactFax = $insuranceContactFax;

        return $this;
    }

    public function getInsuranceContactComment(): ?string
    {
        return $this->insuranceContactComment;
    }

    public function setInsuranceContactComment(?string $insuranceContactComment): self
    {
        $this->insuranceContactComment = $insuranceContactComment;

        return $this;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function setSupplierName(string $supplierName): self
    {
        $this->supplierName = $supplierName;

        return $this;
    }

    public function getSupplierCountry(): ?string
    {
        return $this->supplierCountry;
    }

    public function setSupplierCountry(string $supplierCountry): self
    {
        $this->supplierCountry = $supplierCountry;

        return $this;
    }

    public function getSupplierTelephone(): ?string
    {
        return $this->supplierTelephone;
    }

    public function setSupplierTelephone(?string $supplierTelephone): self
    {
        $this->supplierTelephone = $supplierTelephone;

        return $this;
    }

    public function getSupplierFax(): ?string
    {
        return $this->supplierFax;
    }

    public function setSupplierFax(?string $supplierFax): self
    {
        $this->supplierFax = $supplierFax;

        return $this;
    }

    public function getSupplierZip(): ?string
    {
        return $this->supplierZip;
    }

    public function setSupplierZip(string $supplierZip): self
    {
        $this->supplierZip = $supplierZip;

        return $this;
    }

    public function getSupplierCity(): ?string
    {
        return $this->supplierCity;
    }

    public function setSupplierCity(string $supplierCity): self
    {
        $this->supplierCity = $supplierCity;

        return $this;
    }

    public function getSupplierStreet(): ?string
    {
        return $this->supplierStreet;
    }

    public function setSupplierStreet(string $supplierStreet): self
    {
        $this->supplierStreet = $supplierStreet;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): self
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getClientCountry(): ?string
    {
        return $this->clientCountry;
    }

    public function setClientCountry(string $clientCountry): self
    {
        $this->clientCountry = $clientCountry;

        return $this;
    }

    public function getClientZip(): ?string
    {
        return $this->clientZip;
    }

    public function setClientZip(string $clientZip): self
    {
        $this->clientZip = $clientZip;

        return $this;
    }

    public function getClientCity(): ?string
    {
        return $this->clientCity;
    }

    public function setClientCity(string $clientCity): self
    {
        $this->clientCity = $clientCity;

        return $this;
    }

    public function getClientStreet(): ?string
    {
        return $this->clientStreet;
    }

    public function setClientStreet(string $clientStreet): self
    {
        $this->clientStreet = $clientStreet;

        return $this;
    }

    public function getClientMobile(): ?string
    {
        return $this->clientMobile;
    }

    public function setClientMobile(?string $clientMobile): self
    {
        $this->clientMobile = $clientMobile;

        return $this;
    }

    public function getClientTelephone(): ?string
    {
        return $this->clientTelephone;
    }

    public function setClientTelephone(?string $clientTelephone): self
    {
        $this->clientTelephone = $clientTelephone;

        return $this;
    }

    public function getClientFax(): ?string
    {
        return $this->clientFax;
    }

    public function setClientFax(?string $clientFax): self
    {
        $this->clientFax = $clientFax;

        return $this;
    }

    public function getInsuranceDamageNo(): ?string
    {
        return $this->insuranceDamageNo;
    }

    public function setInsuranceDamageNo(string $insuranceDamageNo): self
    {
        $this->insuranceDamageNo = $insuranceDamageNo;

        return $this;
    }

    public function getInsuranceDamageDate(): ?\DateTimeInterface
    {
        return $this->insuranceDamageDate;
    }

    public function setInsuranceDamageDate(\DateTimeInterface $insuranceDamageDate): self
    {
        $this->insuranceDamageDate = $insuranceDamageDate;

        return $this;
    }

    public function getInsuranceDamageDateReport(): ?\DateTimeInterface
    {
        return $this->insuranceDamageDateReport;
    }

    public function setInsuranceDamageDateReport(\DateTimeInterface $insuranceDamageDateReport): self
    {
        $this->insuranceDamageDateReport = $insuranceDamageDateReport;

        return $this;
    }

    public function getInsuranceContractNo(): ?string
    {
        return $this->insuranceContractNo;
    }

    public function setInsuranceContractNo(string $insuranceContractNo): self
    {
        $this->insuranceContractNo = $insuranceContractNo;

        return $this;
    }

    public function getInsuranceVuNr(): ?string
    {
        return $this->insuranceVuNr;
    }

    public function setInsuranceVuNr(string $insuranceVuNr): self
    {
        $this->insuranceVuNr = $insuranceVuNr;

        return $this;
    }

    public function getDamageDescription(): ?string
    {
        return $this->damageDescription;
    }

    public function setDamageDescription(?string $damageDescription): self
    {
        $this->damageDescription = $damageDescription;

        return $this;
    }

    public function getDamageJob(): ?string
    {
        return $this->damageJob;
    }

    public function setDamageJob(string $damageJob): self
    {
        $this->damageJob = $damageJob;

        return $this;
    }

    public function getReferenceNo(): ?string
    {
        return $this->referenceNo;
    }

    public function setReferenceNo(string $referenceNo): self
    {
        $this->referenceNo = $referenceNo;

        return $this;
    }

    public function getCreateDateTime(): ?\DateTimeInterface
    {
        return $this->createDateTime;
    }

    public function setCreateDateTime(\DateTimeInterface $createDateTime): self
    {
        $this->createDateTime = $createDateTime;

        return $this;
    }

    public function getDamageName(): ?string
    {
        return $this->damageName;
    }

    public function setDamageName(string $damageName): self
    {
        $this->damageName = $damageName;

        return $this;
    }

    public function getDamageStreet(): ?string
    {
        return $this->damageStreet;
    }

    public function setDamageStreet(string $damageStreet): self
    {
        $this->damageStreet = $damageStreet;

        return $this;
    }

    public function getDamageZip(): ?string
    {
        return $this->damageZip;
    }

    public function setDamageZip(string $damageZip): self
    {
        $this->damageZip = $damageZip;

        return $this;
    }

    public function getDamageCity(): ?string
    {
        return $this->damageCity;
    }

    public function setDamageCity(string $damageCity): self
    {
        $this->damageCity = $damageCity;

        return $this;
    }

    public function getDamageCountry(): ?string
    {
        return $this->damageCountry;
    }

    public function setDamageCountry(string $damageCountry): self
    {
        $this->damageCountry = $damageCountry;

        return $this;
    }

    public function getReceipt(): ?bool
    {
        return $this->receipt;
    }

    public function setReceipt(bool $receipt): self
    {
        $this->receipt = $receipt;

        return $this;
    }

    public function getEmailSent(): ?bool
    {
        return $this->emailSent;
    }

    public function setEmailSent(bool $emailSent): self
    {
        $this->emailSent = $emailSent;

        return $this;
    }

    public function getReceiptDate(): ?\DateTimeInterface
    {
        return $this->receiptDate;
    }

    public function setReceiptDate(\DateTimeInterface $receiptDate): self
    {
        $this->receiptDate = $receiptDate;

        return $this;
    }

    public function getReceiptStatus(): ?int
    {
        return $this->receiptStatus;
    }

    public function setReceiptStatus(int $receiptStatus): self
    {
        $this->receiptStatus = $receiptStatus;

        return $this;
    }

    public function getReceiptMessage(): ?string
    {
        return $this->receiptMessage;
    }

    public function setReceiptMessage(?string $receiptMessage): self
    {
        $this->receiptMessage = $receiptMessage;

        return $this;
    }

    public function getFinishDate(): ?\DateTimeInterface
    {
        return $this->finishDate;
    }

    public function setFinishDate(?\DateTimeInterface $finishDate): self
    {
        $this->finishDate = $finishDate;

        return $this;
    }

    public function getJobEnter(): ?string
    {
        return $this->jobEnter;
    }

    public function setJobEnter(string $jobEnter): self
    {
        $this->jobEnter = $jobEnter;

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

    public function getCrypt(): ?string
    {
        return $this->crypt;
    }

    public function setCrypt(string $crypt): self
    {
        $this->crypt = $crypt;

        return $this;
    }

    /**
     * @return Collection|Action[]
     */
    public function getAction(): Collection
    {
        return $this->action;
    }

    public function addAction(Action $action): self
    {
        if (!$this->action->contains($action)) {
            $this->action[] = $action;
        }

        return $this;
    }

    public function removeAction(Action $action): self
    {
        if ($this->action->contains($action)) {
            $this->action->removeElement($action);
        }

        return $this;
    }

    public function getDamage(): ?Damage
    {
        return $this->damage;
    }

    public function setDamage(?Damage $damage): self
    {
        $this->damage = $damage;

        return $this;
    }

    public function getArea(): ?Area
    {
        return $this->area;
    }

    public function setArea(?Area $area): self
    {
        $this->area = $area;

        return $this;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): self
    {
        $this->contract = $contract;

        return $this;
    }

    /**
     * @return Collection|Result[]
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(Result $result): self
    {
        if (!$this->results->contains($result)) {
            $this->results[] = $result;
            $result->setJob($this);
        }

        return $this;
    }

    public function removeResult(Result $result): self
    {
        if ($this->results->contains($result)) {
            $this->results->removeElement($result);
            // set the owning side to null (unless already changed)
            if ($result->getJob() === $this) {
                $result->setJob(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|File[]
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): self
    {
        if (!$this->files->contains($file)) {
            $this->files[] = $file;
            $file->setJob($this);
        }

        return $this;
    }

    public function removeFile(File $file): self
    {
        if ($this->files->contains($file)) {
            $this->files->removeElement($file);
            // set the owning side to null (unless already changed)
            if ($file->getJob() === $this) {
                $file->setJob(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|MessageType[]
     */
    public function getMessageTypes(): Collection
    {
        return $this->messageTypes;
    }

    public function addMessageType(MessageType $messageType): self
    {
        if (!$this->messageTypes->contains($messageType)) {
            $this->messageTypes[] = $messageType;
            $messageType->addJob($this);
        }

        return $this;
    }

    public function removeMessageType(MessageType $messageType): self
    {
        if ($this->messageTypes->contains($messageType)) {
            $this->messageTypes->removeElement($messageType);
            $messageType->removeJob($this);
        }

        return $this;
    }


}
