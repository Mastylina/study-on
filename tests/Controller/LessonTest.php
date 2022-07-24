<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\AbstractTest;
use App\Tests\Auth\Auth;
use JMS\Serializer\SerializerInterface;

class LessonTest extends AbstractTest
{
    // Стартовая страница курсов
    private $startingPathCourse = '/courses';
    // Стартовая страница уроков
    private $startingPathLesson = '/lesson';
    /** @var SerializerInterface */
    private $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get(SerializerInterface::class);
    }

    // Метод вызова старовой страницы курсов
    public function getPathCourse(): string
    {
        return $this->startingPathCourse;
    }

    // Метод вызова старовой страницы уроков
    public function getPathLesson(): string
    {
        return $this->startingPathLesson;
    }

    // Переопределение метода для фикстур
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    // Проверка на корректный http-статус для всех уроков по всем курсам
    public function testPageIsSuccessful(): void
    {
        // Для начала нам надо авторизоваться
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться обычным пользователем
        $data = [
            'username' => 'artem@user.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        // Перейдём на главную с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Переходим по курсам к их урокам
        $courseLinks = $crawler->filter('a.card-link')->links();
        foreach ($courseLinks as $courseLink) {
            $crawler = $client->click($courseLink);
            $this->assertResponseOk();

            // Переходим по всем урокам данного курса и проверям, что всё ок
            $lessonLinks = $crawler->filter('a.card-link')->links();
            foreach ($lessonLinks as $lessonLink) {
                $crawler = $client->click($lessonLink);
                self::assertResponseIsSuccessful();
            }
        }

        //________________________________________________________
        // Провекра перехода на несуществующий урок, 404
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathLesson() . '/-1');
        $this->assertResponseNotFound();
    }

    // Тест страницы добавления урока с валидными значениями,
    // А также проверить удаление урока
    // А также редирект на страницу курса после добалвения и удаления урока
    public function testLessonNewAddValidFieldsAndDeleteCourse(): void
    {        // Для начала нам надо авторизоваться
        $auth = new Auth();
        $auth->setSerializer($this->serializer);
        // Формируем данные для авторизации, будем авторизовываться администратором, т.к. пользователю не доступен
        // весь функционал
        $data = [
            'username' => 'anna@admin.com',
            'password' => '123654'
        ];
        $requestData = $this->serializer->serialize($data, 'json');
        $crawler = $auth->auth($requestData);

        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому, допустим, курсу по ссылке
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Заполнение полей формы
        $form = $crawler->selectButton('lesson__add')->form();
        // Изменяем поля в форме
        $form['lesson[name]'] = 'Новый урок';
        $form['lesson[content]'] = 'Тестовый материал';
        $form['lesson[number]'] = '1';
        // Получим id созданного курса
        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['id' => $form['lesson[course]']->getValue()]);
        self::assertNotEmpty($course);
        // Отправляем форму
        $client->submit($form);
        // Проверка редиректа на страницу курса
        self::assertTrue($client->getResponse()->isRedirect($this->getPathCourse() . '/show/' . $course->getId()));
        // Переходим на страницу добавленного урока
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // Перейдём на страницу добавленного урока
        $link = $crawler->filter('ol > li > a')->first()->link();
        $client->click($link);
        $this->assertResponseOk();

        // Нажимаме кнопку удалить
        $client->submitForm('lesson__delete');
        // Проверка редиректа на страницу курса
        self::assertTrue($client->getResponse()->isRedirect($this->getPathCourse() . '/show/' . $course->getId()));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        //________________________________________________________
        // Тест страницы добавления курса с невалидным полем name
        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому, допустим, курсу по ссылке
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => '',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => '13',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения более 255 символов в поле code
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsd',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => '13',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());

        //________________________________________________________
        // Тест страницы добавления урока с невалидным полем material
        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому, допустим, курсу по ссылке
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле material
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => '',
            'lesson[number]' => '13',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        //________________________________________________________
        // Тест страницы добавления урока с невалидным полем number
        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому, допустим, курсу по ссылке
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к добавлению  (форме)
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Проверка передачи пустого значения в поле number
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Новый материал',
            'lesson[number]' => '',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Проверка передачи значения неверной валидации номера
        // Заполнение полей формы
        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => 'Новый материал',
            'lesson[number]' => 'sadk123!!_',
        ]);
        // Список ошибок
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('This value is not valid.', $error->text());
    }

}
