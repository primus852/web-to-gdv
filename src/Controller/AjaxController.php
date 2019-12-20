<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Insurance;
use App\Entity\Job;
use App\Util\Converter\Converter;
use App\Util\Converter\ConverterException;
use App\Util\Gdv\Gdv;
use App\Util\Gdv\GdvException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface as ObjectManager;
use Exception;
use primus852\ShortResponse\ShortResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AjaxController extends AbstractController
{

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/admin/_ajax/_createManual", name="ajaxCreateManual")
     * @param Request $request
     * @param ManagerRegistry $em
     * @return JsonResponse
     */
    public function createManual(Request $request, ObjectManager $em)
    {

        /**
         * Gather Vars
         */
        $referenceNo = $request->get('referenceNo');
        $insuranceContractNo = $request->get('insuranceContractNo');
        $insuranceDamageNo = $request->get('insuranceDamageNo');
        $insuranceVuNo = $request->get('insuranceVuNo');
        $id = $request->get('insuranceId');

        $ins = $em->getRepository(Insurance::class)->find($id);

        if($ins === null){
            return ShortResponse::error('Bitte wählen Sie eine Versicherung');
        }

        try {
            Gdv::manual($referenceNo, $insuranceContractNo, $insuranceDamageNo, $insuranceVuNo, $ins->getDlNo(), $ins->getDlpNo(), $em);
        } catch (GdvException $e) {
            return ShortResponse::exception('Fehler bei der Erstellung, bitte versuchen Sie es erneut', $e->getMessage());
        }

        return ShortResponse::success('Auftrag erfolgreich übermittelt');


    }

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/admin/_ajax/_createReceipt", name="ajaxCreateReceipt")
     * @param Request $request
     * @param ManagerRegistry $em
     * @return JsonResponse
     */
    public function createReceipt(Request $request, ObjectManager $em)
    {

        if($request->get('id') === '' || $request->get('id') === null){
            return ShortResponse::error('Auftragsnummer leer');
        }

        $job = $em->getRepository(Job::class)->find($request->get('id'));

        if ($job === null) {
            return ShortResponse::error('Auftrag nicht vorhanden');
        }

        if ($request->get('reason') === 'NONE') {
            return ShortResponse::error('Auftrag nicht quittiert, es wurde kein Grund ausgewählt');
        }

        $status = $request->get('status') === 'accept' ? true : false;

        try {
            Gdv::receipt($em, $job, $status, $request->get('reason'));
        } catch (GdvException $e) {
            return ShortResponse::exception('Fehler bei der Quittierung', $e->getMessage());
        }

        return ShortResponse::success('Auftrag quittiert');

    }

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/admin/_ajax/_uploadFile", name="ajaxUploadFile")
     * @param Request $request
     * @param ManagerRegistry $em
     * @return JsonResponse
     * @throws Exception
     */
    public function uploadFile(Request $request, ObjectManager $em)
    {

        $file = $request->files->get('fileName');
        $type = $request->get('type');
        if ($type == "01") {
            $type = 'attachment';
        }

        $jobId = $request->get('jobId');
        $reportType = $request->get('reportType');

        /**
         * Create files folder if it does not exist
         */
        $fs = new Filesystem();
        if (!$fs->exists('files/inbox')) {
            $fs->mkdir('files/inbox');
        }

        $path = 'files/inbox';

        /* Get Jpb */
        $job = $em->getRepository(Job::class)->find($jobId);

        if ($job === null) {
            return ShortResponse::error('Auftrag nicht vorhanden');
        }

        if (!is_null($file)) {

            $filename = uniqid() . "." . $file->getClientOriginalExtension();
            $file->move($path, $filename);

            /* Check if Excel File */
            if ($type == 'invoice') {

                if (strpos(strtolower($file->getClientOriginalExtension()), 'xls') !== FALSE) {

                    /**
                     * Extract Data from Excel
                     */
                    try {
                        $data = Converter::extract_excel($path . '/' . $filename);
                    } catch (ConverterException $e) {
                        return ShortResponse::exception('Konnte Daten aus Excel nicht extrahieren', $e->getMessage());
                    }

                    try {
                        Converter::generate_invoice($job, $data, $path . '/' . $filename, $em);
                    } catch (ConverterException $e) {
                        return ShortResponse::exception('Konnte Rechnung nicht erstellen', $e->getMessage() . ' Filename: ' . $filename . ' Path: ' . $path);
                    }

                    return ShortResponse::success('Rechnung erstellt', array(
                        'type' => 'invoice',
                    ));
                }

                return ShortResponse::error('Es handelt sich nicht um eine Excel Datei', array(
                    'type' => 'invoice',
                ));


            }

            if ($type == 'pdfinvoice' && strpos(strtolower($file->getClientOriginalExtension()), 'pdf') === FALSE) {

                return ShortResponse::error('Es handelt sich nicht um eine PDF Datei', array(
                    'type' => $type,
                ));
            }

            /* Just Upload & to DB */
            $fileDb = new File();
            $fileDb->setJob($job);
            $fileDb->setPath($filename);
            $fileDb->setFiletype($type);
            $fileDb->setUploadDate(new DateTime());
            $fileDb->setReportType($reportType);
            $fileDb->setFilename($file->getClientOriginalName());

            $em->persist($fileDb);

            try {
                $em->flush();
            } catch (Exception $e) {
                return ShortResponse::mysql($e->getMessage());
            }

            if ($type == 'report') {

                try {
                    Gdv::report($job, $file->getClientOriginalExtension(), $filename, $reportType);
                } catch (GdvException $e) {
                    return ShortResponse::exception('Fehler bei der Quittierung', $e->getMessage());
                }

                return ShortResponse::success('Zwischenbericht wurde hochgeladen', array(
                    'type' => 'report'
                ));

            }


            $message = 'Datei in Datenbank eingetragen.';
            if ($type == 'pdfinvoice') {
                $message = 'PDF hochgeladen, aktualisiere Seite...';
            }

            if ($type == 'attachment') {
                $message = 'Datei an Rechnung gehängt.';
            }

            return ShortResponse::success($message, array(
                'type' => $type,
            ));
        }

        return ShortResponse::error('Upload fehlgeschlagen, bitte versuchen Sie es erneut');

    }
}
