<?php

namespace App\Controller;

use App\Dto\Response\CurrentUserDto;
use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Model\TransactionDto;

class ProfileController extends AbstractController
{
    private DecodingJwt $decodingJwt;
    public function __construct(DecodingJwt $decodingJwt, BillingClient $billingClient, SerializerInterface $serializer)
    {
        $this->decodingJwt = $decodingJwt;
        $this->billingClient = $billingClient;
        $this->serializer = $serializer;

    }
    /**
     * @Route("/profile", name="profile")
     * @IsGranted("ROLE_USER")
     */
    public function index(BillingClient $billingClient): Response
    {
        /** @var CurrentUserDto $currentUser */
        $currentUser = $billingClient->getUser($this->getUser(), $this->decodingJwt);

        return $this->render('profile/index.html.twig', [
            'email' => $currentUser['username'],
            'role' => in_array('ROLE_SUPER_ADMIN', $currentUser['roles']) ? 'Администратор' : 'Пользователь',
            'balance' => $currentUser['balance']
        ]);
    }

    /**
     * @Route("/history", name="profile_history")
     * @param $courseRepository
     * @return Response
     * @throws \Exception
     */
    public function history(CourseRepository $courseRepository): Response
    {
        try {
            /** @var TransactionDto[] $transactionsDto */
            $transactionsDto = $this->billingClient->transactionsHistory($this->getUser());
            $courses = $courseRepository->findAll();
            $coursesData = [];
            foreach ($transactionsDto as $transactionDto) {
                foreach ($courses as $course) {
                    if ($transactionDto && $transactionDto->getCourseCode() === $course->getCode()) {
                        $coursesData[$transactionDto->getCourseCode()] = [
                            'id' => $course->getId(),
                            'name' => $course->getName(),
                        ];
                    }
                }
            }
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->render('profile/history.html.twig', [
            'transactionsDto' => $transactionsDto,
            'courses' => $coursesData,
        ]);
    }

}