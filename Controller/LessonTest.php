<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Tests\AbstractTest;
use App\Tests\Auth\Auth;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;

class LessonTest extends AbstractTest
{
    private string $coursesIndexPath = '/courses/';
    private string $lessonsIndexPath = '/lessons/';
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    protected function getFixtures(): array
    {
        return [
            AppFixtures::class
        ];
    }

    public function testLessonPagesUnauthorizedAccess(): void
    {
        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            foreach ($course->getLessons() as $lesson) {
                $client->request('GET', $this->lessonsIndexPath . $lesson->getId());
                $this->assertResponseRedirect();
                $crawler = $client->followRedirect();
                $this->assertEquals('/login', $client->getRequest()->getPathInfo());

                $client->request('GET', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                $this->assertResponseRedirect();
                $crawler = $client->followRedirect();
                $this->assertEquals('/login', $client->getRequest()->getPathInfo());

                $client->request('POST', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                $this->assertResponseRedirect();
                $crawler = $client->followRedirect();
                $this->assertEquals('/login', $client->getRequest()->getPathInfo());
            }
        }
    }

    public function testLessonPagesUserAccess(): void
    {
        $crawler = $this->userAuth();

        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            foreach ($course->getLessons() as $lesson) {
                $client->request('GET', $this->lessonsIndexPath . $lesson->getId());
                $this->assertResponseOk();

                $client->request('GET', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                self::assertResponseStatusCodeSame(403);

                $client->request('POST', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                self::assertResponseStatusCodeSame(403);
            }
        }
    }

    public function testLessonPagesResponseIsSuccessful(): void
    {
        $crawler = $this->adminAuth();

        $client = self::getClient();

        $courseRepository = self::getEntityManager()->getRepository(Course::class);
        $courses = $courseRepository->findAll();

        foreach ($courses as $course) {
            foreach ($course->getLessons() as $lesson) {
                $client->request('GET', $this->lessonsIndexPath . $lesson->getId());
                $this->assertResponseOk();

                $client->request('GET', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                $this->assertResponseOk();

                $client->request('POST', $this->lessonsIndexPath . $lesson->getId() . '/edit');
                $this->assertResponseOk();
            }
        }
    }

    public function testValidDataLessonAdd(): void
    {
        $crawler = $this->adminAuth();

        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-secondary')->last()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('??????????????????');

        $form = $submitButton->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '?????????????? ??????????',
            'lesson[number]' => 1000,
        ]);

        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $client->submit($form);
        self::assertTrue($client->getResponse()->isRedirect($this->coursesIndexPath . $course->getId()));
        $crawler = $client->followRedirect();

        $lessonLink = $crawler->filter('.list-group-item > a')->last()->link();
        $client->click($lessonLink);
        $this->assertResponseOk();
    }

    public function testInvalidDataLessonAdd(): void
    {
        $crawler = $this->adminAuth();

        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-secondary')->last()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitForm = $crawler->selectButton('??????????????????');
        $form = $submitForm->form([
            'lesson[name]' => 'qwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiopqwertyuiopq
            wertyuiopqwertyuiopqwertyuiopqwertyuiop',
            'lesson[content]' => '??????????????',
            'lesson[number]' => 1000,
        ]);

        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message')->first();
        self::assertEquals('?????????????????? ???????????????????????? ?????????? ????????????????', $error->text());

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '??????????????',
            'lesson[number]' => 1000000,
        ]);

        $client->submit($form);
        $crawler = $client->submit($form);
        $error = $crawler->filter('.form-error-message')->first();
        self::assertEquals('???????????????? ???????? ???????????? ???????? ?? ???????????????? ???? 1 ???? 10000', $error->text());
    }

    public function testBlankDataLessonAdd(): void
    {
        $crawler = $this->adminAuth();

        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-secondary')->last()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitForm = $crawler->selectButton('??????????????????');
        $form = $submitForm->form([
            'lesson[name]' => '',
            'lesson[content]' => '??????????????',
            'lesson[number]' => 1000,
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());

        $submitForm = $crawler->selectButton('??????????????????');
        $form = $submitForm->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '',
            'lesson[number]' => 1000,
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());

        $submitForm = $crawler->selectButton('??????????????????');
        $form = $submitForm->form([
            'lesson[name]' => '?????????? ????????',
            'lesson[content]' => '??????????????',
            'lesson[number]' => '',
        ]);

        $client->submit($form);
        self::assertFalse($client->getResponse()->isRedirect());
    }

    public function testLessonsDelete(): void
    {
        $crawler = $this->adminAuth();

        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $lessonsCount = $crawler->filter('.list-group-item > a')->count();

        $lessonLink = $crawler->filter('.list-group-item > a')->first()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $client->submitForm('lesson__delete');
        self::assertTrue($client->getResponse()->isRedirect());
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        self::assertCount($lessonsCount - 1, $crawler->filter('.list-group-item'));
    }

    public function testLessonsEdit(): void
    {
        $crawler = $this->adminAuth();

        $client = self::getClient();

        $crawler = $client->request('GET', $this->coursesIndexPath);
        $this->assertResponseOk();

        $link = $crawler->filter('.course-show')->first()->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $lessonLink = $crawler->filter('.list-group-item > a')->first()->link();
        $crawler = $client->click($lessonLink);
        $this->assertResponseOk();

        $link = $crawler->filter('.btn-outline-primary')->link();
        $crawler = $client->click($link);
        $this->assertResponseOk();

        $submitButton = $crawler->selectButton('??????????????????');
        $form = $submitButton->form();
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['id' => $form['lesson[course]']->getValue()]);

        $lesson = self::getEntityManager()
            ->getRepository(Lesson::class)
            ->findOneBy(['name' => $form['lesson[name]']->getValue()]);

        $form['lesson[name]'] = '???????????????????? ????????';
        $form['lesson[content]'] = '?????????????? ??????????';
        $form['lesson[number]'] = 100;
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect($this->lessonsIndexPath . $lesson->getId()));
        $crawler = $client->followRedirect();
        $this->assertResponseOk();

        $lessonName = $crawler->filter('h1')->text();
        self::assertEquals('???????????????????? ????????', $lessonName);

        $courseName = $crawler->filter('h3')->text();
        self::assertEquals('???????? ' . $course->getName(), $courseName);

        $courseDescription = $crawler->filter('h5')->text();
        self::assertEquals('?????????????? ??????????', $courseDescription);
    }

    private function adminAuth(): Crawler
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => 'admin@mail.ru',
            'password' => 'admin123'
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        return $auth->auth($requestData);
    }

    private function userAuth(): Crawler
    {
        $auth = new Auth();
        $auth->setSerializer($this->serializer);

        $data = [
            'username' => 'user@mail.ru',
            'password' => 'user123'
        ];

        $requestData = $this->serializer->serialize($data, 'json');

        return $auth->auth($requestData);
    }
}