<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;

class CourseTest extends AbstractTest
{
    // стартовая страница
    private $startPath = '/courses';

    // переопределим метод
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    // вызовем стартовую страницу
    public function getPath(): string
    {
        return $this->startPath;
    }

    // проверим для всех GET/POST экшенов контроллеров, что возвращается корректный http-статус
    public function testHTTPStatus(): void
    {
        // ВызываетKernelTestCase::bootKernel(), и создает
        // "клиента", действующего как браузер
        $client = self::getClient();

        // Запросить конкретную страницу
        $crawler = $client->request('GET', '/');

        // Валидировать успешный ответ
        $this->assertResponseIsSuccessful();

        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();

        // Получение списка курсов
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        //проверка на пустоту
        self::assertNotEmpty($courses);

        // получение количества курсов со страницы
        $coursesCountBD = count($courses);
        // Получение количества курсов по фильтрации класса card
        $coursesCount = $crawler->filter('div.card')->count();
        // Проверка на соответсвие кол-ва курсов на странице и кол-ва курсов в бд
        self::assertEquals($coursesCountBD, $coursesCount);

        // Запрос страницы создания нового курса
        self::getClient()->request('POST', $this->getPath() . '/new');
        $this->assertResponseOk();
        $findValue = $em->getRepository(Course::class)->findOneBy([]);
        // проход по курсу и соответствующим страницам
        self::getClient()->request('GET', $this->getPath() . '/' . $findValue->getId());
        $this->assertResponseOk();

        self::getClient()->request('GET', $this->getPath() . '/' . $findValue->getId() . '/edit');
        $this->assertResponseOk();

        self::getClient()->request('POST', $this->getPath() . '/' . $findValue->getId() . '/edit');
        $this->assertResponseOk();

        // проверка, что при обращении по несуществующему URL курса/урока и так далее отдается 404
        // присвоим переменной не существующий путь
        $url = $this->getPath() . '/0';
        // запросим не существующую страницу
        $client->request('GET', $url);
        $this->assertResponseNotFound();
    }

    // Тест для show всех курсов
    public function testShow(): void
    {
        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertNotEmpty($courses);
        foreach ($courses as $course) {
            $crawler = self::getClient()->request('GET', $this->getPath() . '/' . $course->getId());
            $this->assertResponseOk();

            // получаем кол-во уроков со страницы курса
            $lessonsCount = $crawler->filter('ol > li')->count();
            // получаем число уроков для курса из бд
            $lessonsCountFromBD = count($course->getLessons());
            // проверяем соответствие
            static::assertEquals($lessonsCountFromBD, $lessonsCount);
        }
    }

    // Тест для проверки добавления новых параметров курса
    public function testValidValueCourse(): void
    {
        //Тестирование добавления нового курса
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // Переход к new курса
        $link = $crawler->filter('a.course__new')->link();
        $client->click($link);
        $this->assertResponseOk();
        // заполним поля для добавления курса
        $client->submitForm('course__add', [
            'course[code]' => '1234',
            'course[name]' => 'Курс',
            'course[description]' => 'Этот курс нужен для...',
        ]);
        // Проверка редиректа на главную страницу всех курсов
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        // Количество курсов на странице
        $coursesCount = $crawler->filter('div.card')->count();
        // Сравнение количества курсов на странице с количеством курсов в бд
        self::assertEquals(4, $coursesCount);

        //Тестирование удаления курса
        // Переход на страницу курса
        $link = $crawler->filter('a.card-link')->last()->link();
        $client->click($link);
        $this->assertResponseOk();
        // Удаление курса
        $client->submitForm('course__delete');
        // Проверка редиректа после удаления курса
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/'));
        // Переходим на главную страницу
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
        // Получим количество курсов на странице и сравним с реальным значением из бд
        $coursesCount = $crawler->filter('div.card')->count();
        self::assertEquals(3, $coursesCount);

        //Тестирование добавления невалидных полей
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // Перейдём к добавлению нового курса
        $link = $crawler->filter('a.course__new')->link();
        $client->click($link);
        $this->assertResponseOk();

        // Заполнение (значение поле код пустое)
        $crawler = $client->submitForm('course__add', [
            'course[code]' => '',
            'course[name]' => 'Курс',
            'course[description]' => 'Этот курс поможет вам.....',
        ]);
        // Проверка соответсвующего сообщения об ошибке
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Заполнение (значение поля код превышает 255 символов)
        $crawler = $client->submitForm('course__add', [
            'course[code]' => 'sadjskadkasaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooo
            llllllllllllllllasdiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsd',
            'course[name]' => 'Курс',
            'course[description]' => 'Этот курс',
        ]);
        // Убедимся что выдаётся верная ошибка
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());

        // Заполнение (поле описание превышает 1000 символов)
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // Заполенение
        $link = $crawler->filter('a.course__new')->link();
        $client->click($link);
        $this->assertResponseOk();

        $crawler = $client->submitForm('course__add', [
            'course[code]' => 'Code123',
            'course[name]' => 'Новый курс',
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

        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());
    }

    // Редактирование курса
    public function testCourseEdit(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPath() . '/');
        $this->assertResponseOk();
        // Перейдем к редактированию, первого курса на странице
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // Нажимаем кнопку редактирования
        $link = $crawler->filter('a.course__edit')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();
        // Изменим значения полей формы
        $form = $crawler->selectButton('course__add')->form();
        // id кода из формы
        $em = self::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['code' => $form['course[code]']->getValue()]);

        $form['course[code]'] = 'Код123';
        $form['course[name]'] = 'Курс1234';
        $form['course[description]'] = 'Описание курса 1234';
        // Отправляем форму
        $client->submit($form);
        // Проверяем редирект на изменённый курс
        self::assertTrue($client->getResponse()->isRedirect($this->getPath() . '/' . $course->getId()));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }
}
