<?php

namespace samuelreichoer\queryapi\controllers;

use Craft;
use craft\web\Controller;
use Exception;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\models\QueryApiSchema;
use samuelreichoer\queryapi\QueryApi;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SchemaController extends Controller
{
    /**
     * @param int|null $schemaId
     * @param QueryApiSchema|null $schema
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     */
    public function actionEditSchema(?int $schemaId = null, ?QueryApiSchema $schema = null): Response
    {
        $this->requirePermission(Constants::EDIT_SCHEMAS);

        if ($schema || $schemaId) {
            if (!$schema) {
                $schema = QueryApi::getInstance()->schema->getSchemaById($schemaId);
            }

            if (!$schema) {
                throw new NotFoundHttpException('Schema not found');
            }

            $title = trim($schema->name) ?: Craft::t('app', 'Edit Query API Schema');
            $usage = QueryApi::getInstance()->token->getSchemaUsageInTokens($schema->id);
        } else {
            $schema = new QueryApiSchema();
            $title = trim($schema->name) ?: Craft::t('app', 'Create a new Query API Schema');
            $usage = [];
        }

        return $this->renderTemplate('query-api/schemas/_edit.twig', compact(
            'schema',
            'title',
            'usage',
        ));
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function actionSaveSchema(): ?Response
    {
        $this->requirePermission(Constants::EDIT_SCHEMAS);
        $this->requirePostRequest();
        $this->requireElevatedSession();

        $schemaService = QueryApi::getInstance()->schema;
        $schemaId = $this->request->getBodyParam('schemaId');

        if ($schemaId) {
            $schema = $schemaService->getSchemaById($schemaId);

            if (!$schema) {
                throw new NotFoundHttpException('Schema not found');
            }
        } else {
            $schema = new QueryApiSchema();
        }

        $schema->name = $this->request->getBodyParam('name') ?? $schema->name;
        $schema->scope = $this->request->getBodyParam('permissions') ?? [];

        if (!$schemaService->saveSchema($schema)) {
            $this->setFailFlash(Craft::t('app', 'Couldn’t save schema.'));

            // Send the schema back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'schema' => $schema,
            ]);

            return null;
        }

        $this->setSuccessFlash(Craft::t('app', 'Schema saved.'));
        return $this->redirectToPostedUrl($schema);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     * @throws ForbiddenHttpException
     */
    public function actionDeleteSchema(): Response
    {
        $this->requirePermission(Constants::EDIT_SCHEMAS);
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $schemaId = $this->request->getRequiredBodyParam('id');

        QueryApi::getInstance()->schema->deleteSchemaById($schemaId);

        return $this->asSuccess();
    }
}
