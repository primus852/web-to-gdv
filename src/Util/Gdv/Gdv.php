<?php
/**
 * Created by PhpStorm.
 * User: torsten
 * Date: 27.08.2018
 * Time: 14:54
 */

namespace App\Util\Gdv;


use App\Entity\Action;
use App\Entity\Area;
use App\Entity\Contract;
use App\Entity\Damage;
use App\Entity\Job;
use App\Entity\Result;
use App\Util\Sftp\Sftp;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use Exception;
use primus852\SimpleCrypt\SimpleCrypt;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Twig_Environment;

class Gdv
{

    private $em;
    private $crawler;
    private $mailer;
    private $twig;

    const REPORT_TYPE = array(
        '01' => 'Foto',
        '02' => 'Kostenvoranschlag',
        '04' => 'Gutachten',
        '11' => 'Abtretungserklärung',
        '32' => 'Abnahmebestätigung',
        '34' => 'Arbeitsnachweis',
        '38' => 'Fremdrechnung',
        '39' => 'Zwischenbericht',
        '40' => 'Messprotokoll',
        '99' => 'Sonstiges',
    );

    const MESSAGE_TYPE_BY_REPORT = array(
        '01' => '029',
        '02' => '031',
        '04' => '006',
        '11' => '029',
        '32' => '029',
        '34' => '029',
        '38' => '029',
        '39' => '029',
        '40' => '030',
        '99' => '029',
    );

    /**
     * Gdv constructor.
     * @param string $content
     * @param ObjectManager $em
     * @param Swift_Mailer $mailer
     * @param Twig_Environment $twig_Environment
     * @throws GdvException
     */
    public function __construct(string $content, \Doctrine\Common\Persistence\ManagerRegistry $em, Swift_Mailer $mailer, Twig_Environment $twig_Environment)
    {

        $this->em = $em;
        $this->mailer = $mailer;
        $this->twig = $twig_Environment;

        $this->crawler = new Crawler();
        $this->crawler->addHtmlContent(mb_convert_encoding($content, 'utf8'));

        if ($this->crawler->filter('GDV > Behebungsbeauftragung > PartnerdatenBlock > Partnerdaten > Adresse > Name1')->count()) {
            try {
                return self::nt028();
            } catch (GdvException $e) {
                throw new GdvException($e->getMessage() . ' (nt028)');
            }

        }

        if ($this->crawler->filter('GDV > individuelleLE > Header > Schaden-Nr')->count()) {
            try {
                return self::nt019();
            } catch (GdvException $e) {
                throw new GdvException($e->getMessage() . ' (nt019)');
            }
        }

        return false;

    }


    /**
     * @return bool
     * @throws GdvException
     */
    public function nt028()
    {

        $refNo = $this->crawler->filter('GDV > Vorsatz > Absender > Abs-OrdNr-DLP')->text();

        $jobs = $this->em->getRepository(Job::class)->findOneBy(array(
            'referenceNo' => $refNo,
        ));

        if ($jobs === null) {

            /* New Job instance */
            $job = new Job();

            /* Collect Data for Job */
            /* --> All Partners to one partner array
             *
             * Partnerdatenblock 0: Schadenort
             * Partnerdatenblock 1: Versicherung
             * Partnerdatenblock 2: Versicherungsnehmer
             * Partnerdatenblock 3: ASP Versicherung
             * Partnerdatenblock 4: Sanierer
             * Partnerdatenblock 5: 3C
             */
            $x = 0;
            $damageText = null;
            $damageAddress = null;
            $contactName = null;
            $contactNumber = null;
            foreach ($this->crawler->filter('GDV > Behebungsbeauftragung > PartnerdatenBlock') as $node) {

				if(empty($node) || $node === null){
					continue;
				}
                $newNode = new Crawler($node);
                $newNode->filter('Partnerdaten > Adresse > Name1')->count() ? $partner[$x]['name'] = $newNode->filter('Partnerdaten > Adresse > Name1')->text() : $partner[$x]['name'] = null;
                $newNode->filter('Partnerdaten > Adresse > Name2')->count() ? $partner[$x]['name'] .= ' ' . $newNode->filter('Partnerdaten > Adresse > Name2')->text() : $partner[$x]['name'] .= null;
                $newNode->filter('Partnerdaten > Adresse > Name3')->count() ? $partner[$x]['name'] .= ' ' . $newNode->filter('Partnerdaten > Adresse > Name3')->text() : $partner[$x]['name'] .= null;
                $newNode->filter('Partnerdaten > Adresse > LKZ')->count() ? $partner[$x]['country'] = $newNode->filter('Partnerdaten > Adresse > LKZ')->text() : $partner[$x]['country'] = null;
                $newNode->filter('Partnerdaten > Adresse > PLZ')->count() ? $partner[$x]['zip'] = $newNode->filter('Partnerdaten > Adresse > PLZ')->text() : $partner[$x]['zip'] = null;
                $newNode->filter('Partnerdaten > Adresse > Ort')->count() ? $partner[$x]['city'] = $newNode->filter('Partnerdaten > Adresse > Ort')->text() : $partner[$x]['city'] = null;
                $newNode->filter('Partnerdaten > Adresse > Strasse')->count() ? $partner[$x]['street'] = $newNode->filter('Partnerdaten > Adresse > Strasse')->text() : $partner[$x]['street'] = null;
                $y = 0;
                foreach ($newNode->filter('Kommunikation') as $comm) {

                	dump($comm);

                	if(empty($comm) || $comm === null){
                		continue;
                	}

                    $commNode = new Crawler($comm);
                    $commNode->filter('Typ')->count() ? $partner[$x]['communication'][$y]['type'] = $newNode->filter('Typ')->text() : $partner[$x]['communication'][$y]['type'] = "0";
                    $commNode->filter('Nummer')->count() ? $partner[$x]['communication'][$y]['number'] = $newNode->filter('Nummer')->text() : $partner[$x]['communication'][$y]['number'] = "Keine";
                    //EINE NUMMER AUSREICHEND????
                    //$commNode->filter('KOMM-TYP2')->count() ? $partner[$x]['communication'][$y]['type2'] = $newNode->filter('KOMM-TYP2')->text() : $partner[$x]['communication'][$y]['type2'] = null;
                    //$commNode->filter('KOMM-NR2')->count() ? $partner[$x]['communication'][$y]['number2'] = $newNode->filter('KOMM-NR2')->text() : $partner[$x]['communication'][$y]['number2'] = null;
                    $y++;
                }

                /* Partnerdatenblock 0: Versicherung */
                if ($x === 0) {

                    $job->setInsuranceName($partner[$x]['name']);
                    $job->setInsuranceStreet($partner[$x]['street']);
                    $job->setInsuranceZip($partner[$x]['zip']);
                    $job->setInsuranceCity($partner[$x]['city']);
                    $job->setInsuranceCountry($partner[$x]['country']);
                }

                /* Partnerdatenblock 1: Schadenort */
                if ($x === 1) {

                    $dd = 1;
                    foreach ($newNode->filter('Schadenhergang') as $dmgDesc) {

                    	if(empty($dmgDesc) || $dmgDesc === null){
							continue;
                        }

                        $dmgNode = new Crawler($dmgDesc);
                        $dmgNode->filter('Schilderung' . $dd)->count() ? $infos['damage_description'][$dd] = $newNode->filter('Schilderung' . $dd)->text() : $infos['damage_description'][$dd] = null;
                        $dmgNode->filter('Schilderung' . $dd)->count() ? $damageText .= $newNode->filter('Schilderung' . $dd)->text() : $damageText .= null;
                        $dd++;
                    }

                    $job->setDamageDescription($damageText);
                    $job->setDamageName($partner[$x]['name']);
                    $job->setDamageStreet($partner[$x]['street']);
                    $job->setDamageZip($partner[$x]['zip']);
                    $job->setDamageCity($partner[$x]['city']);
                    $job->setDamageCountry($partner[$x]['country']);

                    $damageAddress = $partner[$x]['street'] . "<br />" . $partner[$x]['zip'] . " " . $partner[$x]['city'] . "<br />" . $partner[$x]['country'];

                }

                /* Partnerdatenblock 2: Versicherungsnehmer */
                if ($x === 2) {
                    if (array_key_exists('communication', $partner[$x])) {
                        switch ($partner[$x]['communication'][0]['type']) {
                            case "10":
                            case "20":
                                $job->setClientTelephone($partner[$x]['communication'][0]['number']);
                                $contactNumber = $partner[$x]['communication'][0]['number'];
                                break;
                            case "30":
                                $job->setClientMobile($partner[$x]['communication'][0]['number']);
                                $contactNumber = $partner[$x]['communication'][0]['number'];
                                break;
                            case "40":
                            case "50":
                                $job->setClientFax($partner[$x]['communication'][0]['number']);
                                break;
                            default:
                                $job->setClientTelephone("Keine");
                                $job->setClientFax("Keine");
                                $contactNumber = "Keine";
                                break;
                        }
                    }

                    $job->setClientName($partner[$x]['name'] === null ? '' : $partner[$x]['name']);
                    $job->setClientStreet($partner[$x]['street'] === null ? '' : $partner[$x]['street']);
                    $job->setClientZip($partner[$x]['zip'] === null ? '' : $partner[$x]['zip']);
                    $job->setClientCity($partner[$x]['city'] === null ? '' : $partner[$x]['city']);
                    $job->setClientCountry($partner[$x]['country'] === null ? '' : $partner[$x]['country']);

                    $contactName = $partner[$x]['name'];

                }

                /* Partnerdatenblock 3: ASP Versicherung */
                if ($x === 3) {
                    if (array_key_exists('communication', $partner[$x])) {
                        switch ($partner[$x]['communication'][0]['type']) {
                            case "10":
                            case "20":
                            case "30":
                                $job->setInsuranceContactTelephone($partner[$x]['communication'][0]['number']);
                                break;
                            case "40":
                            case "50":
                                $job->setInsuranceContactFax($partner[$x]['communication'][0]['number']);
                                break;
                            default:
                                $job->setInsuranceContactTelephone("Keine");
                                $job->setInsuranceContactFax("Keine");
                                break;
                        }
                    }


                    $job->setInsuranceContactName($partner[$x]['name']);


                }

                /* Partnerdatenblock 4: Sanierer */
                if ($x === 4) {
                    if (array_key_exists('communication', $partner[$x])) {
                        switch ($partner[$x]['communication'][0]['type']) {
                            case "10":
                            case "20":
                            case "30":
                                $job->setSupplierTelephone($partner[$x]['communication'][0]['number']);
                                break;
                            case "40":
                            case "50":
                                $job->setSupplierFax($partner[$x]['communication'][0]['number']);
                                break;
                            default:
                                $job->setSupplierTelephone("Keine");
                                $job->setSupplierFax("Keine");
                                break;
                        }
                    }


                    $job->setSupplierName($partner[$x]['name']);
                    $job->setSupplierStreet($partner[$x]['street']);
                    $job->setSupplierZip($partner[$x]['zip']);
                    $job->setSupplierCity($partner[$x]['city']);
                    $job->setSupplierCountry($partner[$x]['country']);

                }

                /* Partnerdatenblock 5: 3C */
                if ($x === 5) {
                    //not needed
                }

                $x++;
            }

            /* --> Ordnungsnummer */
            $job->setReferenceNo($this->crawler->filter('GDV > Vorsatz > Absender > Abs-OrdNr-DLP')->text());

            /* --> Abs-DLNR */
            $job->setDlNo($this->crawler->filter('GDV > Vorsatz > Absender > Abs-DLNR')->text());

            /* --> Abs-DLPNR */
            $job->setDlpNo($this->crawler->filter('GDV > Vorsatz > Absender > Abs-DLPNR')->text());

            /* --> Schaden-Nr */
            $job->setInsuranceDamageNo($this->crawler->filter('GDV > Behebungsbeauftragung > Header > Schaden-Nr')->text());

            /* --> Schadendatum */
            $dmgDate = $this->crawler->filter('GDV > Behebungsbeauftragung > AllgemeineSchadendaten > Schadendatum')->text();
            $dmgDt = DateTime::createFromFormat('dmY', $dmgDate);
            if ($dmgDt === false) {
                throw new GdvException('Could not convert DamageDate: ' . $dmgDate);
            }
            $job->setInsuranceDamageDate($dmgDt);

            /* --> Schadenmeldedatum */
            $reportDate = $this->crawler->filter('GDV > Behebungsbeauftragung > AllgemeineSchadendaten > Schadenmeldedatum')->text();
            $reportDt = DateTime::createFromFormat('dmY', $reportDate);
            if ($reportDt === false) {
                throw new GdvException('Could not convert ReportDate: ' . $reportDate);
            }
            $job->setInsuranceDamageDateReport($reportDt);

            /* --> Versicherungsschein-Nr */
            $job->setInsuranceContractNo($this->crawler->filter('GDV > Behebungsbeauftragung > Header > Versicherungsschein-Nr')->text());

            /* --> VU-Nr */
            $job->setInsuranceVuNr($this->crawler->filter('GDV > Behebungsbeauftragung > Header > VU-Nr')->text());

            /* --> Auftragsbeschreibung */
            $job->setDamageJob($this->crawler->filter('GDV > Behebungsbeauftragung > Behebungsauftrag > Auftragsbeschreibung')->text());

            /* --> Damage Type from AppBundle:Damage */
            $damage = $this->em->getRepository(Damage::class)->findOneBy(array(
                'gdv' => $this->crawler->filter('GDV > Behebungsbeauftragung > AllgemeineSchadendaten > Schadenart')->text()
            ));
            $job->setDamage($damage);

            /* --> Area Type from AppBundle:Area */
            $area = $this->em->getRepository(Area::class)->findOneBy(array(
                'gdv' => ltrim($this->crawler->filter('GDV > Behebungsbeauftragung > Behebungsauftrag > BetroffenerBereich')->text(), '0')
            ));
            $job->setArea($area);

            /* --> Action Type from AppBundle:Action NOT READY, MULTIPLE ACTIONS POSSIBLE!!!!!! */
            $aa = 1;
            $actionText = null;
            foreach ($this->crawler->filter('GDV > Behebungsbeauftragung > Behebungsauftrag > Auftragstypen')->children() as $actionType) {

            	if(empty($actionType) || $actionType === null){
            		continue;
				}

                $actionNode = new Crawler($actionType);
                if ($actionNode->filter('Auftragsart' . $aa)->count()) {

                    $auftragsart = ltrim($this->crawler->filter('GDV > Behebungsbeauftragung > Behebungsauftrag > Auftragstypen > Auftragsart' . $aa)->text(), '0');

                    $action = $this->em->getRepository(Action::class)->findOneBy(array(
                        'gdv' => $auftragsart,
                    ));

                    if ($action === null) {
                        throw new GdvException('Could not find Action: ' . $auftragsart);
                    }

                    $action->addJob($job);
                    $job->addAction($action);
                    $this->em->persist($action);
                    $actionText .= $action->getText() . " ";
                }
                $aa++;
            }


            /* --> Contract Type from AppBundle:Contract */
            $contractType = ltrim($this->crawler->filter('GDV > Behebungsbeauftragung > AllgemeineSchadendaten > Schadensparte')->text(), '0');
            $contract = $this->em->getRepository(Contract::class)->findOneBy(array(
                'gdv' => $contractType
            ));

            if ($contract === null) {
                throw new GdvException('Could not find ContractType: ' . $contractType);
            }

            $job->setContract($contract);

            /* --> Crypt Plain and to Send */
            $sc = new SimpleCrypt('brasa', 'tw');
            $crypt = $this->crawler->filter('GDV > Behebungsbeauftragung > Header > Schaden-Nr')->text();
            $cryptSend = $sc->encrypt($crypt);

            $date = new DateTime();
            $date->setTimezone(new DateTimeZone('Europe/Berlin'));

            $job->setCreateDatetime($date);
            $job->setReceipt(false);
            $job->setReceiptStatus(false);
            $job->setJobEnter("download");
            $job->setReceiptDate($date);
            $job->setCrypt($cryptSend);

            try {
                $html = $this->twig->render(
                    'email/newJob.html.twig', array(
                    'sentAt' => new DateTime(),
                    'damageNo' => $this->crawler->filter('GDV > Behebungsbeauftragung > Header > Schaden-Nr')->text(),
                    'damageText' => $damageText,
                    'damageAddress' => $damageAddress,
                    'actionText' => $actionText,
                    'contactName' => $contactName,
                    'contactNumber' => $contactNumber,
                    'crypt' => $cryptSend,

                ));
            } catch (Exception $e) {
                throw new GdvException('Could not render Email Template: ' . $e->getMessage());
            }

            $message = (new Swift_Message('Ein neuer Auftrag ist eingegangen'))
                ->setFrom(getenv('MAILER_ADMIN'))
                ->setTo(getenv('MAILER_DEFAULT'))
                ->setBcc('tow.berlin@gmail.com')
                ->setBody($html, 'text/html');


            if ($this->mailer->send($message) > 0) {
                $job->setEmailsent(true);
            } else {
                $job->setEmailsent(false);
            }

            $this->em->persist($job);

            try {
                $this->em->flush();
            } catch (Exception $e) {
                throw new GdvException('MySQL Error: ' . $e->getMessage());
            }


            return true;


        } else {
            return false;
        }


    }

    /**
     * @return bool
     * @throws GdvException
     */
    public function nt019()
    {

        /* Find the job with the DamageNo & ContractNo */
        /* @var $job Job */
        $job = $this->em->getRepository(Job::class)->findOneBy(array(
            'insuranceDamageNo' => $this->crawler->filter('GDV > individuelleLE > Header > Schaden-Nr')->text(),
            'insuranceContractNo' => $this->crawler->filter('GDV > individuelleLE > Header > Versicherungsschein-Nr')->text(),
        ));

        if ($job === null) {

            $result = new Result();
            $result->setJob($job);
            $result->setText($this->crawler->filter('GDV > individuelleLE > SatzartZurFreienVerfuegung > beliebigerInhalt')->text());

            /**
             * Check if we have an attachment
             */
            $has_attachment = false;
            $f_full = null;
            if ($this->crawler->filter('GDV > individuelleLE > Anhang')->count()) {

                $has_attachment = true;

                /**
                 * File Parts
                 */
                $f_type = $this->crawler->filter('GDV > individuelleLE > Anhang > Anhangsart')->text();
                $f_name = $this->crawler->filter('GDV > individuelleLE > Anhang > Dateiname')->text();
                $f_value = $this->crawler->filter('GDV > individuelleLE > Anhang > Inhalt')->text();

                $f_full = $f_name . '.' . $f_type;

                /**
                 * Create tmp folder
                 */
                $fs = new Filesystem();
                if (!$fs->exists('tmp')) {
                    $fs->mkdir('tmp');
                }

                /**
                 * Delete previous Anhang
                 */
                if ($fs->exists('tmp/' . $f_full)) {
                    $fs->remove('tmp/' . $f_full);
                }

                /**
                 * Base64 String to File
                 */
                $fs->dumpFile('tmp/' . $f_full, base64_decode($f_value));

            }

            try {
                $html = $this->twig->render(
                    'email/newReceipt.html.twig', array(
                    'sentAt' => new DateTime(),
                    'damageNo' => $this->crawler->filter('GDV > individuelleLE > Header > Schaden-Nr')->text(),
                    'referenceNo' => $this->crawler->filter('GDV > Vorsatz > Absender > Abs-OrdNr-DLP')->text(),
                    'resultText' => $this->crawler->filter('GDV > individuelleLE > SatzartZurFreienVerfuegung > beliebigerInhalt')->text(),
                    'has_attachment' => $has_attachment ? ' - Details entnehmen Sie bitte dem Anhang' : '',

                ));
            } catch (Exception $e) {
                throw new GdvException('Could not render Email Template');
            }

            $message = (new Swift_Message('Prüfung abgeschlossen'))
                ->setFrom(getenv('MAILER_ADMIN'))
                ->setTo(getenv('MAILER_DEFAULT'))
                ->setBcc('tow.berlin@gmail.com')
                ->setBody($html, 'text/html');

            if($has_attachment){
                $message->attach(Swift_Attachment::fromPath('tmp/'.$f_full));
            }

            if ($this->mailer->send($message) > 0) {
                $result->setEmailSent(true);
            } else {
                $result->setEmailsent(false);
            }

            $this->em->persist($result);

            try {
                $this->em->flush();
            } catch (Exception $e) {
                throw new GdvException('MySQL Error: ' . $e->getMessage());
            }

            return true;

        } else {
            return false;
        }

    }

    /**
     * @param Job $job
     * @param string $extension
     * @param string $fileName
     * @param string $reportType
     * @throws GdvException
     */
    public static function report(Job $job, string $extension, string $fileName, string $reportType)
    {

        if (!array_key_exists($reportType, self::REPORT_TYPE)) {
            throw new GdvException('Unbekannter Typ: ' . $reportType);
        }

        $reportName = self::REPORT_TYPE[$reportType];
        $nrLogischeEinheitSend = self::MESSAGE_TYPE_BY_REPORT[$reportType];

        $fileNo = count($job->getFiles());

        /* New XML */
        $xml = new DOMDocument('1.0');

        /* XML-->GDV */
        $rootE = $xml->createElement('GDV');
        $rootE->setAttribute('xmlns', 'http://www.gdv-online.de/snetz/namespaces/KSN/release2003');
        $xml->appendChild($rootE);

        /* XML-->GDV-->Vorsatz */
        $vorsatzE = $xml->createElement('Vorsatz');
        $vorsatzE->setAttribute('Satzart', '4001');
        $vorsatzE->setAttribute('Versionsnummer', '003');
        $rootE->appendChild($vorsatzE);

        /* XML-->GDV-->Vorsatz-->Absender */
        $absenderE = $xml->createElement('Absender');
        $vorsatzE->appendChild($absenderE);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-DLNR */
        $abs1E = $xml->createElement('Abs-DLNR');
        $abs1T = $xml->createTextNode('3C06');
        $abs1E->appendChild($abs1T);
        $absenderE->appendChild($abs1E);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-DLPNR */
        $abs2E = $xml->createElement('Abs-DLPNR');
        $abs2T = $xml->createTextNode('0000');
        $abs2E->appendChild($abs2T);
        $absenderE->appendChild($abs2E);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-OrdNr-DLP */
        $abs3E = $xml->createElement('Abs-OrdNr-DLP');
        $abs3T = $xml->createTextNode($job->getReferenceNo());
        $abs3E->appendChild($abs3T);
        $absenderE->appendChild($abs3E);

        /* XML-->GDV-->Vorsatz-->Empfaenger */
        $empfaengerE = $xml->createElement('Empfaenger');
        $vorsatzE->appendChild($empfaengerE);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-DLNR */
        $emp1E = $xml->createElement('Empf-DLNR');
        $emp1T = $xml->createTextNode($job->getDlNo());
        $emp1E->appendChild($emp1T);
        $empfaengerE->appendChild($emp1E);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-DLPNR */
        $emp2E = $xml->createElement('Empf-DLPNR');
        $emp2T = $xml->createTextNode($job->getDlpNo());
        $emp2E->appendChild($emp2T);
        $empfaengerE->appendChild($emp2E);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-OrdNr-DLP */
        $emp3E = $xml->createElement('Empf-OrdNr-DLP');
        $emp3T = $xml->createTextNode($job->getReferenceNo());
        $emp3E->appendChild($emp3T);
        $empfaengerE->appendChild($emp3E);

        /* XML-->GDV-->Vorsatz->VSNR */
        $vsnrE = $xml->createElement('VSNR');
        $vsnrT = $xml->createTextNode($job->getInsuranceContractNo());
        $vsnrE->appendChild($vsnrT);
        $vorsatzE->appendChild($vsnrE);

        /* XML-->GDV-->Vorsatz->Erstellungsdatum */
        $edatumE = $xml->createElement('Erstellungsdatum');
        $edatumT = $xml->createTextNode(date("dmY"));
        $edatumE->appendChild($edatumT);
        $vorsatzE->appendChild($edatumE);

        /* XML-->GDV-->Vorsatz->Erstellungsuhrzeit */
        $euhrzeitE = $xml->createElement('Erstellungsuhrzeit');
        $euhrzeitT = $xml->createTextNode(date("His"));
        $euhrzeitE->appendChild($euhrzeitT);
        $vorsatzE->appendChild($euhrzeitE);

        /* XML-->GDV-->Vorsatz->Absenderkennzeichen */
        $kennzeichenE = $xml->createElement('AbsenderAdresskennzeichen');
        $kennzeichenT = $xml->createTextNode('AZ');
        $kennzeichenE->appendChild($kennzeichenT);
        $vorsatzE->appendChild($kennzeichenE);

        /* XML-->GDV-->Vorsatz->Releasenummer */
        $releaseE = $xml->createElement('ReleaseNummer');
        $releaseT = $xml->createTextNode('2003');
        $releaseE->appendChild($releaseT);
        $vorsatzE->appendChild($releaseE);

        /* XML-->GDV-->Vorsatz->Satznummer */
        $satzE = $xml->createElement('Satznummer');
        $satzT = $xml->createTextNode('1');
        $satzE->appendChild($satzT);
        $vorsatzE->appendChild($satzE);

        /* XML-->GDV-->SachZwischenbericht */
        $sachberichtE = $xml->createElement('SachZwischenbericht');
        $rootE->appendChild($sachberichtE);

        /* XML-->GDV-->SachZwischenbericht-->Header */
        $headerE = $xml->createElement('Header');
        $sachberichtE->appendChild($headerE);

        /* XML-->GDV-->SachZwischenbericht-->Header-->VU-Nr */
        $vuHeaderE = $xml->createElement('VU-Nr');
        $vuHeaderT = $xml->createTextNode($job->getInsuranceVuNr());
        $vuHeaderE->appendChild($vuHeaderT);
        $headerE->appendChild($vuHeaderE);

        /* XML-->GDV-->SachZwischenbericht-->Header-->Versicherungsschein-Nr */
        $vsHeaderE = $xml->createElement('Versicherungsschein-Nr');
        $vsHeaderT = $xml->createTextNode($job->getInsuranceContractNo());
        $vsHeaderE->appendChild($vsHeaderT);
        $headerE->appendChild($vsHeaderE);

        /* XML-->GDV-->SachZwischenbericht-->Header-->Schaden-Nr */
        $saHeaderE = $xml->createElement('Schaden-Nr');
        $saHeaderT = $xml->createTextNode($job->getInsuranceDamageNo());
        $saHeaderE->appendChild($saHeaderT);
        $headerE->appendChild($saHeaderE);

        /* XML-->GDV-->SachZwischenbericht-->BeschreibungLogischeEinheit */
        $logischeEinheitE = $xml->createElement('BeschreibungLogischeEinheit');
        $logischeEinheitE->setAttribute('Satzart', '4052');
        $logischeEinheitE->setAttribute('Versionsnummer', '001');
        $sachberichtE->appendChild($logischeEinheitE);

        /* XML-->GDV-->SachZwischenbericht-->BeschreibungLogischeEinheit-->NrLogischeEinheit */
        $nrLogischeEinheitE = $xml->createElement('NrLogischeEinheit');
        $nrLogischeEinheitT = $xml->createTextNode($nrLogischeEinheitSend);
        $nrLogischeEinheitE->appendChild($nrLogischeEinheitT);
        $logischeEinheitE->appendChild($nrLogischeEinheitE);


        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock */
        $partnerPreE = $xml->createElement('PartnerdatenBlock');
        $sachberichtE->appendChild($partnerPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten */
        $partnerDatenPreE = $xml->createElement('Partnerdaten');
        $partnerPreE->appendChild($partnerDatenPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse */
        $partnerAdressePreE = $xml->createElement('Adresse');
        $partnerDatenPreE->appendChild($partnerAdressePreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
        $partnerAnredePreE = $xml->createElement('Anredeschluessel');
        $partnerAnredePreT = $xml->createTextNode("3");
        $partnerAnredePreE->appendChild($partnerAnredePreT);
        $partnerAdressePreE->appendChild($partnerAnredePreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name1 */
        $partnerName1PreE = $xml->createElement('Name1');
        $partnerName1PreT = $xml->createTextNode("BRASA Berlin Brandschaden-Sani");
        $partnerName1PreE->appendChild($partnerName1PreT);
        $partnerAdressePreE->appendChild($partnerName1PreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name1 */
        $partnerName2PreE = $xml->createElement('Name2');
        $partnerName2PreT = $xml->createTextNode("erung");
        $partnerName2PreE->appendChild($partnerName2PreT);
        $partnerAdressePreE->appendChild($partnerName2PreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
        $partnerLKZPreE = $xml->createElement('LKZ');
        $partnerLKZPreT = $xml->createTextNode("D");
        $partnerLKZPreE->appendChild($partnerLKZPreT);
        $partnerAdressePreE->appendChild($partnerLKZPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
        $partnerPLZPreE = $xml->createElement('PLZ');
        $partnerPLZPreT = $xml->createTextNode("14163");
        $partnerPLZPreE->appendChild($partnerPLZPreT);
        $partnerAdressePreE->appendChild($partnerPLZPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partnerOrtPreE = $xml->createElement('Ort');
        $partnerOrtPreT = $xml->createTextNode("Berlin");
        $partnerOrtPreE->appendChild($partnerOrtPreT);
        $partnerAdressePreE->appendChild($partnerOrtPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
        $partnerStrassePreE = $xml->createElement('Strasse');
        $partnerStrassePreT = $xml->createTextNode("Hegauer Weg 19");
        $partnerStrassePreE->appendChild($partnerStrassePreT);
        $partnerAdressePreE->appendChild($partnerStrassePreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
        $partnerAdresseKPreE = $xml->createElement('Adresskennzeichen');
        $partnerAdresseKPreT = $xml->createTextNode("AZ");
        $partnerAdresseKPreE->appendChild($partnerAdresseKPreT);
        $partnerDatenPreE->appendChild($partnerAdresseKPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
        $partnerKommPreE = $xml->createElement('Kommunikation');
        $partnerDatenPreE->appendChild($partnerKommPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
        $partnerKommTypPreE = $xml->createElement('Typ');
        $partnerKommTypPreT = $xml->createTextNode("10");
        $partnerKommTypPreE->appendChild($partnerKommTypPreT);
        $partnerKommPreE->appendChild($partnerKommTypPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
        $partnerKommNrPreE = $xml->createElement('Nummer');
        $partnerKommNrPreT = $xml->createTextNode("+49 30 80908345");
        $partnerKommNrPreE->appendChild($partnerKommNrPreT);
        $partnerKommPreE->appendChild($partnerKommNrPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-TYP2 */
        $partnerKommTyp2PreE = $xml->createElement('KOMM-TYP2');
        $partnerKommTyp2PreT = $xml->createTextNode("50");
        $partnerKommTyp2PreE->appendChild($partnerKommTyp2PreT);
        $partnerKommPreE->appendChild($partnerKommTyp2PreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-NR2 */
        $partnerKommNr2PreE = $xml->createElement('KOMM-NR2');
        $partnerKommNr2PreT = $xml->createTextNode("+49 30 80908347");
        $partnerKommNr2PreE->appendChild($partnerKommNr2PreT);
        $partnerKommPreE->appendChild($partnerKommNr2PreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock */
        $partner2PreE = $xml->createElement('PartnerdatenBlock');
        $sachberichtE->appendChild($partner2PreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten */
        $partner2DatenPreE = $xml->createElement('Partnerdaten');
        $partner2PreE->appendChild($partner2DatenPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse */
        $partner2AdressePreE = $xml->createElement('Adresse');
        $partner2DatenPreE->appendChild($partner2AdressePreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
        $partner2AnredePreE = $xml->createElement('Anredeschluessel');
        $partner2AnredePreT = $xml->createTextNode("3");
        $partner2AnredePreE->appendChild($partner2AnredePreT);
        $partner2AdressePreE->appendChild($partner2AnredePreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
        $partner2PLZPreE = $xml->createElement('PLZ');
        $partner2PLZPreT = $xml->createTextNode($job->getDamageZip());
        $partner2PLZPreE->appendChild($partner2PLZPreT);
        $partner2AdressePreE->appendChild($partner2PLZPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partner2OrtPreE = $xml->createElement('Ort');
        $partner2OrtPreT = $xml->createTextNode($job->getDamageCity());
        $partner2OrtPreE->appendChild($partner2OrtPreT);
        $partner2AdressePreE->appendChild($partner2OrtPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
        $partner2StrassePreE = $xml->createElement('Strasse');
        $partner2StrassePreT = $xml->createTextNode($job->getDamageStreet());
        $partner2StrassePreE->appendChild($partner2StrassePreT);
        $partner2AdressePreE->appendChild($partner2StrassePreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
        $partner2AdresseKPreE = $xml->createElement('Adresskennzeichen');
        $partner2AdresseKPreT = $xml->createTextNode("AV");
        $partner2AdresseKPreE->appendChild($partner2AdresseKPreT);
        $partner2DatenPreE->appendChild($partner2AdresseKPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
        $partner2KommPreE = $xml->createElement('Kommunikation');
        $partner2DatenPreE->appendChild($partner2KommPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
        $partner2KommTypPreE = $xml->createElement('Typ');
        $partner2KommTypPreT = $xml->createTextNode("10");
        $partner2KommTypPreE->appendChild($partner2KommTypPreT);
        $partner2KommPreE->appendChild($partner2KommTypPreE);

        /* XML-->GDV-->SachZwischenbericht-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
        $partner2KommNrPreE = $xml->createElement('Nummer');
        $partner2KommNrPreT = $xml->createTextNode('0');
        $partner2KommNrPreE->appendChild($partner2KommNrPreT);
        $partner2KommPreE->appendChild($partner2KommNrPreE);

        /* XML-->GDV-->SachZwischenbericht-->Anhang */
        $anhangE = $xml->createElement('Anhang');
        $anhangE->setAttribute('Satzart', '4900');
        $anhangE->setAttribute('Versionsnummer', '003');
        $sachberichtE->appendChild($anhangE);

        /* XML-->GDV-->SachZwischenbericht-->Anhang-->Letzter */
        $anhangLetzterE = $xml->createElement('Letzter');
        $anhangLetzterT = $xml->createTextNode("1");
        $anhangLetzterE->appendChild($anhangLetzterT);
        $anhangE->appendChild($anhangLetzterE);

        /* XML-->GDV-->SachZwischenbericht-->Anhang-->Anhangsart */

        $anhangArtE = $xml->createElement('Anhangsart');
        /* Need to Check if JPEG detected (instead of jpg) */
        if (strtoupper($extension) == "JPEG") {
            $anhangArtT = $xml->createTextNode(strtoupper("JPG"));
        } else {
            $anhangArtT = $xml->createTextNode(strtoupper($extension));
        }
        $anhangArtE->appendChild($anhangArtT);
        $anhangE->appendChild($anhangArtE);

        /* XML-->GDV-->SachZwischenbericht-->Anhang-->VersionsnummerDatei */
        $anhangVersionE = $xml->createElement('VersionsnummerDatei');
        $anhangVersionT = $xml->createTextNode("1");
        $anhangVersionE->appendChild($anhangVersionT);
        $anhangE->appendChild($anhangVersionE);

        /* XML-->GDV-->SachZwischenbericht-->Anhang-->Anhangstyp */
        $anhangTypE = $xml->createElement('Anhangstyp');
        $anhangTypT = $xml->createTextNode($reportType); //SIEHE SATZNUMMER 4900 --> 39 = Zwischenbericht
        $anhangTypE->appendChild($anhangTypT);
        $anhangE->appendChild($anhangTypE);

        /* XML-->GDV-->SachZwischenbericht-->Anhang-->Dateiname */
        $anhangNameE = $xml->createElement('Dateiname');
        $anhangNameT = $xml->createTextNode($fileName);
        $anhangNameE->appendChild($anhangNameT);
        $anhangE->appendChild($anhangNameE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Dateiname-kurz */
        $anhangKurzE = $xml->createElement('Dateiname-kurz');
        $anhangKurzT = $xml->createTextNode(substr($fileName, 0, 6) . "~1");
        $anhangKurzE->appendChild($anhangKurzT);
        $anhangE->appendChild($anhangKurzE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Beschreibung */
        $anhangDescE = $xml->createElement('Beschreibung');
        $anhangDescT = $xml->createTextNode($reportName); // DEPENDS ON Anhangstyp
        $anhangDescE->appendChild($anhangDescT);
        $anhangE->appendChild($anhangDescE);

        try {
            $baseFile = file_get_contents('files/inbox/' . $fileName);
            $base64 = base64_encode($baseFile);
        } catch (Exception $e) {
            throw new GdvException('Konnte die Datei nicht finden: ' . $e->getMessage());
        }

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Inhalt */
        $anhangInhaltE = $xml->createElement('Inhalt');
        $anhangInhaltT = $xml->createTextNode($base64);
        $anhangInhaltE->appendChild($anhangInhaltT);
        $anhangE->appendChild($anhangInhaltE);

        /**
         * Create a new SFTP Connection
         */
        try {
            $sftp = new Sftp(getenv('SFTP_INBOX'));
        } catch (Exception $e) {
            throw new GdvException('Could not connect to SFTP: ' . $e->getMessage());
        }

        try {

            /* Save to Variable */
            $xml->saveXML();

            /* Save to local File */
            $xml->save('files/outbox/zb_' . $job->getInsuranceDamageNo() . '_' . $fileNo . '.xml');

        } catch (Exception $e) {
            throw new GdvException('Could not save XML: ' . $e->getMessage());
        }

        try {
            $sftp->put_file('zb_' . $job->getInsuranceDamageNo() . '_' . $fileNo . '.xml', $xml->saveXML(), getenv('IS_TEST'));
        } catch (GdvException $e) {
            throw new GdvException('Error Uploading Report: ' . $e->getMessage());
        }

    }

    /**
     * @param string $reference_no
     * @param string $insurance_contract_no
     * @param string $insurance_damage_no
     * @param string $insurance_vu_nr
     * @param string $dl_no
     * @param string $dlp_no
     * @param ObjectManager $em
     * @throws GdvException
     */
    public static function manual(string $reference_no, string $insurance_contract_no, string $insurance_damage_no, string $insurance_vu_nr, string $dl_no, string $dlp_no, ObjectManager $em)
    {

        /* New XML */
        $xml = new DOMDocument('1.0');

        /* XML-->GDV */
        $rootE = $xml->createElement('GDV');
        $rootE->setAttribute('xmlns', 'http://www.gdv-online.de/snetz/namespaces/KSN/release2003');
        $xml->appendChild($rootE);

        /* XML-->GDV-->Vorsatz */
        $vorsatzE = $xml->createElement('Vorsatz');
        $vorsatzE->setAttribute('Satzart', '4001');
        $vorsatzE->setAttribute('Versionsnummer', '003');
        $rootE->appendChild($vorsatzE);

        /* XML-->GDV-->Vorsatz-->Absender */
        $absenderE = $xml->createElement('Absender');
        $vorsatzE->appendChild($absenderE);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-DLNR */
        $abs1E = $xml->createElement('Abs-DLNR');
        $abs1T = $xml->createTextNode('3C06');
        $abs1E->appendChild($abs1T);
        $absenderE->appendChild($abs1E);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-DLPNR */
        $abs2E = $xml->createElement('Abs-DLPNR');
        $abs2T = $xml->createTextNode('0000');
        $abs2E->appendChild($abs2T);
        $absenderE->appendChild($abs2E);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-OrdNr-DLP */
        $abs3E = $xml->createElement('Abs-OrdNr-DLP');
        $abs3T = $xml->createTextNode($reference_no);
        $abs3E->appendChild($abs3T);
        $absenderE->appendChild($abs3E);

        /* XML-->GDV-->Vorsatz-->Empfaenger */
        $empfaengerE = $xml->createElement('Empfaenger');
        $vorsatzE->appendChild($empfaengerE);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-DLNR */
        $emp1E = $xml->createElement('Empf-DLNR');
        $emp1T = $xml->createTextNode($dl_no);
        $emp1E->appendChild($emp1T);
        $empfaengerE->appendChild($emp1E);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-DLPNR */
        $emp2E = $xml->createElement('Empf-DLPNR');
        $emp2T = $xml->createTextNode($dlp_no);
        $emp2E->appendChild($emp2T);
        $empfaengerE->appendChild($emp2E);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-OrdNr-DLP */
        $emp3E = $xml->createElement('Empf-OrdNr-DLP');
        $emp3T = $xml->createTextNode($reference_no);
        $emp3E->appendChild($emp3T);
        $empfaengerE->appendChild($emp3E);

        /* XML-->GDV-->Vorsatz->VSNR */
        $vsnrE = $xml->createElement('VSNR');
        $vsnrT = $xml->createTextNode($insurance_contract_no);
        $vsnrE->appendChild($vsnrT);
        $vorsatzE->appendChild($vsnrE);

        /* XML-->GDV-->Vorsatz->Erstellungsdatum */
        $edatumE = $xml->createElement('Erstellungsdatum');
        $edatumT = $xml->createTextNode(date("dmY"));
        $edatumE->appendChild($edatumT);
        $vorsatzE->appendChild($edatumE);

        /* XML-->GDV-->Vorsatz->Erstellungsuhrzeit */
        $euhrzeitE = $xml->createElement('Erstellungsuhrzeit');
        $euhrzeitT = $xml->createTextNode(date("His"));
        $euhrzeitE->appendChild($euhrzeitT);
        $vorsatzE->appendChild($euhrzeitE);

        /* XML-->GDV-->Vorsatz->Absenderkennzeichen */
        $kennzeichenE = $xml->createElement('AbsenderAdresskennzeichen');
        $kennzeichenT = $xml->createTextNode('AZ');
        $kennzeichenE->appendChild($kennzeichenT);
        $vorsatzE->appendChild($kennzeichenE);

        /* XML-->GDV-->Vorsatz->Releasenummer */
        $releaseE = $xml->createElement('ReleaseNummer');
        $releaseT = $xml->createTextNode('2003');
        $releaseE->appendChild($releaseT);
        $vorsatzE->appendChild($releaseE);

        /* XML-->GDV-->Vorsatz->Satznummer */
        $satzE = $xml->createElement('Satznummer');
        $satzT = $xml->createTextNode('1');
        $satzE->appendChild($satzT);
        $vorsatzE->appendChild($satzE);

        /* XML-->GDV-->Quittung */
        $quittungE = $xml->createElement('Quittung');
        $quittungE->setAttribute('Nachrichtentyp', '004');
        $rootE->appendChild($quittungE);

        /* XML-->GDV-->Quittung-->Header */
        $headerE = $xml->createElement('Header');
        $quittungE->appendChild($headerE);

        /* XML-->GDV-->Quittung-->Header-->VU-Nr */
        $vuHeaderE = $xml->createElement('VU-Nr');
        $vuHeaderT = $xml->createTextNode($insurance_vu_nr);
        $vuHeaderE->appendChild($vuHeaderT);
        $headerE->appendChild($vuHeaderE);

        /* XML-->GDV-->Quittung-->Header-->Versicherungsschein-Nr */
        $vsHeaderE = $xml->createElement('Versicherungsschein-Nr');
        $vsHeaderT = $xml->createTextNode($insurance_contract_no);
        $vsHeaderE->appendChild($vsHeaderT);
        $headerE->appendChild($vsHeaderE);

        /* XML-->GDV-->Quittung-->Header-->Schaden-Nr */
        $saHeaderE = $xml->createElement('Schaden-Nr');
        $saHeaderT = $xml->createTextNode($insurance_damage_no);
        $saHeaderE->appendChild($saHeaderT);
        $headerE->appendChild($saHeaderE);

        /* XML-->GDV-->Quittung-->BeschreibungLogischeEinheit */
        $logischeEinheitE = $xml->createElement('BeschreibungLogischeEinheit');
        $logischeEinheitE->setAttribute('Satzart', '4052');
        $logischeEinheitE->setAttribute('Versionsnummer', '001');
        $quittungE->appendChild($logischeEinheitE);

        /* XML-->GDV-->Quittung-->BeschreibungLogischeEinheit-->NrLogischeEinheit */
        $nrLogischeEinheitE = $xml->createElement('NrLogischeEinheit');
        $nrLogischeEinheitT = $xml->createTextNode('004');
        $nrLogischeEinheitE->appendChild($nrLogischeEinheitT);
        $logischeEinheitE->appendChild($nrLogischeEinheitE);

        /* XML-->GDV-->Quittung-->Quittungsdaten */
        $quittungsdatenE = $xml->createElement('Quittungsdaten');
        $quittungsdatenE->setAttribute('Satzart', '4850');
        $quittungsdatenE->setAttribute('Versionsnummer', '001');
        $quittungE->appendChild($quittungsdatenE);

        /* XML-->GDV-->Quittung-->Quittungsdaten-->Quittungstyp */
        $quittungstypE = $xml->createElement('Quittungstyp');
        $quittungstypT = $xml->createTextNode("04");
        $quittungstypE->appendChild($quittungstypT);
        $quittungsdatenE->appendChild($quittungstypE);

        /* XML-->GDV-->Quittung-->Quittungsdaten-->Sendedatum */
        $quittungsDatumE = $xml->createElement('Sendedatum');
        $quittungsDatumT = $xml->createTextNode(date("dmY"));
        $quittungsDatumE->appendChild($quittungsDatumT);
        $quittungsdatenE->appendChild($quittungsDatumE);

        /* XML-->GDV-->Quittung-->Quittungsdaten-->Sendedatum */
        $quittungsZeitE = $xml->createElement('Sendeuhrzeit');
        $quittungsZeitT = $xml->createTextNode(date("His"));
        $quittungsZeitE->appendChild($quittungsZeitT);
        $quittungsdatenE->appendChild($quittungsZeitE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock */
        $partnerE = $xml->createElement('PartnerdatenBlock');
        $quittungE->appendChild($partnerE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten */
        $partnerDatenE = $xml->createElement('Partnerdaten');
        $partnerE->appendChild($partnerDatenE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse */
        $partnerAdresseE = $xml->createElement('Adresse');
        $partnerDatenE->appendChild($partnerAdresseE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
        $partnerAnredeE = $xml->createElement('Anredeschluessel');
        $partnerAnredeT = $xml->createTextNode("3");
        $partnerAnredeE->appendChild($partnerAnredeT);
        $partnerAdresseE->appendChild($partnerAnredeE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name1 */
        $partnerName1E = $xml->createElement('Name1');
        $partnerName1T = $xml->createTextNode("BRASA Berlin Brandschaden-Sani");
        $partnerName1E->appendChild($partnerName1T);
        $partnerAdresseE->appendChild($partnerName1E);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name2 */
        $partnerName2E = $xml->createElement('Name2');
        $partnerName2T = $xml->createTextNode("erung");
        $partnerName2E->appendChild($partnerName2T);
        $partnerAdresseE->appendChild($partnerName2E);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
        $partnerLKZE = $xml->createElement('LKZ');
        $partnerLKZT = $xml->createTextNode("D");
        $partnerLKZE->appendChild($partnerLKZT);
        $partnerAdresseE->appendChild($partnerLKZE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
        $partnerPLZE = $xml->createElement('PLZ');
        $partnerPLZT = $xml->createTextNode("14163");
        $partnerPLZE->appendChild($partnerPLZT);
        $partnerAdresseE->appendChild($partnerPLZE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partnerOrtE = $xml->createElement('Ort');
        $partnerOrtT = $xml->createTextNode("Berlin");
        $partnerOrtE->appendChild($partnerOrtT);
        $partnerAdresseE->appendChild($partnerOrtE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
        $partnerStrasseE = $xml->createElement('Strasse');
        $partnerStrasseT = $xml->createTextNode("Hegauer Weg 19");
        $partnerStrasseE->appendChild($partnerStrasseT);
        $partnerAdresseE->appendChild($partnerStrasseE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
        $partnerAdresseKE = $xml->createElement('Adresskennzeichen');
        $partnerAdresseKT = $xml->createTextNode("AZ");
        $partnerAdresseKE->appendChild($partnerAdresseKT);
        $partnerDatenE->appendChild($partnerAdresseKE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
        $partnerKommE = $xml->createElement('Kommunikation');
        $partnerDatenE->appendChild($partnerKommE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
        $partnerKommTypE = $xml->createElement('Typ');
        $partnerKommTypT = $xml->createTextNode("20");
        $partnerKommTypE->appendChild($partnerKommTypT);
        $partnerKommE->appendChild($partnerKommTypE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
        $partnerKommNrE = $xml->createElement('Nummer');
        $partnerKommNrT = $xml->createTextNode("+49 30 80908345");
        $partnerKommNrE->appendChild($partnerKommNrT);
        $partnerKommE->appendChild($partnerKommNrE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-TYP2 */
        $partnerKommTyp2E = $xml->createElement('KOMM-TYP2');
        $partnerKommTyp2T = $xml->createTextNode("50");
        $partnerKommTyp2E->appendChild($partnerKommTyp2T);
        $partnerKommE->appendChild($partnerKommTyp2E);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-NR2 */
        $partnerKommNr2E = $xml->createElement('KOMM-NR2');
        $partnerKommNr2T = $xml->createTextNode("+49 30 80908347");
        $partnerKommNr2E->appendChild($partnerKommNr2T);
        $partnerKommE->appendChild($partnerKommNr2E);

        try {
            self::nt028_manual($insurance_damage_no, $insurance_contract_no, $insurance_vu_nr, $reference_no, $dl_no, $dlp_no, $em);
        } catch (GdvException $e) {
            throw new GdvException($e->getMessage());
        }

        /**
         * Create files folder if it does not exist
         */
        $fs = new Filesystem();
        if (!$fs->exists('files/outbox')) {
            $fs->mkdir('files/outbox');
        }

        /**
         * Create a new SFTP Connection
         */
        try {
            $sftp = new Sftp(getenv('SFTP_INBOX'));
        } catch (Exception $e) {
            throw new GdvException('Could not connect to SFTP: ' . $e->getMessage());
        }

        try {

            /* Save to Variable */
            $xml->saveXML();

            /* Save to local File */
            $xml->save('files/outbox/quittung_' . $insurance_damage_no . '_manuell.xml');

        } catch (Exception $e) {
            throw new GdvException('Could not save XML: ' . $e->getMessage());
        }

        try {
            $sftp->put_file('quittung_' . $insurance_damage_no . '_manuell.xml', $xml->saveXML(), getenv('IS_TEST'));
        } catch (GdvException $e) {
            throw new GdvException('Error Uploading Manual Receipt: ' . $e->getMessage());
        }

    }

    /**
     * @param string $insurance_damage_no
     * @param string $insurance_contract_no
     * @param string $insurance_vu_nr
     * @param string $reference_no
     * @param string $dl_no
     * @param string $dlp_no
     * @param ObjectManager $em
     * @throws GdvException
     */
    private static function nt028_manual(string $insurance_damage_no, string $insurance_contract_no, string $insurance_vu_nr, string $reference_no, string $dl_no, string $dlp_no, ObjectManager $em)
    {


        $sc = new SimpleCrypt('brasa', 'tw');
        $cryptSend = $sc->encrypt($insurance_damage_no);

        $damage = $em->getRepository(Damage::class)->findOneBy(array(
            'gdv' => 999,
        )); //Sonstiges
        $contract = $em->getRepository(Contract::class)->findOneBy(array(
            'gdv' => 0
        )); //Unbekannt
        $area = $em->getRepository(Area::class)->findOneBy(array(
            'gdv' => 99
        )); //Sonstiges

        $job = new Job();
        $job->setDamage($damage); //REFERENCE //
        $job->setContract($contract); //REFERENCE //
        $job->setArea($area); //REFERENCE //
        $job->setInsuranceName("unbekannt"); //WOHER?
        $job->setInsuranceCountry("D"); //WOHER?
        $job->setInsuranceZip("unbekannt"); //WOHER?
        $job->setInsuranceCity("unbekannt"); //WOHER?
        $job->setInsuranceStreet("unbekannt"); //WOHER?
        $job->setInsuranceContactName("unbekannt"); //WOHER?
        $job->setInsuranceContactTelephone("unbekannt"); //WOHER?
        $job->setInsuranceContactFax("unbekannt"); //WOHER?
        $job->setInsuranceContactComment("unbekannt"); //WOHER?
        $job->setSupplierName("unbekannt"); //WOHER?
        $job->setSupplierCountry("D"); //WOHER?
        $job->setSupplierTelephone("unbekannt"); //WOHER?
        $job->setSupplierFax("unbekannt"); //WOHER?
        $job->setSupplierZip("unbekannt"); //WOHER?
        $job->setSupplierCity("unbekannt"); //WOHER?
        $job->setSupplierStreet("unbekannt"); //WOHER?
        $job->setClientName("unbekannt"); //WOHER?
        $job->setClientCountry("D"); //WOHER?
        $job->setClientZip("00000"); //WOHER?
        $job->setClientCity("unbekannt"); //WOHER?
        $job->setClientStreet("unbekannt"); //WOHER?
        $job->setClientMobile("unbekannt"); //WOHER?
        $job->setClientTelephone("unbekannt"); //WOHER?
        $job->setClientFax("unbekannt"); //WOHER?
        $job->setInsuranceDamageNo($insurance_damage_no);
        $job->setInsuranceDamageDate(new DateTime());
        $job->setInsuranceDamageDateReport(new DateTime());
        $job->setInsuranceContractNo($insurance_contract_no);
        $job->setInsuranceVuNr($insurance_vu_nr);
        $job->setDamageDescription("unbekannt"); //WOHER?
        $job->setDamageJob("unbekannt"); //WOHER?
        $job->setReferenceNo($reference_no);
        $job->setCreateDateTime(new DateTime());
        $job->setDamageName("unbekannt"); //WOHER?
        $job->setDamageStreet("unbekannt"); //WOHER?
        $job->setDamageZip("unbekannt"); //WOHER?
        $job->setDamageCity("unbekannt"); //WOHER?
        $job->setDamageCountry("unbekannt"); //WOHER?
        $job->setReceipt(true);
        $job->setReceiptDate(new DateTime());
        $job->setReceiptMessage("OK");
        $job->setReceiptStatus(1);
        $job->setEmailsent(false);
        $job->setCrypt($cryptSend);
        $job->setFinishDate(null);
        $job->setJobEnter("manuell");
        $job->setDlNo($dl_no);
        $job->setDlpNo($dlp_no);

        $em->persist($job);

        try {
            $em->flush();
        } catch (Exception $e) {
            throw new GdvException('MySQL Error: ' . $e->getMessage());
        }

    }

    /**
     * @param ObjectManager $em
     * @param Job $job
     * @param bool $status
     * @param string $reason
     * @throws GdvException
     */
    public static function receipt(ObjectManager $em, Job $job, bool $status, string $reason)
    {

        $receiptType = $status ? '04' : '05';

        /**
         * New XML
         */
        $xml = new DOMDocument('1.0');

        /* XML-->GDV */
        $rootE = $xml->createElement('GDV');
        $rootE->setAttribute('xmlns', 'http://www.gdv-online.de/snetz/namespaces/KSN/release2003');
        $xml->appendChild($rootE);

        /* XML-->GDV-->Vorsatz */
        $vorsatzE = $xml->createElement('Vorsatz');
        $vorsatzE->setAttribute('Satzart', '4001');
        $vorsatzE->setAttribute('Versionsnummer', '003');
        $rootE->appendChild($vorsatzE);

        /* XML-->GDV-->Vorsatz-->Absender */
        $absenderE = $xml->createElement('Absender');
        $vorsatzE->appendChild($absenderE);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-DLNR */
        $abs1E = $xml->createElement('Abs-DLNR');
        $abs1T = $xml->createTextNode('3C06');
        $abs1E->appendChild($abs1T);
        $absenderE->appendChild($abs1E);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-DLPNR */
        $abs2E = $xml->createElement('Abs-DLPNR');
        $abs2T = $xml->createTextNode('0000');
        $abs2E->appendChild($abs2T);
        $absenderE->appendChild($abs2E);

        /* XML-->GDV-->Vorsatz-->Absender-->Abs-OrdNr-DLP */
        $abs3E = $xml->createElement('Abs-OrdNr-DLP');
        $abs3T = $xml->createTextNode($job->getReferenceNo());
        $abs3E->appendChild($abs3T);
        $absenderE->appendChild($abs3E);

        /* XML-->GDV-->Vorsatz-->Empfaenger */
        $empfaengerE = $xml->createElement('Empfaenger');
        $vorsatzE->appendChild($empfaengerE);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-DLNR */
        $emp1E = $xml->createElement('Empf-DLNR');
        $emp1T = $xml->createTextNode('0051');
        $emp1E->appendChild($emp1T);
        $empfaengerE->appendChild($emp1E);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-DLPNR */
        $emp2E = $xml->createElement('Empf-DLPNR');
        $emp2T = $xml->createTextNode($job->getDlpNo());
        $emp2E->appendChild($emp2T);
        $empfaengerE->appendChild($emp2E);

        /* XML-->GDV-->Vorsatz-->Empfaenger-->Empf-OrdNr-DLP */
        $emp3E = $xml->createElement('Empf-OrdNr-DLP');
        $emp3T = $xml->createTextNode($job->getReferenceNo());
        $emp3E->appendChild($emp3T);
        $empfaengerE->appendChild($emp3E);

        /* XML-->GDV-->Vorsatz->VSNR */
        $vsnrE = $xml->createElement('VSNR');
        $vsnrT = $xml->createTextNode($job->getInsuranceContractNo());
        $vsnrE->appendChild($vsnrT);
        $vorsatzE->appendChild($vsnrE);

        /* XML-->GDV-->Vorsatz->Erstellungsdatum */
        $edatumE = $xml->createElement('Erstellungsdatum');
        $edatumT = $xml->createTextNode(date("dmY"));
        $edatumE->appendChild($edatumT);
        $vorsatzE->appendChild($edatumE);

        /* XML-->GDV-->Vorsatz->Erstellungsuhrzeit */
        $euhrzeitE = $xml->createElement('Erstellungsuhrzeit');
        $euhrzeitT = $xml->createTextNode(date("His"));
        $euhrzeitE->appendChild($euhrzeitT);
        $vorsatzE->appendChild($euhrzeitE);

        /* XML-->GDV-->Vorsatz->Absenderkennzeichen */
        $kennzeichenE = $xml->createElement('AbsenderAdresskennzeichen');
        $kennzeichenT = $xml->createTextNode('AZ');
        $kennzeichenE->appendChild($kennzeichenT);
        $vorsatzE->appendChild($kennzeichenE);

        /* XML-->GDV-->Vorsatz->Releasenummer */
        $releaseE = $xml->createElement('ReleaseNummer');
        $releaseT = $xml->createTextNode('2003');
        $releaseE->appendChild($releaseT);
        $vorsatzE->appendChild($releaseE);

        /* XML-->GDV-->Vorsatz->Satznummer */
        $satzE = $xml->createElement('Satznummer');
        $satzT = $xml->createTextNode('1');
        $satzE->appendChild($satzT);
        $vorsatzE->appendChild($satzE);

        /* XML-->GDV-->Quittung */
        $quittungE = $xml->createElement('Quittung');
        $quittungE->setAttribute('Nachrichtentyp', '004');
        $rootE->appendChild($quittungE);

        /* XML-->GDV-->Quittung-->Header */
        $headerE = $xml->createElement('Header');
        $quittungE->appendChild($headerE);

        /* XML-->GDV-->Quittung-->Header-->VU-Nr */
        $vuHeaderE = $xml->createElement('VU-Nr');
        $vuHeaderT = $xml->createTextNode($job->getInsuranceVuNr());
        $vuHeaderE->appendChild($vuHeaderT);
        $headerE->appendChild($vuHeaderE);

        /* XML-->GDV-->Quittung-->Header-->Versicherungsschein-Nr */
        $vsHeaderE = $xml->createElement('Versicherungsschein-Nr');
        $vsHeaderT = $xml->createTextNode($job->getInsuranceContractNo());
        $vsHeaderE->appendChild($vsHeaderT);
        $headerE->appendChild($vsHeaderE);

        /* XML-->GDV-->Quittung-->Header-->Schaden-Nr */
        $saHeaderE = $xml->createElement('Schaden-Nr');
        $saHeaderT = $xml->createTextNode($job->getInsuranceDamageNo());
        $saHeaderE->appendChild($saHeaderT);
        $headerE->appendChild($saHeaderE);

        /* XML-->GDV-->Quittung-->BeschreibungLogischeEinheit */
        $logischeEinheitE = $xml->createElement('BeschreibungLogischeEinheit');
        $logischeEinheitE->setAttribute('Satzart', '4052');
        $logischeEinheitE->setAttribute('Versionsnummer', '001');
        $quittungE->appendChild($logischeEinheitE);

        /* XML-->GDV-->Quittung-->BeschreibungLogischeEinheit-->NrLogischeEinheit */
        $nrLogischeEinheitE = $xml->createElement('NrLogischeEinheit');
        $nrLogischeEinheitT = $xml->createTextNode('004');
        $nrLogischeEinheitE->appendChild($nrLogischeEinheitT);
        $logischeEinheitE->appendChild($nrLogischeEinheitE);

        /* XML-->GDV-->Quittung-->Quittungsdaten */
        $quittungsdatenE = $xml->createElement('Quittungsdaten');
        $quittungsdatenE->setAttribute('Satzart', '4850');
        $quittungsdatenE->setAttribute('Versionsnummer', '001');
        $quittungE->appendChild($quittungsdatenE);

        /* XML-->GDV-->Quittung-->Quittungsdaten-->Quittungstyp */
        $quittungstypE = $xml->createElement('Quittungstyp');
        $quittungstypT = $xml->createTextNode($receiptType);
        $quittungstypE->appendChild($quittungstypT);
        $quittungsdatenE->appendChild($quittungstypE);

        /* XML-->GDV-->Quittung-->Quittungsdaten-->Sendedatum */
        $quittungsDatumE = $xml->createElement('Sendedatum');
        $quittungsDatumT = $xml->createTextNode(date("dmY"));
        $quittungsDatumE->appendChild($quittungsDatumT);
        $quittungsdatenE->appendChild($quittungsDatumE);

        /* XML-->GDV-->Quittung-->Quittungsdaten-->Sendedatum */
        $quittungsZeitE = $xml->createElement('Sendeuhrzeit');
        $quittungsZeitT = $xml->createTextNode(date("His"));
        $quittungsZeitE->appendChild($quittungsZeitT);
        $quittungsdatenE->appendChild($quittungsZeitE);


        if ($status == false) {

            /* XML-->GDV-->Quittung-->PartnerdatenBlock */
            $partnerPreE = $xml->createElement('PartnerdatenBlock');
            $quittungE->appendChild($partnerPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten */
            $partnerDatenPreE = $xml->createElement('Partnerdaten');
            $partnerPreE->appendChild($partnerDatenPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse */
            $partnerAdressePreE = $xml->createElement('Adresse');
            $partnerDatenPreE->appendChild($partnerAdressePreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
            $partnerAnredePreE = $xml->createElement('Anredeschluessel');
            $partnerAnredePreT = $xml->createTextNode("3");
            $partnerAnredePreE->appendChild($partnerAnredePreT);
            $partnerAdressePreE->appendChild($partnerAnredePreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name1 */
            $partnerName1PreE = $xml->createElement('Name1');
            $partnerName1PreT = $xml->createTextNode("3C Deutschland GmbH");
            $partnerName1PreE->appendChild($partnerName1PreT);
            $partnerAdressePreE->appendChild($partnerName1PreE);


            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
            $partnerLKZPreE = $xml->createElement('LKZ');
            $partnerLKZPreT = $xml->createTextNode("D");
            $partnerLKZPreE->appendChild($partnerLKZPreT);
            $partnerAdressePreE->appendChild($partnerLKZPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
            $partnerPLZPreE = $xml->createElement('PLZ');
            $partnerPLZPreT = $xml->createTextNode("74076");
            $partnerPLZPreE->appendChild($partnerPLZPreT);
            $partnerAdressePreE->appendChild($partnerPLZPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
            $partnerOrtPreE = $xml->createElement('Ort');
            $partnerOrtPreT = $xml->createTextNode("Heilbronn");
            $partnerOrtPreE->appendChild($partnerOrtPreT);
            $partnerAdressePreE->appendChild($partnerOrtPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
            $partnerStrassePreE = $xml->createElement('Strasse');
            $partnerStrassePreT = $xml->createTextNode("Edisonstr. 19");
            $partnerStrassePreE->appendChild($partnerStrassePreT);
            $partnerAdressePreE->appendChild($partnerStrassePreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
            $partnerAdresseKPreE = $xml->createElement('Adresskennzeichen');
            $partnerAdresseKPreT = $xml->createTextNode("AZ");
            $partnerAdresseKPreE->appendChild($partnerAdresseKPreT);
            $partnerDatenPreE->appendChild($partnerAdresseKPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
            $partnerKommPreE = $xml->createElement('Kommunikation');
            $partnerDatenPreE->appendChild($partnerKommPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
            $partnerKommTypPreE = $xml->createElement('Typ');
            $partnerKommTypPreT = $xml->createTextNode("10");
            $partnerKommTypPreE->appendChild($partnerKommTypPreT);
            $partnerKommPreE->appendChild($partnerKommTypPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
            $partnerKommNrPreE = $xml->createElement('Nummer');
            $partnerKommNrPreT = $xml->createTextNode("+49(7131)79786-60");
            $partnerKommNrPreE->appendChild($partnerKommNrPreT);
            $partnerKommPreE->appendChild($partnerKommNrPreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-TYP2 */
            $partnerKommTyp2PreE = $xml->createElement('KOMM-TYP2');
            $partnerKommTyp2PreT = $xml->createTextNode("40");
            $partnerKommTyp2PreE->appendChild($partnerKommTyp2PreT);
            $partnerKommPreE->appendChild($partnerKommTyp2PreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-NR2 */
            $partnerKommNr2PreE = $xml->createElement('KOMM-NR2');
            $partnerKommNr2PreT = $xml->createTextNode("+49(7131)79786-88");
            $partnerKommNr2PreE->appendChild($partnerKommNr2PreT);
            $partnerKommPreE->appendChild($partnerKommNr2PreE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Email */
            $partnerEmailPreE = $xml->createElement('Email');
            $partnerEmailPreT = $xml->createTextNode("info@3c-d.de");
            $partnerEmailPreE->appendChild($partnerEmailPreT);
            $partnerDatenPreE->appendChild($partnerEmailPreE);

        }

        /* XML-->GDV-->Quittung-->PartnerdatenBlock */
        $partnerE = $xml->createElement('PartnerdatenBlock');
        $quittungE->appendChild($partnerE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten */
        $partnerDatenE = $xml->createElement('Partnerdaten');
        $partnerE->appendChild($partnerDatenE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse */
        $partnerAdresseE = $xml->createElement('Adresse');
        $partnerDatenE->appendChild($partnerAdresseE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
        $partnerAnredeE = $xml->createElement('Anredeschluessel');
        $partnerAnredeT = $xml->createTextNode("3");
        $partnerAnredeE->appendChild($partnerAnredeT);
        $partnerAdresseE->appendChild($partnerAnredeE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name1 */
        $partnerName1E = $xml->createElement('Name1');
        $partnerName1T = $xml->createTextNode("BRASA Berlin Brandschaden-Sani");
        $partnerName1E->appendChild($partnerName1T);
        $partnerAdresseE->appendChild($partnerName1E);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name2 */
        $partnerName2E = $xml->createElement('Name2');
        $partnerName2T = $xml->createTextNode("erung");
        $partnerName2E->appendChild($partnerName2T);
        $partnerAdresseE->appendChild($partnerName2E);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
        $partnerLKZE = $xml->createElement('LKZ');
        $partnerLKZT = $xml->createTextNode("D");
        $partnerLKZE->appendChild($partnerLKZT);
        $partnerAdresseE->appendChild($partnerLKZE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
        $partnerPLZE = $xml->createElement('PLZ');
        $partnerPLZT = $xml->createTextNode("14163");
        $partnerPLZE->appendChild($partnerPLZT);
        $partnerAdresseE->appendChild($partnerPLZE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partnerOrtE = $xml->createElement('Ort');
        $partnerOrtT = $xml->createTextNode("Berlin");
        $partnerOrtE->appendChild($partnerOrtT);
        $partnerAdresseE->appendChild($partnerOrtE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
        $partnerStrasseE = $xml->createElement('Strasse');
        $partnerStrasseT = $xml->createTextNode("Hegauer Weg 19");
        $partnerStrasseE->appendChild($partnerStrasseT);
        $partnerAdresseE->appendChild($partnerStrasseE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
        $partnerAdresseKE = $xml->createElement('Adresskennzeichen');
        $partnerAdresseKT = $xml->createTextNode("AZ");
        $partnerAdresseKE->appendChild($partnerAdresseKT);
        $partnerDatenE->appendChild($partnerAdresseKE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
        $partnerKommE = $xml->createElement('Kommunikation');
        $partnerDatenE->appendChild($partnerKommE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
        $partnerKommTypE = $xml->createElement('Typ');
        $partnerKommTypT = $xml->createTextNode("20");
        $partnerKommTypE->appendChild($partnerKommTypT);
        $partnerKommE->appendChild($partnerKommTypE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
        $partnerKommNrE = $xml->createElement('Nummer');
        $partnerKommNrT = $xml->createTextNode("+49 30 80908345");
        $partnerKommNrE->appendChild($partnerKommNrT);
        $partnerKommE->appendChild($partnerKommNrE);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-TYP2 */
        $partnerKommTyp2E = $xml->createElement('KOMM-TYP2');
        $partnerKommTyp2T = $xml->createTextNode("50");
        $partnerKommTyp2E->appendChild($partnerKommTyp2T);
        $partnerKommE->appendChild($partnerKommTyp2E);

        /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-NR2 */
        $partnerKommNr2E = $xml->createElement('KOMM-NR2');
        $partnerKommNr2T = $xml->createTextNode("+49 30 80908347");
        $partnerKommNr2E->appendChild($partnerKommNr2T);
        $partnerKommE->appendChild($partnerKommNr2E);

        if ($status == false) {

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Kommentar */
            $partnerKommentarE = $xml->createElement('Kommentar');
            $partnerKommentarE->setAttribute('Satzart', '4710');
            $partnerKommentarE->setAttribute('Versionsnummer', '002');
            $partnerE->appendChild($partnerKommentarE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Kommentar-->Adresskennzeichen */
            $partnerAdresseKKE = $xml->createElement('Adresskennzeichen');
            $partnerAdresseKKT = $xml->createTextNode("AZ");
            $partnerAdresseKKE->appendChild($partnerAdresseKKT);
            $partnerKommentarE->appendChild($partnerAdresseKKE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Kommentar-->Kommentar1 */
            $partnerKommentar1E = $xml->createElement('Kommentar1');
            $partnerKommentar1T = $xml->createTextNode($reason);
            $partnerKommentar1E->appendChild($partnerKommentar1T);
            $partnerKommentarE->appendChild($partnerKommentar1E);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Kommentar-->Kommentar2 */
            $partnerKommentar2E = $xml->createElement('Kommentar2');
            $partnerKommentar2T = $xml->createTextNode('./.');
            $partnerKommentar2E->appendChild($partnerKommentar2T);
            $partnerKommentarE->appendChild($partnerKommentar2E);


            /* XML-->GDV-->Quittung-->PartnerdatenBlock */
            $partnerPostE = $xml->createElement('PartnerdatenBlock');
            $quittungE->appendChild($partnerPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten */
            $partnerDatenPostE = $xml->createElement('Partnerdaten');
            $partnerPostE->appendChild($partnerDatenPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse */
            $partnerAdressePostE = $xml->createElement('Adresse');
            $partnerDatenPostE->appendChild($partnerAdressePostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
            $partnerAnredePostE = $xml->createElement('Anredeschluessel');
            $partnerAnredePostT = $xml->createTextNode("3");
            $partnerAnredePostE->appendChild($partnerAnredePostT);
            $partnerAdressePostE->appendChild($partnerAnredePostE);


            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
            $partnerLKZPostE = $xml->createElement('LKZ');
            $partnerLKZPostT = $xml->createTextNode($job->getDamageCountry());
            $partnerLKZPostE->appendChild($partnerLKZPostT);
            $partnerAdressePostE->appendChild($partnerLKZPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
            $partnerPLZPostE = $xml->createElement('PLZ');
            $partnerPLZPostT = $xml->createTextNode($job->getDamageZip());
            $partnerPLZPostE->appendChild($partnerPLZPostT);
            $partnerAdressePostE->appendChild($partnerPLZPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
            $partnerOrtPostE = $xml->createElement('Ort');
            $partnerOrtPostT = $xml->createTextNode($job->getDamageCity());
            $partnerOrtPostE->appendChild($partnerOrtPostT);
            $partnerAdressePostE->appendChild($partnerOrtPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
            $partnerStrassePostE = $xml->createElement('Strasse');
            $partnerStrassePostT = $xml->createTextNode($job->getDamageStreet());
            $partnerStrassePostE->appendChild($partnerStrassePostT);
            $partnerAdressePostE->appendChild($partnerStrassePostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
            $partnerAdresseKPostE = $xml->createElement('Adresskennzeichen');
            $partnerAdresseKPostT = $xml->createTextNode("AV");
            $partnerAdresseKPostE->appendChild($partnerAdresseKPostT);
            $partnerDatenPostE->appendChild($partnerAdresseKPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
            $partnerKommPostE = $xml->createElement('Kommunikation');
            $partnerDatenPostE->appendChild($partnerKommPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
            $partnerKommTypPostE = $xml->createElement('Typ');
            $partnerKommTypPostT = $xml->createTextNode("20");
            $partnerKommTypPostE->appendChild($partnerKommTypPostT);
            $partnerKommPostE->appendChild($partnerKommTypPostE);

            /* XML-->GDV-->Quittung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
            $partnerKommNrPostE = $xml->createElement('Nummer');
            $partnerKommNrPostT = $xml->createTextNode("0");
            $partnerKommNrPostE->appendChild($partnerKommNrPostT);
            $partnerKommPostE->appendChild($partnerKommNrPostE);


        }

        /**
         * Create files folder if it does not exist
         */
        $fs = new Filesystem();
        if (!$fs->exists('files/outbox')) {
            $fs->mkdir('files/outbox');
        }

        /**
         * Create a new SFTP Connection
         */
        try {
            $sftp = new Sftp(getenv('SFTP_INBOX'));
        } catch (Exception $e) {
            throw new GdvException('Could not connect to SFTP: ' . $e->getMessage());
        }

        try {

            /* Save to Variable */
            $xml->saveXML();

            /* Save to local File */
            $xml->save('files/outbox/quittung_' . $job->getInsuranceDamageNo() . '.xml');

        } catch (Exception $e) {
            throw new GdvException('Could not save XML: ' . $e->getMessage());
        }

        try {
            $sftp->put_file('quittung_' . $job->getInsuranceDamageNo() . '.xml', $xml->saveXML(), getenv('IS_TEST'));
        } catch (GdvException $e) {
            throw new GdvException('Error Uploading Receipt: ' . $e->getMessage());
        }

        $job->setReceipt(true);
        $job->setReceiptStatus($status);
        $job->setReceiptDate(new DateTime());
        $job->setReceiptMessage($reason);

        try {
            $em->persist($job);
            $em->flush();
        } catch (Exception $e) {
            throw new GdvException('MySQL Error');
        }

    }

}