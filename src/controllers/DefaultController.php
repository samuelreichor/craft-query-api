<?php

namespace samuelreichoer\queryapi\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use Exception;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\helpers\Permissions;
use samuelreichoer\queryapi\helpers\Utils;
use samuelreichoer\queryapi\models\QueryApiSchema;
use samuelreichoer\queryapi\QueryApi;
use samuelreichoer\queryapi\services\ElementQueryService;
use samuelreichoer\queryapi\services\JsonTransformerService;
use yii\caching\TagDependency;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class DefaultController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    /**
     * @throws Exception
     */
    public function actionGetCustomQueryResult(): Response
    {
        $this->_setCorsHeaders();
        $request = $this->request;

        if ($request->getIsOptions()) {
            // This is just a preflight request, no need to run the actual query yet
            $this->response->format = Response::FORMAT_RAW;
            $this->response->data = '';
            return $this->response;
        }

        $schema = $this->_getActiveSchema();
        Permissions::canQuerySites($schema);


        $params = $request->getQueryParams();

        // Early return when no params are available. Min is one()/all()
        if (count($params) < 1) {
            return $this->asJson([]);
        }

        // Get the elementType of the query
        $elementType = 'entries';
        if (isset($params['elementType']) && $params['elementType']) {
            $elementType = $params['elementType'];
            unset($params['elementType']);
        }

        // Transform string of field handles to array
        $predefinedFieldHandleArr = [];
        if (isset($params['fields']) && $params['fields']) {
            $predefinedFieldHandleArr = explode(',', $params['fields']);
            unset($params['fields']);
        }

        // Transform all other comma seperated strings to array
        foreach ($params as $key => $value) {
            if (is_string($value) && str_contains($value, ',')) {
                $params[$key] = explode(',', $value);
            }
        }

        // Return cached query data
        $cacheKey = Constants::CACHE_TAG_GlOBAL . $elementType . '_' . Utils::generateCacheKey([
                'schema' => $schema->uid,
                'params' => $params,
            ]);

        if ($result = Craft::$app->getCache()->get($cacheKey)) {
            return $result;
        }

        // Set cache duration of config and fallback to general craft cache duration
        $duration = QueryApi::getInstance()->cache->getCacheDuration();

        Craft::$app->getElements()->startCollectingCacheInfo();

        // Instantiate the Query Service and handle query execution
        $queryService = new ElementQueryService($schema);
        $result = $queryService->executeQuery($elementType, $params);

        // Instantiate the Transform Service and handle transforming different elementTypes
        $transformerService = new JsonTransformerService($queryService);
        $transformedData = $transformerService->executeTransform($result, $predefinedFieldHandleArr);

        $queryOne = isset($params['one']) && $params['one'] === '1';
        $finalResult = $this->asJson($queryOne ? ($transformedData[0] ?? null) : $transformedData);

        [$craftDependency] = Craft::$app->getElements()->stopCollectingCacheInfo();

        $tags = $craftDependency instanceof TagDependency ? $craftDependency->tags : [];
        $tags[] = Constants::CACHE_TAG_GlOBAL;
        $combinedDependency = new TagDependency(['tags' => $tags]);

        Craft::$app->getCache()->set(
            $cacheKey,
            $finalResult,
            $duration,
            $combinedDependency
        );

        return $finalResult;
    }

    /**
     * @throws Exception
     */
    public function actionGetAllRoutes($siteId = null): Response
    {
        $this->_setCorsHeaders();
        $request = $this->request;

        if ($request->getIsOptions()) {
            // This is just a preflight request, no need to run the actual query yet
            $this->response->format = Response::FORMAT_RAW;
            $this->response->data = '';
            return $this->response;
        }

        $schema = $this->_getActiveSchema();
        Permissions::canQuerySites($schema);

        $siteIds = $this->_getValidSiteIds($siteId);

        $cacheKey = Constants::CACHE_TAG_GlOBAL . 'get-all-routes_' . Utils::generateCacheKey([
            'schema' => $schema->uid,
            'siteIds' => $siteIds,
        ]);

        if ($result = Craft::$app->getCache()->get($cacheKey)) {
            return $result;
        }

        if (!Permissions::canQueryAllSites($schema)) {
            foreach ($siteIds as $siteId) {
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if (!$schema->has("sites.{$site->uid}:read")) {
                    throw new ForbiddenHttpException("Schema doesn't have access to site with handle: {$site->handle}");
                }
            }
        }

        $allSectionIds = Craft::$app->entries->getAllSectionIds();
        if (!Permissions::canQueryAllEntries($schema)) {
            foreach ($allSectionIds as $sectionId) {
                $section = Craft::$app->getEntries()->getSectionById($sectionId);
                if (!$schema->has("sections.{$section->uid}:read")) {
                    throw new ForbiddenHttpException("Schema doesn't have access to section with handle: {$section->handle}");
                }
            }
        }

        $duration = QueryApi::getInstance()->cache->getCacheDuration();
        Craft::$app->getElements()->startCollectingCacheInfo();

        $allUrls = [];
        $allEntries = Entry::find()
            ->siteId($siteIds)
            ->status('live')
            ->sectionId($allSectionIds)
            ->all();

        foreach ($allEntries as $entry) {
            $allUrls[] = $entry->getUrl();
        }

        $finalResult = $this->asJson($allUrls);

        [$craftDependency] = Craft::$app->getElements()->stopCollectingCacheInfo();
        $tags = $craftDependency instanceof TagDependency ? $craftDependency->tags : [];
        $tags[] = Constants::CACHE_TAG_GlOBAL;
        $combinedDependency = new TagDependency(['tags' => $tags]);

        Craft::$app->getCache()->set(
            $cacheKey,
            $finalResult,
            $duration,
            $combinedDependency
        );

        return $finalResult;
    }

    private function _setCorsHeaders(): void
    {
        $headers = $this->response->getHeaders();
        $headers->setDefault('Access-Control-Allow-Credentials', 'true');
        $headers->setDefault('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Craft-Authorization, X-Craft-Token');

        $corsFilter = Craft::$app->getBehavior('corsFilter');
        $allowedOrigins = $corsFilter->cors['Origin'] ?? [];
        if (is_array($allowedOrigins)) {
            if (($origins = $this->request->getOrigin()) !== null) {
                $origins = ArrayHelper::filterEmptyStringsFromArray(array_map('trim', explode(',', $origins)));
                foreach ($origins as $origin) {
                    if (in_array($origin, $allowedOrigins)) {
                        $headers->setDefault('Access-Control-Allow-Origin', $origin);
                        break;
                    }
                }
            }
        } else {
            $headers->setDefault('Access-Control-Allow-Origin', '*');
        }
    }

    /**
     * @throws BadRequestHttpException
     */
    private function _getActiveSchema(): QueryApiSchema
    {
        $bearerToken = $this->request->getBearerToken();

        if (!$bearerToken) {
            throw new BadRequestHttpException('Missing Authorization header.');
        }

        $token = QueryApi::getInstance()->token->getTokenByAccessToken($bearerToken);

        if (!$token->getIsValid()) {
            throw new BadRequestHttpException('Invalid or inactive access token.');
        }

        return $token->getSchema();
    }

    /**
     * @throws BadRequestHttpException
     */
    private function _getValidSiteIds(?string $rawSiteIds)
    {
        $allSiteIds = Craft::$app->sites->getAllSiteIds();
        if ($rawSiteIds === null) {
            return $allSiteIds;
        }

        // decode url needed when using decoded arrays
        $decodedSiteId = urldecode($rawSiteIds);

        // if it is a json array “[1,2]“
        $siteIds = json_decode($decodedSiteId, true);

        // If json decode is not working, it is a primitive type
        if (!is_array($siteIds)) {
            $siteIds = [$rawSiteIds];
        }

        // Check if Site Ids are valid
        foreach ($siteIds as $id) {
            if (!in_array($id, $allSiteIds)) {
                throw new BadRequestHttpException('Invalid SiteId: ' . $id);
            }
        }

        return $siteIds;
    }
}
