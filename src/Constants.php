<?php

namespace samuelreichoer\queryapi;

class Constants
{
    // Tables
    public const TABLE_SCHEMAS = '{{%query_api_schemas}}';
    public const TABLE_TOKENS = '{{%query_api_tokens}}';

    // Project Config
    public const PATH_SCHEMAS = 'query-api.schemas';
    public const PATH_TOKENS = 'query-api.tokens';

    // Permissions
    public const EDIT_SCHEMAS = 'query-api-schemas:edit';
    public const EDIT_TOKENS = 'query-api-tokens:edit';
}
