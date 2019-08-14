<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Job;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RenderController extends AbstractController
{

    public function version()
    {
        return new Response('TBA');
    }

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/admin/_render/_jobDetails", name="jobDetails")
     * @param Request $request
     * @return Response
     */
    public function jobDetailsAction(Request $request)
    {

        /* Entity Manager */
        $em = $this->getDoctrine()->getManager();

        $job = $em->getRepository(Job::class)->find($request->get('id'));

        if($job === null){
            throw new NotFoundHttpException('Auftrag nicht vorhanden');
        }

        return $this->render('render/jobDetails.html.twig', array(
            'job' => $job,
        ));
    }

    /**
     * @Security("is_granted('ROLE_USER')")
     * @Route("/admin/_render/_file/{filename}", name="loadFile", defaults={"filename"="0"})
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function loadFileAction(string $filename)
    {

        /* Entity Manager */
        $em = $this->getDoctrine()->getManager();

        $file = $em->getRepository(File::class)->findOneBy(array(
            'path' => $filename,
        ));

        if($file === null){
            throw new NotFoundHttpException();
        }

        return $this->file('files/inbox/'.$file->getPath(), $file->getFilename(),ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
