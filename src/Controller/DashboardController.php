<?php

namespace App\Controller;

use App\Entity\Insurance;
use App\Entity\Job;
use App\Util\Gdv\Gdv;
use App\Util\Gdv\GdvException;
use Doctrine\Common\Persistence\ObjectManager;
use primus852\ShortResponse\ShortResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{

    /**
     * @Route("/", name="home")
     */
    public function home()
    {

        return $this->redirectToRoute('dashboard');

    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/admin/archiv", name="archive")
     */
    public function archive()
    {
        /* Get Job */
        $jobs = $this->getDoctrine()->getRepository(Job::class)->findBy(array(
            'receiptStatus' => 2
        ));

        return $this->render(
            'dashboard/archive.html.twig', array(
                'jobs' => $jobs,
            )
        );
    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/admin/manuelle-eingabe", name="manual")
     */
    public function manual()
    {
        /* Get all Insurances */
        $insurances = $this->getDoctrine()->getRepository(Insurance::class)->findAll();

        return $this->render(
            'dashboard/manual.html.twig', array(
                'insurances' => $insurances,
            )
        );
    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/admin/offen", name="open")
     */
    public function open()
    {
        /* Get Job */
        $jobs = $this->getDoctrine()->getRepository(Job::class)->findBy(array(
            'receiptStatus' => 1
        ));

        return $this->render(
            'dashboard/open.html.twig', array(
                'jobs' => $jobs,
            )
        );
    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/admin/druck/{id}", name="printJob", defaults={"id"="0"})
     * @param $id
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    public function printAction($id)
    {

        /* Get Job */
        $job = $this->getDoctrine()->getRepository(Job::class)->findOneBy(array(
            'id' => $id
        ));

        if ($job === null) {
            return ShortResponse::error('Auftrag nicht vorhanden');
        }

        return $this->render(
            'dashboard/printJob.html.twig', array(
                'job' => $job,
            )
        );

    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/admin/dashboard", name="dashboard")
     */
    public function dashboard()
    {

        /* Entity Manager */
        $em = $this->getDoctrine()->getManager();

        $jobs = $em->getRepository(Job::class)->findBy(array(
            'receipt' => false,
        ));

        $unchecked = $em->getRepository(Job::class)->findBy(array(
            'receiptStatus' => 2,
            'receiptMessage' => 'OK',
        ));

        $ucCount = 0;
        foreach($unchecked as $ju){

            if($ju->getResults() === null){
                $ucCount++;
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'jobs' => $jobs,
            'uc' => $ucCount,
        ]);
    }

    /**
     * @Route("/bestaetigung/{crypt}", name="remoteConfirm", defaults={"crypt"="0"})
     * @param $crypt
     * @return Response
     * @throws GdvException
     */
    public function remoteConfirm($crypt)
    {

        /* @var $em ObjectManager */
        $em = $this->getDoctrine()->getManager();

        $job = $em->getRepository(Job::class)->findOneBy(array(
            'crypt' => $crypt,
            'receipt' => false,
        ));


        if ($job === null) {
            throw new NotFoundHttpException();
        }


        try {
            Gdv::receipt($em, $job, true, 'OK');
        } catch (GdvException $e) {
            throw new GdvException('Fehler bei der Receipt-Erstellung: ' . $e->getMessage());
        }

        // TODO: Landing Page

        return new Response('Auftrag quittiert');

    }
}
