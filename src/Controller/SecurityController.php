<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     * @param Request $request
     * @param AuthenticationUtils $utils
     * @return Response
     */
    public function login(Request $request, AuthenticationUtils $utils)
    {

        $error = $utils->getLastAuthenticationError();
        $last_username = $utils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'error' => $error,
            'last_username' => $last_username,
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
    }
}
