<?php

namespace App\Tests\Controller;

;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Service\BillingClient;
use App\Service\DecodingJwt;
use App\Tests\AbstractTest;
use App\Tests\Auth\Auth;
use App\Tests\Mock\BillingClientMock;
use JMS\Serializer\SerializerInterface;

class CourseTest extends AbstractTest
{
    // Стартовая страница курсов
    private $startingPath = '/courses';

    /** @var SerializerInterface */
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get(SerializerInterface::class);
    }

    // Переопределение метода для фикстур
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    // Метод вызова старовой страницы курсов
    public function getPath(): string
    {
        return $this->startingPath;
    }


    public function testAccessToPagesByRole(): void
    {
        // Для начала нам надо авторизоваться
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться под пользователем,
        // для проверке недоступности функционала пользователю
        $data = [
            'username' => 'artem@user.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        $client = self::getClient();

        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        self::assertNotEmpty($courses);
        // с помощью полученных курсов проходим все возможные страницы GET/POST связанных с курсом,
        // статус ответа должны получить 403, доступ только для администраторов
        foreach ($courses as $course) {
            self::getClient()->request('GET', $this->getPath() . '/' . $course->getId() . '/edit');
            self::assertResponseStatusCodeSame(403);

            self::getClient()->request('POST', $this->getPath() . '/new');
            self::assertResponseStatusCodeSame(403);

            self::getClient()->request('POST', $this->getPath() . '/' . $course->getId() . '/edit');
            self::assertResponseStatusCodeSame(403);
        }
    }

    // Проверка на корректный http-статус для всех GET/POST методов, по всем существующим курсам

    /**
     * @dataProvider urlProviderSuccessful
     * @param $url
     */
    public function testPageIsSuccessful($url): void
    {
        // Для начала нам надо авторизоваться
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться от админа,
        // т.к. не все страницы доступны обычному пользователю
        $data = [
            'username' => 'anna@admin.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        $client = self::getClient();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        // с помощью полученных курсов проходим все возможные страницы GET/POST связанных с курсом
        foreach ($courses as $course) {
            self::getClient()->request('GET', $this->getPath() . '/show/' . $course->getId());
            $this->assertResponseOk();

            self::getClient()->request('GET', $this->getPath() . '/' . $course->getId() . '/edit');
            $this->assertResponseOk();

            self::getClient()->request('POST', $this->getPath() . '/new');
            $this->assertResponseOk();

            self::getClient()->request('POST', $this->getPath() . '/' . $course->getId() . '/edit');
            $this->assertResponseOk();
        }
        //_______________________________________________________________
        // Пример проверки 404 ошибки, переход на несуществующие страницы
        $client = self::getClient();
        $url = $this->getPath() . '/show/13';
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }
    public function urlProviderSuccessful()
    {
        yield [$this->getPath() . '/'];
        yield [$this->getPath() . '/new'];
    }

    // Тесты главной страницы курсов
    public function testCourseIndex(): void
    {
        //________Авторизованный пользователь________
        // Авторизация
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться обычным пользователем
        $data = [
            'username' => 'artem@user.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        //  Получаем фактическое количество курсов из БД
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        $coursesCountFromBD = count($courses);

        // Получение количества курсов по фильтрации класса card
        $coursesCount = $crawler->filter('div.card')->count();

        // Проверка количества курсов на странице
        self::assertEquals($coursesCountFromBD, $coursesCount);

        //________Неавторизованным пользователем пробуем зайти на главную (разрешение должно быть одобрено)________
        // Выйдем из аккаунта
        $linkLogout = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($linkLogout);
        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        // Редиректит на страницу /
        $crawler = $client->followRedirect();
        $this->assertResponseRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());
        // Редиректит на страницу /courses/
        $crawler = $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        // И проверяем, что ответ от сервера ОК
        $this->assertResponseOk();
    }

    // Тесты страницы конкретного курса
    public function testCourseShow(): void
    {
        //____________Авторизованный пользователь____________
        // Авторизация
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться обычным пользователем
        $data = [
            'username' => 'artem@user.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);

        foreach ($courses as $course) {
            // Данные курсы доступны данному пользователю
            if ($course->getCode() === 'PPBIB' | $course->getCode() === 'CAMPB' | $course->getCode() === 'MSCB') {
                $crawler = self::getClient()->request('GET', $this->getPath() . '/show/' . $course->getId());

                $this->assertResponseOk();

                // Провекра количества уроков для конкретного курса
                $lessonsCount = $crawler->filter('ol > li')->count();
                // Получаем фактическое количество уроков для данного курса из БД
                $lessonsCountFromBD = count($course->getLessons());

                // Проверка количества уроков в курсе
                static::assertEquals($lessonsCountFromBD, $lessonsCount);

            } elseif ($course->getCode() === 'MSC') {
                // Данный курс будет недоступен, т.к. не куплен
                self::getClient()->request('GET', $this->getPath() . '/show/' . $course->getId());
                self::assertResponseStatusCodeSame(500);
            } else {
                // Првоерка перехода на курс, которого не существует
                self::getClient()->request('GET', $this->getPath() . '/show/' . 1000);
                self::assertResponseStatusCodeSame(404);
            }
        }
        //________Неавторизованный пользователь (доступ к бесплатным курсам)________
        // Выйдем из аккаунта
        $client = self::getClient();
        $linkLogout = $crawler->selectLink('Выход')->link();
        $crawler = $client->click($linkLogout);
        $this->assertResponseRedirect();
        self::assertEquals('/logout', $client->getRequest()->getPathInfo());

        // Редиректит на страницу /
        $crawler = $client->followRedirect();
        $this->assertResponseRedirect();
        self::assertEquals('/', $client->getRequest()->getPathInfo());
        // Редиректит на страницу /courses/
        $crawler = $client->followRedirect();
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        $this->assertResponseOk();

        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);

        foreach ($courses as $course) {
            // Данный курсы бесплатны, доступны даже не авторизованным пользователям
            if (
                $course->getCode() === 'MSCB' |
                $course->getCode() === 'PPBIB'|
                $course->getCode() === 'CAMPB'
            ) {
                $crawler = self::getClient()->request('GET', $this->getPath() . '/show/' . $course->getId());
                $this->assertResponseOk();

                // Провекра количества уроков для конкретного курса
                $lessonsCount = $crawler->filter('ol > li')->count();
                // Получаем фактическое количество уроков для данного курса из БД
                $lessonsCountFromBD = count($course->getLessons());

                // Проверка количества уроков в курсе
                static::assertEquals($lessonsCountFromBD, $lessonsCount);
            } else {
                // Доступ запрещен
                self::getClient()->request('GET', $this->getPath() . '/show/' . $course->getId());
                self::assertResponseStatusCodeSame(500);
            }
        }
    }

    // Тест страницы добавления курса,
    public function testCourseNewAddValidFieldsAndDeleteCourse(): void
    {
        // Тест страницы добавления курса с валидными значениями,
        // а также проверка редиректа на страницу с курсами и изменения их количества
        // после добавления курса. А также проверить удаление курса.

        // Для начала нам надо авторизоваться
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться от админа,
        // т.к. не весь функционал доступен обычному пользователю
        $data = [
            'username' => 'anna@admin.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Заполнение полей формы
        $client->submitForm('course__add', [
            'course[code]' => 'KSADDFOAS',
            'course[name]' => 'Новый курс',
            'course[price]' => 1300,
            'course[type]' => 'rent',
            'course[description]' => 'Тестовый курс',
        ]);
        // Проверка редиректа на главную страницу
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();

        // Получение количества курсов
        $coursesCount = $crawler->filter('div.card')->count();

        // Проверка обновленного количества курсов на странице
        // (можно сранивать и с фактическим количеством курсов из БД)
        self::assertEquals(8, $coursesCount);

        // Перейдём на страницу добавленного курса
        $link = $crawler->filter('a.card-link')->last()->link();
        $client->click($link);
        $this->assertResponseOk();

        // Нажимаме кнопку удалить
        $client->submitForm('course__delete');
        // Проверка редиректа на галвную страницу
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // Проверяем количество курсов после удаления
        $coursesCount = $crawler->filter('div.card')->count();
        self::assertEquals(7, $coursesCount);

        //________________________________________________________
        // Тест страницы добавления курса с невалидным полем code
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('course__add', [
            'course[code]' => '',
            'course[name]' => 'Новый курс',
            'course[price]' => 1300,
            'course[type]' => 'rent',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения более 255 символов в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('course__add', [
            'course[code]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsd',
            'course[name]' => 'Новый курс',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());

        //________________________________________________________
        // Тест страницы добавления курса с невалидным полем name
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле name
        // Заполнение полей формы
        $crawler = $client->submitForm('course__add', [
            'course[code]' => 'NORMALCODE',
            'course[name]' => '',
            'course[price]' => 1300,
            'course[type]' => 'rent',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения более 255 символов в поле name
        // Заполнение полей формы
        $crawler = $client->submitForm('course__add', [
            'course[code]' => 'NORMALCODE',
            'course[name]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsd',
            'course[price]' => 1300,
            'course[type]' => 'rent',
            'course[description]' => 'Тестовый курс',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());

        //______________________________________________________________
        // Тест страницы добавления курса с невалидным полем description
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.course-new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи значения более 1000 символоами в поле description
        // Заполнение полей формы
        $crawler = $client->submitForm('course__add', [
            'course[code]' => 'NORMALCODE',
            'course[name]' => 'Новый курс',
            'course[price]' => 1300,
            'course[type]' => 'rent',
            'course[description]' => 'sadjskadkasjdddddddasdkkkkkk
            kkkkkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllll
            llllllllllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjjjjjjjjjjjjasdllllllllllllllllllllllllllllsadkasdk
            asdknqowhduiqbwdnoskznmdoasmpodpasmdpamsdsadddddddddda
            sssssssssssssssssssssssssssssssssssssssssssssssddddddd
            dddddddddddddddddddddddddddddddddddddddddddddddddddddd
            dddddddddddddddddddddddddddsssssssssssssssssssssssssss
            ssssssssssssssssssssssssssssssssssssssssssssssssssssss
            ssssssssssssssssssssssssssssssssssssssssssssssssssssss
            sssssadjskadkasjdddddddasdkkkkkkkkkkkkkkkkasdkkkkkkkkk
            kkkkkkkkkasdllllllllllllllllllllllllllllllllllllllllll
            asdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjasdllll
            llllllllllllllllllllllllsadkasdkasdknqowhduiqbwdnoskzn
            mdoasmpodpasmdpamsdsaddddddddddasssssssssssssssssssssss
            ssssssssssssssssssssssssdddddddddddddddddddddddddddddd
            dddddddddddddddddddddddddddddddddddddddddddddddddddddd
            ddddssssssssssssssssssssssssssssssssssssssssssssssssss
            sssssssssssssssssssssss',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());
    }

    // Тест страницы редактирование курса, а именно - изменение полей и редирект на испарвленный курс
    // Проверка валдиации формы мы проверили в тестах выше
    public function testCourseEditAndCheckFields(): void
    {
        // Для начала нам надо авторизоваться
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться от админа,
        // т.к. не весь функционал доступен обычному пользователю
        $data = [
            'username' => 'anna@admin.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Перейдем к редактированию, допустим, первого курса на странице
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Нажимаем кнопку редактирования
        $link = $crawler->filter('a.course__edit')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Изменим значения полей формы
        $form = $crawler->selectButton('course__add')->form();
        // Получим id кода из формы
        $em = self::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => $form['course[code]']->getValue()]);
        // Изменяем поля в форме
        $form['course[code]'] = 'NORMALCODE123';
        $form['course[name]'] = 'NORMAL COURSE';
        $form['course[price]'] = 1300;
        $form['course[type]'] = 'rent';
        $form['course[description]'] = 'TEST COURSE';
        // Отправляем форму
        $client->submit($form);

        // Проверяем редирект на изменённый курса
        $this->assertResponseRedirect();
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }

    // Тест оплаты курса
    public function testPayCourse(): void
    {
        // Авторизация
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации
        $data = [
            'username' => 'artem@user.com',
            'password' => '123654'
        ];

        $client = self::getClient();

        //__________________Успешно__________________
        $code = 'AREND199230SKLADS';
        $crawler = $client->request('GET', $this->getPath() . '/pay?course_code='. $code);
        // Проверяем редирект на изменённый курса
        $this->assertResponseRedirect();
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        //__________________Неуспешно__________________
        $code = 'NONE';
        $crawler = $client->request('GET', $this->getPath() . '/pay?course_code='. $code);
        // Проверяем редирект на изменённый курса
        $this->assertResponseRedirect();
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }

}