<?php

namespace App\Controller;

use App\Dto\Response\CurrentUserDto;
use App\Service\BillingClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    /**
     * @Route("/profile", name="profile")
     * @IsGranted("ROLE_USER")
     */
    public function index(BillingClient $billingClient): Response
    {
        /** @var CurrentUserDto $currentUser */
        $currentUser = $billingClient->getUser($this->getUser()->getApiToken());

        return $this->render('profile/index.html.twig', [
            'email' => $currentUser->username,
            'role' => in_array('ROLE_SUPER_ADMIN', $currentUser->roles) ? 'Администратор' : 'Пользователь',
            'balance' => $currentUser->balance
        ]);
    }
}