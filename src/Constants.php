<?php

namespace samuelreichoer\queryapi;

class Constants
{
    // Tables
    public const TABLE_SCHEMAS = '{{%query_api_schemas}}';
    public const TABLE_TOKENS = '{{%query_api_tokens}}';

    // Project Config
    public const PATH_SCHEMAS = 'query-api.schemas';

    // Permissions
    public const EDIT_SCHEMAS = 'query-api-schemas:edit';
    public const EDIT_TOKENS = 'query-api-tokens:edit';

    // Cache Identifier
    public const CACHE_TAG_GlOBAL = 'queryapi:global';

    // Base Transformer Settings
    public const EXCLUDED_FIELD_HANDLES = ['nystudio107\seomatic\fields\SeoSettings'];
}
