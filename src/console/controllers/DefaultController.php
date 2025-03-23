<?php

namespace samuelreichoer\queryapi\console\controllers;

use craft\console\Controller;
use Exception;
use samuelreichoer\queryapi\models\QueryApiSchema;
use samuelreichoer\queryapi\models\QueryApiToken;
use samuelreichoer\queryapi\QueryApi;
use yii\console\ExitCode;

class DefaultController extends Controller
{
    /**
     * @throws Exception
     */
    public function actionCreatePublicSchema(): int
    {
        $this->stdout("Creating public schema...\n");
        $schema = new QueryApiSchema();
        $schema->name = 'Public Schema';
        $schema->scope = [
            "sites.*:read",
            "sections.*:read",
            "usergroups.*:read",
            "volumes.*:read",
            "addresses.*:read",
        ];

        if (!QueryApi::getInstance()->schema->saveSchema($schema)) {
            $this->stderr("Failed to save schema. Validation errors:\n");

            foreach ($schema->getErrors() as $attribute => $errors) {
                foreach ($errors as $error) {
                    $this->stderr("  - [$attribute] $error\n");
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // Invalidate query API caches
        QueryApi::getInstance()->cache->invalidateCaches();

        $this->stdout("Successfully created public schema.\n");
        return ExitCode::OK;
    }

    /**
     * @throws Exception
     */
    public function actionCreatePublicToken(): int
    {
        $this->actionCreatePublicSchema();
        $schema = QueryApi::getInstance()->schema->getSchemaByName('Public Schema');

        $tokenService = QueryApi::getInstance()->token;

        $token = new QueryApiToken();
        $token->name = 'Public Token';
        $token->accessToken = $tokenService->generateToken();
        $token->enabled = true;
        $token->schemaId = $schema->id;

        if (!$tokenService->saveToken($token)) {
            $this->stderr("Failed to save token. Validation errors:\n");

            foreach ($schema->getErrors() as $attribute => $errors) {
                foreach ($errors as $error) {
                    $this->stderr("  - [$attribute] $error\n");
                }
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Successfully created public token.\n");
        $this->stdout("Your Token is: {$token->accessToken}\n");

        return ExitCode::OK;
    }

    public function actionClearCaches(): int
    {
        QueryApi::getInstance()->cache->invalidateCaches();
        return ExitCode::OK;
    }
}
