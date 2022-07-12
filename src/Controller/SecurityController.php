<?php

namespace App\Controller;

use App\Dto\Response\UserRegisterDto;
use App\Exception\BillingUnavailableException;
use App\Form\RegisterType;
use App\Model\UserDTO;
use App\Security\AppCustomAuthenticator;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;


class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
             return $this->redirectToRoute('app_course_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     *  @Route("/register", name="app_register")
     */

    public function register(Request $request, UserAuthenticatorInterface $authenticator, AppCustomAuthenticator $formAuthenticator, BillingClient $billingClient
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('profile');
        }

        $registerRequest = new UserRegisterDto();
        $form = $this->createForm(RegisterType::class, $registerRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $billingClient->register($registerRequest);
            } catch (BillingException $e) {
                return $this->render('security/registration.html.twig', [
                    'form' => $form->createView(),
                    'errors' => json_decode($e->getMessage(), true),
                ]);
            } catch (BillingUnavailableException $e) {
                return $this->render('security/registration.html.twig', [
                    'form' => $form->createView(),
                    'errors' => ['billing' => [$e->getMessage()]],
                ]);
            }

            return $authenticator->authenticateUser(
                $user,
                $formAuthenticator,
                $request
            );
        }

        return $this->render('security/registration.html.twig', [
            'form' => $form->createView(),
            'errors' => ''
        ]);
    }


}
