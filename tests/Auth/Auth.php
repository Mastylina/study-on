<?php


namespace App\Tests\Auth;

use App\Model\UserDto;
use App\Service\BillingClient;
use App\Tests\AbstractTest;
use App\Tests\Mock\BillingClientMock;
use App\Service\DecodingJwt;
use App\Dto\Response\UserRegisterDto;
use JMS\Serializer\SerializerInterface;

use Symfony\Component\HttpFoundation\Response;

class Auth extends AbstractTest
{
    private $serializer;

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function auth(string $data)
    {
        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($data, UserDto::class, 'json');

        $this->getBillingClient();
        $client = self::getClient();
        $crawler = $client->request('GET', '/login');
        $this->assertResponseOk();

        $form = $crawler->selectButton('Вход')->form();
        $form['email'] = $userDto->getUsername();
        $form['password'] = $userDto->getPassword();
        $client->submit($form);

        $error = $crawler->filter('#errors');
        self::assertCount(0, $error);

        $crawler = $client->followRedirect();

        $this->assertResponseOk();

        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
        return $crawler;


    }

    // Метод для замены сервиса билинга на Mock версию для тестов
    public function getBillingClient(): void
    {
        self::getClient()->disableReboot();

        self::getClient()->getContainer()->set(
            BillingClient::class,
            new BillingClientMock($this->serializer)
        );
    }
}