<?php
/**
 * Created by PhpStorm.
 * User: torsten
 * Date: 23.10.2018
 * Time: 10:17
 */

namespace App\Util\Converter;


use App\Entity\File;
use App\Entity\Job;
use App\Util\Sftp\Sftp;
use Doctrine\Common\Persistence\ObjectManager;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class Converter
{

    const BLOCKS = array(
        'type' => 'A9',
        'file' => 'A11',
        'date' => 'A12',
        'invoice' => 'A13',
        'project' => 'A14',
        'dmg' => 'A15',
        'positions' => 'A22:G200',
    );

    const SEARCH = array("ä", "ö", "ü", "ß", "Ä", "Ö", "Ü", "é", "á", "ó",);
    const REPLACE = array("ae", "oe", "ue", "ss", "Ae", "Oe", "Ue", "e", "a", "o");

    /**
     * @param string $file
     * @return array
     * @throws ConverterException
     */
    public static function extract_excel(string $file)
    {

        try {
            $spreadsheet = IOFactory::load($file);
        } catch (Exception $e) {
            throw new ConverterException('Konnte Excel nicht laden: ' . $e->getMessage());
        }

        try {
            $ws = $spreadsheet->getActiveSheet();
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new ConverterException('Konnte Excel nicht laden: ' . $e->getMessage());
        }

        try {
            $type = $ws->getCell(self::BLOCKS['type']);
            $fileNo = $ws->getCell(self::BLOCKS['file']);
            $datePre = $ws->getCell(self::BLOCKS['date']);
            $invoiceNo = $ws->getCell(self::BLOCKS['invoice']);
            $project = str_replace('Projekt: ', '', $ws->getCell(self::BLOCKS['project']));
            $dmgPre = $ws->getCell(self::BLOCKS['dmg']);
            $positions = $ws->rangeToArray(self::BLOCKS['positions']);
            $createdPre = $spreadsheet->getProperties()->getCreated();
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            throw new ConverterException('Excel-Fehler: ' . $e->getMessage());
        }

        /**
         * Extract Schadennummer
         */
        preg_match_all('/Schaden-Nr.: ([0-9]+)*/', $dmgPre, $dmgNrArr);
        if (!empty($dmgNrArr[1][0])) {
            $dmg = $dmgNrArr[1][0];
        } else {
            throw new ConverterException('Konnte Schadennummer nicht extrahieren');
        }

        /**
         * Extract Date
         */
        try {
            $date = \DateTime::createFromFormat('d.m.Y', str_replace('Datum: ', '', $datePre));
        } catch (\Exception $e) {
            throw new ConverterException('Konnte Datum nicht extrahieren');
        }

        /**
         * Extract Created
         */
        try {
            $created = \DateTime::createFromFormat('U', $createdPre);
        } catch (\Exception $e) {
            throw new ConverterException('Konnte Datum nicht extrahieren');
        }

        return array(
            'type' => $type,
            'fileNo' => $fileNo,
            'date' => $date->format('dmY'),
            'invoice' => $invoiceNo,
            'project' => $project,
            'dmg' => $dmg,
            'positions' => $positions,
            'createdDate' => $created->format('dmY'),
            'createdTime' => $created->format('His'),
        );
    }

    public static function generate_invoice(Job $job, array $data, string $filename, ObjectManager $em)
    {

        $attachments = $em->getRepository(File::class)->findBy(array(
            'job' => $job,
            'filetype' => array('attachment', 'pdfinvoice'),
        ));

        /**
         * Init Vars
         */
        $i = 0;
        $lastRef = null;


        /**
         * Gather all positions
         */
        foreach ($data['positions'] as $position) {

            /**
             * If row is not empty
             */
            if ($position[3] != NULL) {

                $free = str_replace(self::SEARCH, self::REPLACE, $position[3]);
                $positionArr[$i]['freitext'] = $free;

                $referenz = "0.0.999";


                /* Wenn POS3 als ersten Text 'SUMME Titel' enthält, nehme die letzte Ref */
                if (strpos($position[3], 'SUMME Titel') === 0) {
                    $referenz = $lastRef;
                } else {
                    $referenz = $position[0];
                }

                /* Wenn POS3 als erstes Wort 'Titel' enthält, nehme POS0 als letzte Ref */
                if (strpos($position[3], 'Titel') === 0) {
                    $lastRef = $position[0];
                }

                /* WTF IS THAT SHIT */
                if ($referenz == null) {
                    $referenz = "0.0.999";
                }


                /* Weise zu */
                $positionArr[$i]['referenz'] = $referenz;

                if (strpos($position[1], '-') !== false) {
                    $mengeWert = str_replace('-', '', $position[1]);
                    $positionArr[$i]['mengeWert'] = $mengeWert;
                    $positionArr[$i]['mengeIndikator'] = "-";
                } else {
                    $positionArr[$i]['mengeWert'] = $position[1];
                    $positionArr[$i]['mengeIndikator'] = "+";
                }
                $positionArr[$i]['einheitWert'] = $position[2];

                if (strpos($position[4], '-') !== false) {
                    $einheitPreis = str_replace('-', '', $position[4]);
                    $positionArr[$i]['einheitPreis'] = str_replace(',', '', str_replace(' €', '', $einheitPreis));
                    $positionArr[$i]['einheitIndikator'] = "-";
                } else {
                    $positionArr[$i]['einheitPreis'] = str_replace(',', '', str_replace(' €', '', $position[4]));
                    $positionArr[$i]['einheitIndikator'] = "+";
                }

                if (strpos($position[5], '-') !== false) {
                    $nettoWert = str_replace('-', '', $position[5]);
                    $positionArr[$i]['nettoWert'] = str_replace(',', '', str_replace(' €', '', $nettoWert));
                    $positionArr[$i]['nettoIndikator'] = "-";
                } else {
                    $positionArr[$i]['nettoWert'] = str_replace(',', '', str_replace(' €', '', $position[5]));
                    $positionArr[$i]['nettoIndikator'] = "+";
                }

                if ($position[3] == "SUMME 1. Rechnung") {
                    if (strpos($position[5], '-') !== false) {
                        $totalArr['summeNetto'] = str_replace('-', '', str_replace(' €', '', $position[5]));
                        $totalArr['indikatorNetto'] = '-';
                    } else {
                        $totalArr['summeNetto'] = str_replace(',', '', str_replace(' €', '', $position[5]));
                        $totalArr['indikatorNetto'] = '+';
                    }
                }
                if ($position[3] == "UST. 19,0%") {
                    if (strpos($position[5], '-') !== false) {
                        $totalArr['summeMwSt'] = str_replace(',', '', str_replace('-', '', str_replace(' €', '', $position[5])));
                        $totalArr['indikatorMwSt'] = '-';
                    } else {
                        $totalArr['summeMwSt'] = str_replace(',', '', str_replace(' €', '', $position[5]));
                        $totalArr['indikatorMwSt'] = '+';
                    }
                }
                if ($position[3] == "BRUTTOSUMME") {
                    if (strpos($position[5], '-') !== false) {
                        $totalArr['summeBrutto'] = str_replace(',', '', str_replace('-', '', str_replace(' €', '', $position[5])));
                        $totalArr['indikatorBrutto'] = '-';
                    } else {
                        $totalArr['summeBrutto'] = str_replace(',', '', str_replace(' €', '', $position[5]));
                        $totalArr['indikatorBrutto'] = '+';
                    }
                }

                $i++;
            }
        }

        $typeOfferInvoice = 1;
        $typeName = 'angebot_';
        $typeAttachment = "99";
        if ($data['type'] == "Rechnung") {
            $typeOfferInvoice = 2;
            $typeAttachment = "03";
            $typeName = 'rechnung_';
        }

        /* New XML */
        $xml = new \DOMDocument('1.0', 'ISO-8859-1');

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
        $edatumT = $xml->createTextNode($data['createdDate']);
        $edatumE->appendChild($edatumT);
        $vorsatzE->appendChild($edatumE);

        /* XML-->GDV-->Vorsatz->Erstellungsuhrzeit */
        $euhrzeitE = $xml->createElement('Erstellungsuhrzeit');
        $euhrzeitT = $xml->createTextNode($data['createdTime']);
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

        /* XML-->GDV-->SachAngebotRechnung */
        $sachangebotRechnungE = $xml->createElement('SachAngebotRechnung');
        $sachangebotRechnungE->setAttribute('Nachrichtentyp', '031');
        $rootE->appendChild($sachangebotRechnungE);

        /* XML-->GDV-->SachAngebotRechnung-->Header */
        $headerE = $xml->createElement('Header');
        $sachangebotRechnungE->appendChild($headerE);

        /* XML-->GDV-->SachAngebotRechnung-->Header-->VU-Nr */
        $vuHeaderE = $xml->createElement('VU-Nr');
        $vuHeaderT = $xml->createTextNode($job->getInsuranceVuNr());
        $vuHeaderE->appendChild($vuHeaderT);
        $headerE->appendChild($vuHeaderE);

        /* XML-->GDV-->SachAngebotRechnung-->Header-->Versicherungsschein-Nr */
        $vsHeaderE = $xml->createElement('Versicherungsschein-Nr');
        $vsHeaderT = $xml->createTextNode($job->getInsuranceContractNo());
        $vsHeaderE->appendChild($vsHeaderT);
        $headerE->appendChild($vsHeaderE);

        /* XML-->GDV-->SachAngebotRechnung-->Header-->Schaden-Nr */
        $saHeaderE = $xml->createElement('Schaden-Nr');
        $saHeaderT = $xml->createTextNode($data['dmg']);
        $saHeaderE->appendChild($saHeaderT);
        $headerE->appendChild($saHeaderE);

        /* XML-->GDV-->SachAngebotRechnung-->Header-->Aktenzeichen */
        $azHeaderE = $xml->createElement('Aktenzeichen');
        $azHeaderT = $xml->createTextNode($data['fileNo']);
        $azHeaderE->appendChild($azHeaderT);
        $headerE->appendChild($azHeaderE);

        /* XML-->GDV-->SachAngebotRechnung-->BeschreibungLogischeEinheit */
        $logischeEinheitE = $xml->createElement('BeschreibungLogischeEinheit');
        $logischeEinheitE->setAttribute('Satzart', '4052');
        $logischeEinheitE->setAttribute('Versionsnummer', '001');
        $sachangebotRechnungE->appendChild($logischeEinheitE);

        /* XML-->GDV-->SachAngebotRechnung-->BeschreibungLogischeEinheit-->NrLogischeEinheit */
        $nrLogischeEinheitE = $xml->createElement('NrLogischeEinheit');
        $nrLogischeEinheitT = $xml->createTextNode('031');
        $nrLogischeEinheitE->appendChild($nrLogischeEinheitT);
        $logischeEinheitE->appendChild($nrLogischeEinheitE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsrahmendaten */
        $rahmenDatenE = $xml->createElement('AngebotRechnungsrahmendaten');
        $rahmenDatenE->setAttribute('Satzart', '4307');
        $rahmenDatenE->setAttribute('Versionsnummer', '001');
        $sachangebotRechnungE->appendChild($rahmenDatenE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Abrechnungsart */
        $abrArtE = $xml->createElement('Abrechnungsart');
        $abrArtT = $xml->createTextNode($typeOfferInvoice);
        $abrArtE->appendChild($abrArtT);
        $rahmenDatenE->appendChild($abrArtE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Waehrungsschluessel */
        $waehrungE = $xml->createElement('Waehrungsschluessel');
        $waehrungT = $xml->createTextNode('EUR');
        $waehrungE->appendChild($waehrungT);
        $rahmenDatenE->appendChild($waehrungE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag */
        $rgGesE = $xml->createElement('Rechnungsbetrag');
        $rahmenDatenE->appendChild($rgGesE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag-->Brutto */
        $rgBruttoE = $xml->createElement('Brutto');
        $rgGesE->appendChild($rgBruttoE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag-->Brutto-->Wert */
        $bruttoWertE = $xml->createElement('Wert');
        $bruttoWertT = $xml->createTextNode($totalArr['summeBrutto']);
        $bruttoWertE->appendChild($bruttoWertT);
        $rgBruttoE->appendChild($bruttoWertE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag-->Brutto-->Indikator */
        $bruttoIndikatorE = $xml->createElement('Indikator');
        $bruttoIndikatorT = $xml->createTextNode($totalArr['indikatorBrutto']);
        $bruttoIndikatorE->appendChild($bruttoIndikatorT);
        $rgBruttoE->appendChild($bruttoIndikatorE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag-->Netto */
        $rgNettoE = $xml->createElement('Netto');
        $rgGesE->appendChild($rgNettoE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag-->Netto-->Wert */
        $nettoWertE = $xml->createElement('Wert');
        $nettoWertT = $xml->createTextNode($totalArr['summeNetto']);
        $nettoWertE->appendChild($nettoWertT);
        $rgNettoE->appendChild($nettoWertE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsbetrag-->Netto-->Indikator */
        $nettoIndikatorE = $xml->createElement('Indikator');
        $nettoIndikatorT = $xml->createTextNode($totalArr['indikatorNetto']);
        $nettoIndikatorE->appendChild($nettoIndikatorT);
        $rgNettoE->appendChild($nettoIndikatorE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Mehrwertsteuergesamt */
        $rgMwstE = $xml->createElement('MehrwertsteuerGesamt');
        $rahmenDatenE->appendChild($rgMwstE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Mehrwertsteuergesamt-->Wert */
        $mwstGesWertE = $xml->createElement('Wert');
        $mwstGesWertT = $xml->createTextNode($totalArr['summeMwSt']);
        $mwstGesWertE->appendChild($mwstGesWertT);
        $rgMwstE->appendChild($mwstGesWertE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Mehrwertsteuergesamt-->Indikator */
        $mwstGesIndikatorE = $xml->createElement('Indikator');
        $mwstGesIndikatorT = $xml->createTextNode($totalArr['indikatorMwSt']);
        $mwstGesIndikatorE->appendChild($mwstGesIndikatorT);
        $rgMwstE->appendChild($mwstGesIndikatorE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsdatum */
        $rgDatumE = $xml->createElement('Rechnungsdatum');
        $rgDatumT = $xml->createTextNode($data['date']);
        $rgDatumE->appendChild($rgDatumT);
        $rahmenDatenE->appendChild($rgDatumE);

        /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungsRahmendaten-->Rechnungsnummer */
        $rgNrE = $xml->createElement('Rechnungsnummer');
        $rgNrT = $xml->createTextNode($data['invoice']);
        $rgNrE->appendChild($rgNrT);
        $rahmenDatenE->appendChild($rgNrE);

        /* FOREACH */
        foreach ($positionArr as $ePos) {

            if ($ePos['freitext'] != "SUMME 1. Rechnung" && $ePos['freitext'] != "SUMME 2. Rechnung" && $ePos['freitext'] != "SUMME 3. Rechnung" && $ePos['freitext'] != "SUMME 4. Rechnung" && $ePos['freitext'] != "UST. 19,0%" && $ePos['freitext'] != "BRUTTOSUMME") {

                if (!$ePos['mengeWert']) {
                    $ePos['mengeWert'] = 0;
                    $ePos['mengeIndikator'] = 0;
                    $ePos['einheitIndikator'] = 0;
                }

                if (!$ePos['einheitPreis']) {
                    $ePos['einheitPreis'] = 0;
                    $ePos['einheitIndikator'];
                }

                if (!$ePos['nettoWert']) {
                    $ePos['nettoWert'] = 0;
                    $ePos['nettoIndikator'] = 0;
                }

                if (!$ePos['einheitIndikator']) {
                    $ePos['einheitIndikator'] = 0;
                }
                switch ($ePos['einheitWert']) {
                    case "Std.":
                        $ePos['einheitWert'] = "01";
                        break;
                    case "Tag":
                        $ePos['einheitWert'] = "02";
                        break;
                    case "Stck":
                        $ePos['einheitWert'] = "03";
                        break;
                    case "m²":
                        $ePos['einheitWert'] = "04";
                        break;
                    case "lfdm":
                        $ePos['einheitWert'] = "05";
                        break;
                    case "psch":
                        $ePos['einheitWert'] = "06";
                        break;
                    case "m³":
                        $ePos['einheitWert'] = "07";
                        break;
                    case "kWh":
                        $ePos['einheitWert'] = "08";
                        break;
                    default:
                        $ePos['einheitWert'] = "99";
                }


                if ($ePos['einheitWert'] == "psch") {
                    $ePos['einheitWert'] = 0;
                }

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen */
                $einzelDatenE = $xml->createElement('AngebotRechnungseinzelpositionen');
                $einzelDatenE->setAttribute('Satzart', '4308');
                $einzelDatenE->setAttribute('Versionsnummer', '001');
                $sachangebotRechnungE->appendChild($einzelDatenE);

                $freitextCUT = substr($ePos['freitext'], 0, 120);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Freitext */
                $freitextE = $xml->createElement('Freitext');
                $freitextT = $xml->createTextNode($freitextCUT);
                $freitextE->appendChild($freitextT);
                $einzelDatenE->appendChild($freitextE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Positionsreferenz */
                $posRefE = $xml->createElement('Positionsreferenz');
                if (strlen($ePos['freitext']) > 120) {
                    $posRefT = $xml->createTextNode("0.0.999");
                } else {
                    $posRefT = $xml->createTextNode($ePos['referenz']);
                }

                $posRefE->appendChild($posRefT);
                $einzelDatenE->appendChild($posRefE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Menge */
                $countE = $xml->createElement('Menge');
                $einzelDatenE->appendChild($countE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Menge-->Wert */
                $countValE = $xml->createElement('Wert');
                $countValT = $xml->createTextNode(str_replace(',', '.', $ePos['mengeWert']));
                $countValE->appendChild($countValT);
                $countE->appendChild($countValE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Menge-->Indikator */
                $countIndE = $xml->createElement('Indikator');
                $countIndT = $xml->createTextNode($ePos['mengeIndikator']);
                $countIndE->appendChild($countIndT);
                $countE->appendChild($countIndE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Einheit */
                $measureE = $xml->createElement('Einheit');
                $measureT = $xml->createTextNode(str_replace(',', '.', $ePos['einheitWert']));
                $measureE->appendChild($measureT);
                $einzelDatenE->appendChild($measureE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Waehrungsschluessel */
                $waehrung2E = $xml->createElement('Waehrungsschluessel');
                $waehrung2T = $xml->createTextNode('EUR');
                $waehrung2E->appendChild($waehrung2T);
                $einzelDatenE->appendChild($waehrung2E);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->ProzentsatzMWST */
                $vat2E = $xml->createElement('ProzentsatzMWSt');
                $einzelDatenE->appendChild($vat2E);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->ProzentsatzMWST-->Wert */
                $vatVal2E = $xml->createElement('Wert');
                $vatVal2T = $xml->createTextNode('19');
                $vatVal2E->appendChild($vatVal2T);
                $vat2E->appendChild($vatVal2E);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->ProzentsatzMWST-->Indikator */
                $vatInd2E = $xml->createElement('Indikator');
                $vatInd2T = $xml->createTextNode($ePos['einheitIndikator']);
                $vatInd2E->appendChild($vatInd2T);
                $vat2E->appendChild($vatInd2E);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->PreisProEinheit */
                $preisEinheitE = $xml->createElement('PreisProEinheit');
                $einzelDatenE->appendChild($preisEinheitE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->PreisProEinheit-->Wert */
                $pEinheitValE = $xml->createElement('Wert');
                $pEinheitValT = $xml->createTextNode(str_replace(',', '.', $ePos['einheitPreis']));
                $pEinheitValE->appendChild($pEinheitValT);
                $preisEinheitE->appendChild($pEinheitValE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->PreisProEinheit-->Indikator */
                $pEinheitIndE = $xml->createElement('Indikator');
                $pEinheitIndT = $xml->createTextNode($ePos['einheitIndikator']);
                $pEinheitIndE->appendChild($pEinheitIndT);
                $preisEinheitE->appendChild($pEinheitIndE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Netto */
                $nettoAmtE = $xml->createElement('Netto');
                $einzelDatenE->appendChild($nettoAmtE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Netto-->Wert */
                $nettoAmtValE = $xml->createElement('Wert');
                $nettoAmtValT = $xml->createTextNode(str_replace(',', '.', $ePos['nettoWert']));
                $nettoAmtValE->appendChild($nettoAmtValT);
                $nettoAmtE->appendChild($nettoAmtValE);

                /* XML-->GDV-->SachAngebotRechnung-->AngebotRechnungseinzelpositionen-->Netto-->Indikator */
                $nettoAmtIndE = $xml->createElement('Indikator');
                $nettoAmtIndT = $xml->createTextNode($ePos['nettoIndikator']);
                $nettoAmtIndE->appendChild($nettoAmtIndT);
                $nettoAmtE->appendChild($nettoAmtIndE);

            }
        }

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock */
        $partnerE = $xml->createElement('PartnerdatenBlock');
        $sachangebotRechnungE->appendChild($partnerE);

        /* Sanierer (AZ) */
        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten */
        $partnerDatenE = $xml->createElement('Partnerdaten');
        $partnerE->appendChild($partnerDatenE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse */
        $partnerAdresseE = $xml->createElement('Adresse');
        $partnerDatenE->appendChild($partnerAdresseE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Anredeschluessel */
        $partnerAnredeE = $xml->createElement('Anredeschluessel');
        $partnerAnredeT = $xml->createTextNode("3");
        $partnerAnredeE->appendChild($partnerAnredeT);
        $partnerAdresseE->appendChild($partnerAnredeE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name1 */
        $partnerName1E = $xml->createElement('Name1');
        $partnerName1T = $xml->createTextNode(substr($job->getSupplierName(), 0, 30));
        $partnerName1E->appendChild($partnerName1T);
        $partnerAdresseE->appendChild($partnerName1E);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Name2 */
        $partnerName2E = $xml->createElement('Name2');
        if (substr($job->getSupplierName(), 31, -1) == '') {
            $supplierName2 = './.';
        } else {
            $supplierName2 = substr($job->getSupplierName(), 31, -1);
        }
        $partnerName2T = $xml->createTextNode($supplierName2);
        $partnerName2E->appendChild($partnerName2T);
        $partnerAdresseE->appendChild($partnerName2E);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
        $partnerLKZE = $xml->createElement('LKZ');
        $partnerLKZT = $xml->createTextNode(substr($job->getSupplierCountry(), 0, 3));
        $partnerLKZE->appendChild($partnerLKZT);
        $partnerAdresseE->appendChild($partnerLKZE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
        $partnerPLZE = $xml->createElement('PLZ');
        $partnerPLZT = $xml->createTextNode(substr($job->getSupplierZip(), 0, 6));
        $partnerPLZE->appendChild($partnerPLZT);
        $partnerAdresseE->appendChild($partnerPLZE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partnerOrtE = $xml->createElement('Ort');
        $partnerOrtT = $xml->createTextNode($job->getSupplierCity());
        $partnerOrtE->appendChild($partnerOrtT);
        $partnerAdresseE->appendChild($partnerOrtE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partnerStrasseE = $xml->createElement('Strasse');
        $partnerStrasseT = $xml->createTextNode($job->getSupplierStreet());
        $partnerStrasseE->appendChild($partnerStrasseT);
        $partnerAdresseE->appendChild($partnerStrasseE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
        $partnerAdresseKE = $xml->createElement('Adresskennzeichen');
        $partnerAdresseKT = $xml->createTextNode("AZ");
        $partnerAdresseKE->appendChild($partnerAdresseKT);
        $partnerDatenE->appendChild($partnerAdresseKE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation */
        $partnerKommE = $xml->createElement('Kommunikation');
        $partnerDatenE->appendChild($partnerKommE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Typ */
        $partnerKommTypE = $xml->createElement('Typ');
        $partnerKommTypT = $xml->createTextNode("20");
        $partnerKommTypE->appendChild($partnerKommTypT);
        $partnerKommE->appendChild($partnerKommTypE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->Nummer */
        $partnerKommNrE = $xml->createElement('Nummer');
        $partnerKommNrT = $xml->createTextNode($job->getSupplierTelephone() !== null ? $job->getSupplierTelephone() : '0');
        $partnerKommNrE->appendChild($partnerKommNrT);
        $partnerKommE->appendChild($partnerKommNrE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-TYP2 */
        $partnerKommTyp2E = $xml->createElement('KOMM-TYP2');
        $partnerKommTyp2T = $xml->createTextNode("50");
        $partnerKommTyp2E->appendChild($partnerKommTyp2T);
        $partnerKommE->appendChild($partnerKommTyp2E);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Kommunikation-->KOMM-NR2 */
        $partnerKommNr2E = $xml->createElement('KOMM-NR2');
        $partnerKommNr2T = $xml->createTextNode("+49 30 80908347");
        $partnerKommNr2E->appendChild($partnerKommNr2T);
        $partnerKommE->appendChild($partnerKommNr2E);

        /* SCHADENORT (AV) */
        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten */
        $partnerDatenSOE = $xml->createElement('Partnerdaten');
        $partnerE->appendChild($partnerDatenSOE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse */
        $partnerAdresseSOE = $xml->createElement('Adresse');
        $partnerDatenSOE->appendChild($partnerAdresseSOE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->LKZ */
        $partnerLKZSOE = $xml->createElement('LKZ');
        $partnerLKZSOT = $xml->createTextNode($job->getDamageCountry());
        $partnerLKZSOE->appendChild($partnerLKZSOT);
        $partnerAdresseSOE->appendChild($partnerLKZSOE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->PLZ */
        $partnerPLZSOE = $xml->createElement('PLZ');
        $partnerPLZSOT = $xml->createTextNode(substr($job->getDamageZip(), 0, 6));
        $partnerPLZSOE->appendChild($partnerPLZSOT);
        $partnerAdresseSOE->appendChild($partnerPLZSOE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Ort */
        $partnerOrtSOE = $xml->createElement('Ort');
        $partnerOrtSOT = $xml->createTextNode($job->getDamageCity());
        $partnerOrtSOE->appendChild($partnerOrtSOT);
        $partnerAdresseSOE->appendChild($partnerOrtSOE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresse-->Strasse */
        $partnerStrasseSOE = $xml->createElement('Strasse');
        $partnerStrasseSOT = $xml->createTextNode($job->getDamageStreet() !== null ? $job->getDamageStreet() : '0');
        $partnerStrasseSOE->appendChild($partnerStrasseSOT);
        $partnerAdresseSOE->appendChild($partnerStrasseSOE);

        /* XML-->GDV-->SachAngebotRechnung-->PartnerdatenBlock-->Partnerdaten-->Adresskennzeichen */
        $partnerAdresseKSOE = $xml->createElement('Adresskennzeichen');
        $partnerAdresseKSOT = $xml->createTextNode("AV");
        $partnerAdresseKSOE->appendChild($partnerAdresseKSOT);
        $partnerDatenSOE->appendChild($partnerAdresseKSOE);

        if (count($attachments) > 0) {
            foreach ($attachments as $att) {
                /* XML-->GDV-->SachAngebotRechnung-->Anhang */
                $anhangE = $xml->createElement('Anhang');
                $anhangE->setAttribute('Satzart', '4900');
                $anhangE->setAttribute('Versionsnummer', '003');
                $sachangebotRechnungE->appendChild($anhangE);

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Letzter */
                $anhangLetzterE = $xml->createElement('Letzter');
                $anhangLetzterT = $xml->createTextNode("0");
                $anhangLetzterE->appendChild($anhangLetzterT);
                $anhangE->appendChild($anhangLetzterE);

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Anhangsart */
                $anhangArtE = $xml->createElement('Anhangsart');
                if (strtoupper(substr($att->getPath(), -4)) == "JPEG") {
                    $anhangArtT = $xml->createTextNode(strtoupper("JPG"));
                } else {
                    $anhangArtT = $xml->createTextNode(strtoupper(substr($att->getPath(), -3)));
                }
                $anhangArtE->appendChild($anhangArtT);
                $anhangE->appendChild($anhangArtE);

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->VersionsnummerDatei */
                $anhangVersionE = $xml->createElement('VersionsnummerDatei');
                $anhangVersionT = $xml->createTextNode("1");
                $anhangVersionE->appendChild($anhangVersionT);
                $anhangE->appendChild($anhangVersionE);

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Anhangstyp */
                $anhangTypE = $xml->createElement('Anhangstyp');
                $anhangTypT = $xml->createTextNode($att->getReportType()); //SIEHE SATZNUMMER 4900
                $anhangTypE->appendChild($anhangTypT);
                $anhangE->appendChild($anhangTypE);

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Dateiname */
                $anhangNameE = $xml->createElement('Dateiname');
                $anhangNameT = $xml->createTextNode($att->getPath());
                $anhangNameE->appendChild($anhangNameT);
                $anhangE->appendChild($anhangNameE);

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Dateiname-kurz */
                $anhangKurzE = $xml->createElement('Dateiname-kurz');
                $anhangKurzT = $xml->createTextNode(substr($att->getPath(), 0, 6) . "~1");
                $anhangKurzE->appendChild($anhangKurzT);
                $anhangE->appendChild($anhangKurzE);
                switch ($att->getReportType()) {
                    case "01":
                        $attType = "Foto";
                        break;
                    case "02":
                        $attType = "Kostenvoranschlag";
                        break;
                    case "04":
                        $attType = "Gutachten";
                        break;
                    case "11":
                        $attType = "Abtretungserklärung";
                        break;
                    case "32":
                        $attType = "Abnahmebestätigung";
                        break;
                    case "34":
                        $attType = "Arbeitsnachweis";
                        break;
                    case "38":
                        $attType = "Fremdrechnung";
                        break;
                    case "39":
                        $attType = "Zwischenbericht";
                        break;
                    case "40":
                        $attType = "Messprotokoll";
                        break;
                    default:
                        $attType = "Sonstiges";
                        break;
                }

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Beschreibung */
                $anhangDescE = $xml->createElement('Beschreibung');
                $anhangDescT = $xml->createTextNode($attType); // DEPENDS ON Anhangstyp
                $anhangDescE->appendChild($anhangDescT);
                $anhangE->appendChild($anhangDescE);

                try {
                    $baseFile = file_get_contents('files/inbox/' . $att->getPath());
                    $base64 = base64_encode($baseFile);
                } catch (\Exception $e) {
                    throw new ConverterException('Konnte die Datei nicht finden: ' . $e->getMessage());
                }

                /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Inhalt */
                $anhangInhaltE = $xml->createElement('Inhalt');
                $anhangInhaltT = $xml->createTextNode($base64);
                $anhangInhaltE->appendChild($anhangInhaltT);
                $anhangE->appendChild($anhangInhaltE);
            }
        }

        /* XML-->GDV-->SachAngebotRechnung-->Anhang */
        $anhangE = $xml->createElement('Anhang');
        $anhangE->setAttribute('Satzart', '4900');
        $anhangE->setAttribute('Versionsnummer', '003');
        $sachangebotRechnungE->appendChild($anhangE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Letzter */
        $anhangLetzterE = $xml->createElement('Letzter');
        $anhangLetzterT = $xml->createTextNode("1");
        $anhangLetzterE->appendChild($anhangLetzterT);
        $anhangE->appendChild($anhangLetzterE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Anhangsart */
        $anhangArtE = $xml->createElement('Anhangsart');
        $anhangArtT = $xml->createTextNode("PDF");
        $anhangArtE->appendChild($anhangArtT);
        $anhangE->appendChild($anhangArtE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->VersionsnummerDatei */
        $anhangVersionE = $xml->createElement('VersionsnummerDatei');
        $anhangVersionT = $xml->createTextNode("1");
        $anhangVersionE->appendChild($anhangVersionT);
        $anhangE->appendChild($anhangVersionE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Anhangstyp */
        $anhangTypE = $xml->createElement('Anhangstyp');
        $anhangTypT = $xml->createTextNode($typeAttachment); //SIEHE SATZNUMMER 4900
        $anhangTypE->appendChild($anhangTypT);
        $anhangE->appendChild($anhangTypE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Dateiname */
        $anhangNameE = $xml->createElement('Dateiname');
        $anhangNameT = $xml->createTextNode($data['project'] . " " . $data['type'] . ".pdf");
        $anhangNameE->appendChild($anhangNameT);
        $anhangE->appendChild($anhangNameE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Dateiname-kurz */
        $anhangKurzE = $xml->createElement('Dateiname-kurz');
        $anhangKurzT = $xml->createTextNode(substr($data['project'] . " " . $data['type'] . ".pdf", 0, 6) . "~1");
        $anhangKurzE->appendChild($anhangKurzT);
        $anhangE->appendChild($anhangKurzE);

        /* XML-->GDV-->SachAngebotRechnung-->Anhang-->Beschreibung */
        $anhangDescE = $xml->createElement('Beschreibung');
        $anhangDescT = $xml->createTextNode($data['type']); // DEPENDS ON Anhangstyp
        $anhangDescE->appendChild($anhangDescT);
        $anhangE->appendChild($anhangDescE);

        try {
            $baseFile = file_get_contents($filename);
            $base64 = base64_encode($baseFile);
        } catch (\Exception $e) {
            throw new ConverterException('Konnte die Datei nicht finden: ' . $e->getMessage());
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
        } catch (\Exception $e) {
            throw new ConverterException('Could not connect to SFTP: ' . $e->getMessage());
        }

        try {

            /* Save to Variable */
            $xml->saveXML();

            /* Save to local File */
            $xml->save('files/inbox/' . $typeName . $data['project'] . '.xml');

        } catch (\Exception $e) {
            throw new ConverterException('Could not save XML: ' . $e->getMessage());
        }

        try {
            $sftp->put_file($typeName . $data['project'] . '.xml', $xml->saveXML(), getenv('IS_TEST'));
        } catch (\Exception $e) {
            throw new ConverterException('Error Uploading Invoice: ' . $e->getMessage());
        }

        /* Create DB Entry */
        $fileDb = new File();
        $fileDb->setJob($job);
        $fileDb->setPath(str_replace('files/inbox/', '', $filename));
        $fileDb->setFiletype('invoice');
        $fileDb->setUploadDate(new \DateTime());
        $fileDb->setReportType(98);
        $fileDb->setFilename('Rechnung Excel');

        $job->setReceiptStatus(2);
        $job->setFinishDate(new \DateTime());

        $em->persist($fileDb);
        $em->persist($job);

        try {
            $em->flush();
        } catch (\Exception $e) {
            throw new ConverterException('MySQL Error: ' . $e->getMessage());
        }

    }

}