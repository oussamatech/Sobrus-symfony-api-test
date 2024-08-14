<?php

namespace App\Controller;

use App\Entity\BlogArticle;
use App\Service\ContentValidator;
use App\Service\TextAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;

/**
 * Class ApiBlogArticleController
 *
 * @OA\Tag(name="Blog Article")
 * @Security(name="Bearer")
 */
class ApiBlogArticleController extends AbstractController
{

    use BasController;

    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private TextAnalyzer $textAnalyzer;
    private ContentValidator $contentValidator;

    public function __construct(TextAnalyzer $textAnalyzer, ContentValidator $contentValidator, EntityManagerInterface $entityManager, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->contentValidator = $contentValidator;
        $this->textAnalyzer = $textAnalyzer;
    }


    /**
     * @Route("/api/blog-articles", name="app_api_blog_article_index", methods={"GET"})
     * @OA\Get(
     *     path="/api/blog-articles",
     *     summary="Get all blog articles",
     *     @OA\Response(response=200, description="List of blog articles",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Items(
     *                 type="Schema",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="authorId", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="content", type="string"),
     *                 @OA\Property(property="publicationDate", type="string", format="date-time"),
     *                 @OA\Property(property="creationDate", type="string", format="date-time"),
     *                 @OA\Property(property="keywords", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="coverPictureRef", type="string")
     *             ),
     *             example={
     *                 {
     *                     "id": 5,
     *                     "authorId": 1,
     *                     "title": "Test Article",
     *                     "content": "This is the content of the test article.",
     *                     "publicationDate": "2024-08-13 10:00:00",
     *                     "creationDate": "2024-08-13 10:00:00",
     *                     "keywords": {"keyword1", "keyword2"},
     *                     "status": "published",
     *                     "slug": "test-article",
     *                     "coverPictureRef": "cover.jpg"
     *                 }
     *             }
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $articles = $this->entityManager->getRepository(BlogArticle::class)->findAll();
        $data = [];
        foreach ($articles as $article) {
            $data[] = [
                'id' => $article->getId(),
                'authorId' => $article->getAuthorId(),
                'title' => $article->getTitle(),
                'content' => $article->getContent(),
                'publicationDate' => $article->getPublicationDate()->format('Y-m-d H:i:s'),
                'creationDate' => $article->getCreationDate()->format('Y-m-d H:i:s'),
                'keywords' => $article->getKeywords(),
                'status' => $article->getStatus(),
                'slug' => $article->getSlug(),
                'coverPictureRef' => $article->getCoverPictureRef(),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/api/blog-articles", name="app_api_blog_article_create", methods={"POST"})
     * @OA\Post(
     *     path="/api/blog-articles",
     *     summary="Create a new blog article",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"authorId", "title", "content", "publicationDate", "keywords", "status", "slug"},
     *                 @OA\Property(property="authorId", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Test Article"),
     *                 @OA\Property(property="content", type="string", example="This is the content of the test article."),
     *                 @OA\Property(property="publicationDate", type="string", format="date-time", example="2024-08-13 10:00:00"),
     *                 @OA\Property(
     *                      property="keywords",
     *                      type="array",
     *                      @OA\Items(type="string"),
     *                      example={"keyword1", "keyword2"}
     *                  ),
     *                 @OA\Property(property="status", type="string", example="published"),
     *                 @OA\Property(property="slug", type="string", example="test-article"),
     *                 @OA\Property(property="coverPictureRef", type="string", format="binary", description="Cover picture file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Article created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="Article created")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input or missing required fields",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Missing required field: title")
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {

        $data = $request->request->all();
        $requiredFields = ['authorId', 'title', 'content', 'publicationDate', 'keywords', 'status', 'slug'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "Missing required field: $field"], Response::HTTP_BAD_REQUEST);
            }
        }

        $dateFields = ['publicationDate'];
        foreach ($dateFields as $dateField) {
            if (!$this->isValidDate($data[$dateField])) {
                return new JsonResponse(['error' => "Invalid date format for field: $dateField"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Handle the file upload
        $coverPictureFile = $request->files->get('coverPictureRef');
        if ($coverPictureFile instanceof UploadedFile) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $mimeType = $coverPictureFile->getMimeType();
            if (!in_array($mimeType, $allowedMimeTypes)) {
                return new JsonResponse(['error' => 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.'], Response::HTTP_BAD_REQUEST);
            }
            $filename = md5(uniqid('', true)) . '.' . $coverPictureFile->guessExtension();
            $destination = $this->getParameter('upload_directory') . '/' . $filename;
            try {
                if (!copy($coverPictureFile->getPathname(), $destination)) {
                    return new JsonResponse(['error' => 'Failed to copy file'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'Failed to upload file'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } else {
            $filename = null;
        }

        $keywords = (!is_array($data['keywords'])) ? explode(',', $data['keywords']) : $data['keywords'];

        $article = new BlogArticle();
        $article->setAuthorId($data['authorId']);
        $article->setTitle($data['title']);
        $article->setContent($data['content']);
        $article->setPublicationDate(new \DateTime($data['publicationDate']));
        $article->setCreationDate(new \DateTime());
        $article->setKeywords($keywords);
        $article->setStatus($data['status']);
        $article->setSlug($data['slug']);
        $article->setCoverPictureRef($filename);

        $errors = $this->validator->validate($article);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'Article created'], Response::HTTP_CREATED);
    }

    /**
     * @Route("/api/blog-articles/{id}", name="app_api_blog_article_show", methods={"GET"})
     * @OA\Get(
     *     path="/api/blog-articles/{id}",
     *     summary="Get a specific blog article by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The ID of the blog article"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog article details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=5),
     *             @OA\Property(property="title", type="string", example="Test Article"),
     *             @OA\Property(property="content", type="string", example="This is the content of the test article."),
     *             @OA\Property(property="publicationDate", type="string", format="date-time", example="2024-08-13 10:00:00"),
     *             @OA\Property(property="creationDate", type="string", format="date-time", example="2024-08-13 10:00:00"),
     *             @OA\Property(property="keywords", type="array", @OA\Items(type="string"), example={"keyword1", "keyword2"}),
     *             @OA\Property(property="status", type="string", example="published"),
     *             @OA\Property(property="slug", type="string", example="test-article"),
     *             @OA\Property(property="coverPictureRef", type="string", example="cover.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Article not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $article = $this->entityManager->getRepository(BlogArticle::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'content' => $article->getContent(),
            'publicationDate' => $article->getPublicationDate()->format('Y-m-d H:i:s'),
            'creationDate' => $article->getCreationDate()->format('Y-m-d H:i:s'),
            'keywords' => $article->getKeywords(),
            'status' => $article->getStatus(),
            'slug' => $article->getSlug(),
            'coverPictureRef' => $article->getCoverPictureRef(),
        ];

        return new JsonResponse($data);
    }

    /**
     * @Route("/api/blog-articles/{id}", name="app_api_blog_article_update", methods={"PATCH"})
     * @OA\Patch(
     *     path="/api/blog-articles/{id}",
     *     summary="Update a specific blog article by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The ID of the blog article"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="title", type="string", example="Updated Test Article"),
     *                 @OA\Property(property="content", type="string", example="Updated content of the test article."),
     *                 @OA\Property(property="publicationDate", type="string", format="date-time", example="2024-08-14 10:00:00"),
     *                 @OA\Property(property="creationDate", type="string", format="date-time", example="2024-08-14 10:00:00"),
     *                 @OA\Property(
     *                     property="keywords",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"keyword1", "keyword2"}
     *                 ),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(property="slug", type="string", example="updated-test-article")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Article updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="Article updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Article not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input or missing fields",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid input data")
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->request->all();

        $article = $this->entityManager->getRepository(BlogArticle::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }


        if (isset($data['title'])) $article->setTitle($data['title']);
        if (isset($data['content'])) $article->setContent($data['content']);
        if (isset($data['publicationDate'])) $article->setPublicationDate(new \DateTime($data['publicationDate']));
        if (isset($data['keywords'])) {
            $keywords = (!is_array($data['keywords'])) ? explode(',', $data['keywords']) : $data['keywords'];
            $article->setKeywords($keywords);
        }
        if (isset($data['status'])) $article->setStatus($data['status']);
        if (isset($data['slug'])) $article->setSlug($data['slug']);

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'Article updated']);
    }

    /**
     * @Route("/api/blog-articles/{id}", name="app_api_blog_article_delete", methods={"DELETE"})
     * @OA\Delete(
     *     path="/api/blog-articles/{id}",
     *     summary="Delete a specific blog article by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The ID of the blog article to delete"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Article successfully deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="Article deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Article not found")
     *         )
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $article = $this->entityManager->getRepository(BlogArticle::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        //No need to delete the file , we just need to change the article status
        $article->setStatus('deleted');
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'Article deleted']);
    }

    /**
     * @Route("/api/blog-articles/{id}/keywords", name="app_api_blog_article_update_keywords", methods={"PATCH"})
     * @OA\Patch(
     *     path="/api/blog-articles/{id}/keywords",
     *     summary="Update keywords for a specific blog article by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The ID of the blog article for which to update keywords"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="banned", type="array", @OA\Items(type="string"), example={"spam", "fake"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Keywords successfully updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="Keywords updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Article not found")
     *         )
     *     )
     * )
     */
    public function updateKeywords(Request $request, int $id): JsonResponse
    {
        $article = $this->entityManager->getRepository(BlogArticle::class)->find($id);
        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }
        $data = $request->request->all();

        $banned = (!is_array($data['banned'])) ? explode(',', $data['banned']) : $data['banned'];

        $keywords = $this->textAnalyzer->findTopWords($article->getContent(), $banned);

        $article->setKeywords($keywords);

        $this->entityManager->flush();

        return new JsonResponse(['status' => 'Keywords updated'], 200);
    }

    /**
     * @Route("/api/blog-articles/{id}/validate-content", name="app_api_blog_article_validate_content", methods={"POST"})
     * @OA\Post(
     *     path="/api/blog-articles/{id}/validate-content",
     *     summary="Validate the content of a specific blog article by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="The ID of the blog article to validate"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="banned", type="array", @OA\Items(type="string"), example={"spam", "offensive"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Content is valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="Content is valid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Content contains banned words",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Content contains banned words: spam, offensive")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Article not found")
     *         )
     *     )
     * )
     */
    public function validateContent(Request $request, int $id): JsonResponse
    {
        $article = $this->entityManager->getRepository(BlogArticle::class)->find($id);
        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $data = $request->request->all();

        $banned = (!is_array($data['banned'])) ? explode(',', $data['banned']) : $data['banned'];

        $invalidWords = $this->contentValidator->validateContent($article->getContent(), $banned);
        if (!empty($invalidWords)) {
            return new JsonResponse(['error' => 'Content contains banned words: ' . implode(', ', $invalidWords)], 400);
        }

        return new JsonResponse(['status' => 'Content is valid'], 200);
    }
}
