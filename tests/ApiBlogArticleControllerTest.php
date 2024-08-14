<?php

use App\Entity\BlogArticle;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiBlogArticleControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $jwtToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->jwtToken = $this->getJwtToken();
        $this->authenticate();
    }

    private function getJwtToken(): string
    {
        $host = $_SERVER['APP_HOST'];
        $url = $host . '/api/login_check';
        $data = [
            'username' => 'dev',
            'password' => 'dev'
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = json_decode(curl_exec($ch));
        curl_close($ch);
        if (isset($response->token)) {
            return $response->token;
        }
        throw new \Exception('Unable to get JWT token. Response: ' . json_encode($response));

    }

    private function authenticate(): void
    {
        $this->client->setServerParameters(['HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtToken]);
    }

    public function testIndex()
    {
        $this->client->request('GET', '/api/blog-articles');
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
    }

    public function testCreate()
    {
        $coverPicture = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__.'/../src/fixtures/cover.png',
            'cover.png',
            'image/png',
            null,
            true
        );
        $data = [
            'authorId' => 1,
            'title' => 'New Article',
            'content' => 'Content of the article.',
            'publicationDate' => '2024-08-13 10:00:00',
            'keywords' => ['keyword1', 'keyword2'],
            'status' => 'draft',
            'slug' => 'new-article',
        ];
        $this->client->request('POST', '/api/blog-articles', $data, ['coverPictureRef' => $coverPicture], ['CONTENT_TYPE' => 'multipart/form-data']);
        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $createdArticle = $this->entityManager->getRepository(BlogArticle::class)->findOneBy(['slug' => 'new-article']);
        $this->assertNotNull($createdArticle);
    }

    public function testCreateInvalidData()
    {
         $data = ['title' => ''];

         $this->client->request('POST', '/api/blog-articles', [], [], [], json_encode($data));
         $response = $this->client->getResponse();

         $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testShow()
     {
         $article = new BlogArticle();
         $article->setAuthorId(1);
         $article->setTitle('Test Article');
         $article->setContent('Content');
         $article->setPublicationDate(new \DateTime());
         $article->setCreationDate(new \DateTime());
         $article->setKeywords(['keywords']);
         $article->setStatus('published');
         $article->setSlug('test-article');
         $article->setCoverPictureRef('cover.jpg');

         $this->entityManager->persist($article);
         $this->entityManager->flush();

         $this->client->request('GET', '/api/blog-articles/'.$article->getId());
         $response = $this->client->getResponse();

         $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
         $data = json_decode($response->getContent(), true);
         $this->assertEquals($article->getId(), $data['id']);
     }

    public function testUpdate(){

       $article = new BlogArticle();
       $article->setAuthorId(1);
       $article->setTitle('Old Title');
       $article->setContent('Content');
       $article->setPublicationDate(new \DateTime());
       $article->setCreationDate(new \DateTime());
       $article->setKeywords(['keyword1', 'keyword2']);
       $article->setStatus('published');
       $article->setSlug('update-article');
       $article->setCoverPictureRef('');

       $this->entityManager->persist($article);
       $this->entityManager->flush();

       $data = ['title' => 'Updated Title'];
       $this->client->request('PATCH', '/api/blog-articles/'.$article->getId(), $data, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
       $response = $this->client->getResponse();

       $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
       $updatedArticle = $this->entityManager->getRepository(BlogArticle::class)->find($article->getId());
       $this->assertEquals('Updated Title', $updatedArticle->getTitle());
    }

    public function testDelete()
      {
          $article = new BlogArticle();
          $article->setAuthorId(1);
          $article->setTitle('To Delete');
          $article->setContent('Content');
          $article->setPublicationDate(new \DateTime());
          $article->setCreationDate(new \DateTime());
          $article->setKeywords(['keywords']);
          $article->setStatus('published');
          $article->setSlug('delete-article');
          $article->setCoverPictureRef('');

          $this->entityManager->persist($article);
          $this->entityManager->flush();

          $this->client->request('DELETE', '/api/blog-articles/'.$article->getId());
          $response = $this->client->getResponse();

          $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
          $deletedArticle = $this->entityManager->getRepository(BlogArticle::class)->find($article->getId());
          $this->assertEquals('deleted', $deletedArticle->getStatus());
      }
}
