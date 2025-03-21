<?php

namespace samuelreichoer\queryapi\controllers;

use Craft;
use craft\web\Controller;
use Exception;
use InvalidArgumentException;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\models\QueryApiSchema;
use samuelreichoer\queryapi\models\QueryApiToken;
use samuelreichoer\queryapi\QueryApi;
use samuelreichoer\queryapi\services\TokenService;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class TokenController extends Controller
{
    /**
     * @param int|null $tokenId
     * @param QueryApiToken|null $token
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \yii\base\Exception
     * @throws Exception
     */
    public function actionEditToken(?int $tokenId = null, ?QueryApiToken $token = null): Response
    {
        $this->requirePermission(Constants::EDIT_TOKENS);

        $tokenService = QueryApi::getInstance()->token;
        $accessToken = null;

        if ($token || $tokenId) {
            if (!$token) {
                $token = $tokenService->getTokenById($tokenId);
            }

            if (!$token) {
                throw new NotFoundHttpException('Token not found');
            }

            $title = trim($token->name) ?: Craft::t('app', 'Edit Query API Token');
        } else {
            $token = new QueryApiToken();
            $accessToken = $this->_generateToken();
            $title = trim($token->name) ?: Craft::t('app', 'Create a new Query API token');
        }

        $schemas = QueryApi::getInstance()->schema->getSchemas();
        $schemaOptions = [];

        foreach ($schemas as $schema) {
            $schemaOptions[] = [
                'label' => $schema->name,
                'value' => $schema->id,
            ];
        }

        if ($token->id && !$token->schemaId && !empty($schemaOptions)) {
            // Add a blank option to the top so it's clear no schema is currently selected
            array_unshift($schemaOptions, [
                'label' => '',
                'value' => '',
            ]);
        }

        return $this->renderTemplate('query-api/tokens/_edit.twig', compact(
            'token',
            'title',
            'accessToken',
            'schemaOptions'
        ));
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionSaveToken(): ?Response
    {
        $this->requirePermission(Constants::EDIT_TOKENS);
        $this->requirePostRequest();
        $this->requireElevatedSession();

        $tokenService = QueryApi::getInstance()->token;
        $tokenId = $this->request->getBodyParam('tokenId');

        if ($tokenId) {
            $token = $tokenService->getTokenById($tokenId);

            if (!$token) {
                throw new NotFoundHttpException('Token not found');
            }
        } else {
            $token = new QueryApiToken();
        }

        $token->name = $this->request->getBodyParam('name') ?? $token->name;
        $token->accessToken = $this->request->getBodyParam('accessToken') ?? $token->accessToken;
        $token->enabled = (bool)$this->request->getRequiredBodyParam('enabled');
        $token->schemaId = $this->request->getBodyParam('schema');

        if (!$tokenService->saveToken($token)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save token.'));

            // Send the schema back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'token' => $token,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('app', 'Token saved.'));
        return $this->redirectToPostedUrl($token);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionDeleteToken(): Response
    {
        $this->requirePermission(Constants::EDIT_TOKENS);
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $tokenId = $this->request->getRequiredBodyParam('id');

        QueryApi::getInstance()->token->deleteTokenById($tokenId);

        return $this->asSuccess();
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     */
    public function actionFetchToken(): Response
    {
        $this->requirePermission(Constants::EDIT_TOKENS);
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireElevatedSession();

        $tokenUid = $this->request->getRequiredBodyParam('tokenUid');

        try {
            $token = QueryApi::getInstance()->token->getTokenByUid($tokenUid);
        } catch (InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid token UID.');
        }

        return $this->asJson([
            'accessToken' => $token->accessToken,
        ]);
    }

    /**
     * @return Response
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     * @throws Exception
     */
    public function actionGenerateToken(): Response
    {
        $this->requirePermission(Constants::EDIT_TOKENS);
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        return $this->asJson([
            'accessToken' => $this->_generateToken(),
        ]);
    }

    /**
     * @return string
     * @throws Exception
     */
    private function _generateToken(): string
    {
        return Craft::$app->getSecurity()->generateRandomString(32);
    }
}
