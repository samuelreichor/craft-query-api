<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\Component;
use craft\db\Query as DbQuery;
use craft\helpers\UrlHelper;
use InvalidArgumentException;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\models\QueryApiToken;
use samuelreichoer\queryapi\records\TokenRecord;
use yii\db\Exception;

class TokenService extends Component
{
    /**
     * Saves a Query API token.
     *
     * @param QueryApiToken $token the schema to save
     * @param bool $runValidation Whether the schema should be validated
     * @return bool Whether the schema was saved successfully
     * @throws Exception
     * @throws Exception
     */
    public function saveToken(QueryApiToken $token, bool $runValidation = true): bool
    {
        $isNewToken = !$token->id;

        if ($runValidation && !$token->validate()) {
            Craft::info('Token not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewToken) {
            $tokenRecord = new TokenRecord();
        } else {
            $tokenRecord = TokenRecord::findOne($token->id) ?: new TokenRecord();
        }

        $tokenRecord->name = $token->name;
        $tokenRecord->enabled = $token->enabled;
        $tokenRecord->schemaId = $token->schemaId;

        if ($token->accessToken) {
            $tokenRecord->accessToken = $token->accessToken;
        }

        $tokenRecord->save();
        $token->id = $tokenRecord->id;
        $token->uid = $tokenRecord->uid;

        return true;
    }

    /**
     * Deletes a Query API token by its ID.
     *
     * @param int $id The token ID
     * @return bool Whether the token is deleted.
     */
    public function deleteTokenById(int $id): bool
    {
        $record = TokenRecord::findOne($id);

        if (!$record) {
            return true;
        }

        return $record->delete();
    }

    /**
     * Returns a Query API token by its ID.
     *
     * @param int $id
     * @return QueryApiToken|null
     */
    public function getTokenById(int $id): ?QueryApiToken
    {
        $result = $this->_createTokenQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new QueryApiToken($result) : null;
    }

    /**
     * Returns a Query API token by its name.
     *
     * @param string $tokenName
     * @return QueryApiToken|null
     */
    public function getTokenByName(string $tokenName): ?QueryApiToken
    {
        $result = $this->_createTokenQuery()
            ->where(['name' => $tokenName])
            ->one();

        return $result ? new QueryApiToken($result) : null;
    }

    /**
     * Returns a Query API token by its UID.
     *
     * @param string $uid
     * @return QueryApiToken
     * @throws InvalidArgumentException if $uid is invalid
     */
    public function getTokenByUid(string $uid): QueryApiToken
    {
        $result = $this->_createTokenQuery()
            ->where(['uid' => $uid])
            ->one();

        if (!$result) {
            throw new InvalidArgumentException('Invalid UID');
        }

        return new QueryApiToken($result);
    }

    /**
     * Returns a Query API token by its access token.
     *
     * @param string $token
     * @return QueryApiToken
     * @throws InvalidArgumentException if $token is invalid
     */
    public function getTokenByAccessToken(string $token): QueryApiToken
    {
        $result = $this->_createTokenQuery()
            ->where(['accessToken' => $token])
            ->one();

        if (!$result) {
            throw new InvalidArgumentException('Invalid access token');
        }

        return new QueryApiToken($result);
    }

    public function getTokens(): array
    {
        $rows = $this->_createTokenQuery()
            ->all();

        $tokens = [];

        foreach ($rows as $row) {
            $tokens[] = new QueryApiToken($row);
        }

        return $tokens;
    }

    public function getSchemaUsageInTokens(int $schemaId): array
    {
        $rows = $this->_createTokenQuery()
            ->where(['schemaId' => $schemaId])
            ->all();

        $usage = [];
        foreach ($rows as $row) {
            $token = new QueryApiToken($row);
            $usage[] = [
                'name' => $token->name,
                'url' => UrlHelper::url('query-api/tokens/' . $token->id),
            ];
        }

        return $usage;
    }

    public function getSchemaUsageInTokensAmount(int $schemaId): string
    {
        $amount = $this->_createTokenQuery()
            ->where(['schemaId' => $schemaId])
            ->count();

        return $amount . " Tokens";
    }

    /**
     * @throws \yii\base\Exception
     */
    public function generateToken()
    {
        return Craft::$app->getSecurity()->generateRandomString(32);
    }

    /**
     * Returns a DbCommand object prepped for retrieving tokens.
     *
     * @return DbQuery
     */
    private function _createTokenQuery(): DbQuery
    {
        return (new DbQuery())
            ->select([
                'id',
                'schemaId',
                'name',
                'accessToken',
                'enabled',
                'dateCreated',
                'dateUpdated',
                'uid',
            ])
            ->from([Constants::TABLE_TOKENS]);
    }
}
