<?php

namespace samuelreichoer\queryapi\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\App;
use craft\web\Controller;
use Exception;
use samuelreichoer\queryapi\helpers\Utils;
use samuelreichoer\queryapi\services\ElementQueryService;
use samuelreichoer\queryapi\services\JsonTransformerService;
use yii\web\Response;

class DefaultController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    /**
     * @throws Exception
     */
    public function actionGetCustomQueryResult(): Response
    {
        // Get request parameters
        $request = Craft::$app->getRequest();
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

        // Instantiate the Query Service and handle query execution
        $queryService = new ElementQueryService();
        $result = $queryService->executeQuery($elementType, $params);

        // Instantiate the Transform Service and handle transforming different elementTypes
        $transformerService = new JsonTransformerService();
        $transformedData = $transformerService->executeTransform($result, $predefinedFieldHandleArr);

        $queryOne = isset($params['one']) && $params['one'] === '1';
        if ($queryOne) {
            return $this->asJson($transformedData[0]);
        }
        return $this->asJson($transformedData);
    }

    /**
     * @throws Exception
     */
    public function actionGetAllRoutes($siteId = null): Response
    {
        $allSiteIds = Craft::$app->sites->getAllSiteIds();
        if ($siteId === null) {
            $siteIds = $allSiteIds;
        } else {
            // decode url needed when using decoded arrays
            $decodedSiteId = urldecode($siteId);

            // if it is a json array “[1,2]“
            $siteIds = json_decode($decodedSiteId, true);

            // If json decode is not working, it is a primitive type
            if (!is_array($siteIds)) {
                $siteIds = [$siteId];
            }

            // Check if Site Ids are valid
            foreach ($siteIds as $id) {
                if (!in_array($id, $allSiteIds)) {
                    throw new Exception('Invalid SiteId: ' . $id);
                }
            }
        }

        $craftDuration = Craft::$app->getConfig()->getGeneral()->cacheDuration;
        $duration = App::env('CRAFT_ENVIRONMENT') === 'dev' ? 0 : $craftDuration;
        $hashedParamsKey = Utils::generateCacheKey($siteIds);
        $cacheKey = 'queryapi_' . 'getAllRoutes' . '_' . $hashedParamsKey;

        if ($result = Craft::$app->getCache()->get($cacheKey)) {
            return $this->asJson($result);
        }

        Craft::$app->getElements()->startCollectingCacheInfo();

        $allSectionIds = Craft::$app->entries->getAllSectionIds();
        $allUrls = [];
        $allEntries = Entry::find()
            ->siteId($siteIds)
            ->status('live')
            ->sectionId($allSectionIds)
            ->all();

        foreach ($allEntries as $entry) {
            $allUrls[] = $entry->getUrl();
        }

        $cacheInfo = Craft::$app->getElements()->stopCollectingCacheInfo();

        Craft::$app->getCache()->set(
            $cacheKey,
            $allUrls,
            $duration,
            $cacheInfo[0]
        );

        return $this->asJson($allUrls);
    }
}
