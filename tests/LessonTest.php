<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\CoursesFixtures;
use App\Entity\Course;
use App\Entity\Lesson;

class LessonControllerTest extends AbstractTest
{
    // Стартовые страницы
    private $startingPathCourse = '/courses';
    private $startingPathLesson = '/lessons';

    // Методы вызовов стартовых страниц
    public function getPathCourse(): string
    {
        return $this->startingPathCourse;
    }

    public function getPathLesson(): string
    {
        return $this->startingPathLesson;
    }

    // переопределим метод
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    // проверим для всех GET/POST экшенов контроллеров, что возвращается корректный http-статус
    public function testPageIsSuccessful(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Переходим на детализированную страницу курсов
        $courseLinks = $crawler->filter('a.card-link')->links();
        foreach ($courseLinks as $courseLink) {
            $crawler = $client->click($courseLink);
            $this->assertResponseOk();

            // Переходим на страницу уроков
            $lessonLinks = $crawler->filter('a.card-link')->links();
            foreach ($lessonLinks as $lessonLink) {
                $crawler = $client->click($lessonLink);
                self::assertResponseIsSuccessful();
            }
        }

        // запросим не существующую страницу
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathLesson() . '/14558');
        $this->assertResponseNotFound();
    }

    // Тест для проверки добавления новых параметров уроков
    public function testValidValueLesson(): void
    {
        // Стартовая точка
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому курсу
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдем к добавлению урока
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $form = $crawler->selectButton('lesson__add')->form();
        $form['lesson[name]'] = 'Урок 1';
        $form['lesson[content]'] = 'Контент урока';
        $form['lesson[number]'] = '1';

        $em = static::getEntityManager();
        $course = $em->getRepository(Course::class)->findOneBy(['id' => $form['lesson[course]']->getValue()]);
        self::assertNotEmpty($course);
        // Отправляем форму
        $client->submit($form);
        // Проверка редиректа
        self::assertTrue($client->getResponse()->isRedirect($this->getPathCourse() . '/' . $course->getId()));
        // Переходим на редирект
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        // Перейдём на show урока
        $link = $crawler->filter('ol > li > a')->first()->link();
        $client->click($link);
        $this->assertResponseOk();

        // Тест для проверки удаления урока
        $client->submitForm('lesson__delete');
        // Проверка редиректа
        self::assertTrue($client->getResponse()->isRedirect($this->getPathCourse() . '/' . $course->getId()));
        // Переходим на редирект
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому курсу
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        //Добавление урока (поле name пустое)
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => '',
            'lesson[content]' => 'Новый урок',
            'lesson[number]' => '13',
        ]);
        // Ошибка
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Заполнение (поле код больше 255 символов)
        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'sadjskadkasjdddddddasdkkkkkkkkk
            kkkkkkkasdkkkkkkkkkkkkkkkkkkasdllllllllllllllllllllllllll
            llllllllllllllllasdjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj
            jjjjasdllllllllllllllllllllllllllllsadkasdkasdknqowhduiqbwd
            noskznmdoasmpodpasmdpamsd',
            'lesson[content]' => 'Урок 123',
            'lesson[number]' => '5',
        ]);
        // Ошибка
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Превышена максимальная длина символов', $error->text());

        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому курсу
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Заполнение (поле content пустое)
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'Новый урок',
            'lesson[content]' => '',
            'lesson[number]' => '13',
        ]);
        // Ошибка
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

        // Стартовая точка на главной странице с курсами
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому курсу
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Добавление пустого поля number
        $link = $crawler->filter('a.lesson__new')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $crawler = $client->submitForm('lesson__add', [
            'lesson[name]' => 'Урок',
            'lesson[content]' => 'Материал',
            'lesson[number]' => '',
        ]);
        // Ошибка
        $error = $crawler->filter('span.form-error-message')->first();
        self::assertEquals('Поле не может быть пустым', $error->text());

    }

    // Тест для редактирования урока
    public function testLessonEdit(): void
    {
        // Стартовая точка
        $client = self::getClient();
        $crawler = $client->request('GET', $this->getPathCourse() . '/');
        $this->assertResponseOk();

        // Перейдём к первому курсу
        $link = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Перейдём к первому уроку
        $link = $crawler->filter('ol > li > a')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Редактировать урок
        $link = $crawler->filter('a.lesson__edit')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        // Заполнение полей
        $form = $crawler->selectButton('lesson__add')->form();
        // Получаем урок по номеру
        $em = self::getEntityManager();
        $lesson = $em->getRepository(Lesson::class)->findOneBy([
            'number' => $form['lesson[number]']->getValue(),
            'course' => $form['lesson[course]']->getValue(),
        ]);
        // Изменяем поля
        $form['lesson[name]'] = 'lesson';
        $form['lesson[content]'] = 'Content';
        // Отправляем форму
        $client->submit($form);
        // Проверка редиректа на страницу урока
        self::assertTrue($client->getResponse()->isRedirect($this->getPathLesson() . '/' . $lesson->getId()));
        // Переходим на страницу редиректа
        $crawler = $client->followRedirect();
        $this->assertResponseOk();
    }
}