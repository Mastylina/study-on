<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\LessonRepository;
use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use App\Model\CourseDto;
use App\Model\PayDto;
use App\Model\TransactionDto;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @Route("/courses")
 */
class CourseController extends AbstractController
{
    /**
     * @Route("/", name="app_course_index", methods={"GET"})
     */
    public function index(CourseRepository $courseRepository,
                          BillingClient $billingClient,
                          DecodingJwt $decodingJwt): Response
    {

        try {
            /** @var CourseDto[] $coursesDto */
            $coursesDto = $billingClient->getAllCourses();
// Создаем массив, где вместо индексов code, для удобства работы с курсами
            $coursesInfoBilling = [];
            foreach ($coursesDto as $courseDto) {
                $coursesInfoBilling[$courseDto->getCode()] = [
                    'course' => $courseDto,
                    'transaction' => null,
                ];
            }
// Если пользователь не авторизован
            if (!$this->getUser()) {
                return $this->render('course/index.html.twig', [
                    'courses' => $courseRepository->findBy([], ['id' => 'ASC']),
                    'coursesInfoBilling' => $coursesInfoBilling,
                ]);
            }

// Нам нужны транзакции оплаты курсов пользователя, а также нам нужно пропустить курсы,
// аренда которых уже завершилась
            /** @var TransactionDto[] $transactionsDto */
            $transactionsDto = $billingClient->transactionsHistory($this->getUser(), 'type=payment&skip_expired=true');

            foreach ($coursesDto as $courseDto) {
                foreach ($transactionsDto as $transactionDto) {
                    if ($transactionDto->getCourseCode() === $courseDto->getCode()) {
                        $coursesInfoBilling[$courseDto->getCode()] = [
                            'course' => $courseDto,
                            'transaction' => $transactionDto,
                        ];
                        break;
                    }

                    $coursesInfoBilling[$courseDto->getCode()] = [
                        'course' => $courseDto,
                        'transaction' => null,
                    ];
                }
            }

// Получим баланс пользователя
            $data = $billingClient->getUser($this->getUser(), $decodingJwt);
            $balance = $data['balance'];

            return $this->render('course/index.html.twig', [
                'courses' => $courseRepository->findBy([], ['id' => 'ASC']),
                'coursesInfoBilling' => $coursesInfoBilling,
                'balance' => $balance,
            ]);
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @Route("/pay", name="course_pay", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function pay(Request $request, BillingClient $billingClient): Response
    {
        // Откуда перешли на данную страницу для обратного редиректа
        $referer = $request->headers->get('referer');

        $courseCode = $request->get('course_code');

        try {
            /** @var PayDto $payDto */
            $payDto = $billingClient->paymentCourse($this->getUser(), $courseCode);

            // flash message
            $this->addFlash('success', 'Оплата прошла успешно!');
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->redirect($referer);
    }

    /**
     * @Route("/show/{id}", name="app_course_show", methods={"GET"})
     */
    public function show(Course $course, LessonRepository $lessonRepository, BillingClient $billingClient): Response
    {

        try {
            // Проверим сначала является ли пользователь администратором
            if ($this->getUser() && $this->getUser()->getRoles()[0] === 'ROLE_SUPER_ADMIN') {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            // Далее проверим, что курс который собираются открыть - бесплатный
            /** @var CourseDto $courseDto */
            $courseDto = $billingClient->getCourse($course->getCode());

            // Если он бесплатный, тогда ОК
            if ($courseDto->getType() === 'free') {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

            // Если курс платный, а пользователь не авторизован, то выдаем ошибку
            if (!$this->getUser()) {
                throw new AccessDeniedException('Доступ запрещен, авторизуйтесь или зарегистрируйтесь.');
            }

            // Если пользователь авторизован, то нам надо проверить историю его транзакций с этим курсом, имеет ли
            // пользователь доступ к нему

            // Нам нужно найти транзакцию оплаты курса пользователем,
            // также мы отбрасываем курсы, аренда которых уже завершилась

            /** @var TransactionDto[] $transactionsDto */
            $transactionDto = $billingClient->transactionsHistory(
                $this->getUser(),
                'type=payment&course_code='. $course->getCode() . '&skip_expired=true'
            );
            // Если такая тразакция существует, то мы выдадим курс
            if ($transactionDto !== []) {
                $lessons = $lessonRepository->findByCourse($course);

                return $this->render('course/show.html.twig', [
                    'course' => $course,
                    'lessons' => $lessons,
                ]);
            }

             // Иначе ошибка
            throw new AccessDeniedException('Доступ запрещен.');
        } catch (AccessDeniedException $e) {
            throw new \Exception($e->getMessage());
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @Route("/new", name="app_course_new", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN", statusCode=403, message="Доступ только для администратора")
     */
    public function new(Request $request, BillingClient $billingClient): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();

                $courseDto->setTitle($form->get('name')->getData());
                $courseDto->setCode($form->get('code')->getData());
                $courseDto->setType($form->get('type')->getData());
                if ('free' === $form->get('type')->getData()) {
                    $courseDto->setPrice(0);
                } else {
                    $courseDto->setPrice($form->get('price')->getData());
                }

                // Отдаем запрос к биллингу, и получаем ответ
                $response = $billingClient->newCourse($this->getUser(), $courseDto);

                // Если всё ок, то добавляем курс в БД, иначе вызывается исключение с ошибкой
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($course);
                $entityManager->flush();
            } catch (BillingUnavailableException | \Exception $e) {
                return $this->render('course/new.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            }

            // flash message
            $this->addFlash('success', 'Новый курс успешно добавлен!');
            return $this->redirectToRoute('app_course_index');
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="app_course_edit", methods={"GET", "POST"})
     * @IsGranted("ROLE_SUPER_ADMIN", statusCode=403, message="Доступ только для администратора")
     */
    public function edit(Request $request, Course $course, BillingClient $billingClient): Response
    {
        // Проверим что действительно есть такой курс
        $courseCode = $course->getCode();
        try {
            $courseTemp = $billingClient->getCourse($courseCode);
        } catch (BillingUnavailableException $e) {
            throw new \Exception($e);
        }

        // Если курс существует, то создаем форму
        $form = $this->createForm(
            CourseType::class,
            $course,
            [
                'price' => $courseTemp->getPrice(),
                'type' => $courseTemp->getType(),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $courseDto = new CourseDto();
                $courseDto->setTitle($form->get('name')->getData());
                $courseDto->setCode($form->get('code')->getData());
                $courseDto->setType($form->get('type')->getData());
                if ('free' === $form->get('type')->getData()) {
                    $courseDto->setPrice(0);
                } else {
                    $courseDto->setPrice($form->get('price')->getData());
                }

                // Отдаем запрос к биллингу, и получаем ответ
                $response = $billingClient->editCourse($this->getUser(), $courseCode, $courseDto);

                // Если всё ок, то обновляем даныне о курсе в БД
                $this->getDoctrine()->getManager()->flush();
            } catch (BillingUnavailableException | \Exception $e) {
                return $this->render('course/edit.html.twig', [
                    'course' => $course,
                    'form' => $form->createView(),
                    'errors' => $e->getMessage(),
                ]);
            }

            return $this->redirectToRoute('app_course_show', [
                'id' => $course->getId(),
            ]);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", requirements={"id"="\d+"}, name="app_course_delete", methods={"POST"})
     * @IsGranted("ROLE_SUPER_ADMIN", statusCode=403, message="Доступ только для администратора")
     */
    public function delete(Request $request, Course $course, CourseRepository $courseRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            foreach ($course->getLessons() as $lesson) {
                $entityManager->remove($lesson);
                $entityManager->flush();
            }
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index');
    }
}
