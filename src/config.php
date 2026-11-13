<?php

/**
 * Craft Query API config.php
 *
 * Copy this file to `/config` as `query-api.php`
 * to override the default plugin settings.
 *
 * This file supports multienvironment configs just like `general.php`.
 */

return [
    '*' => [

        /**
         * ---------------------------------------------------------------------
         * Cache Duration
         * ---------------------------------------------------------------------
         *
         * Defines how long Query API responses should be cached (in seconds).
         * If set to `null`, the cache duration from Craft’s `general.php`
         * configuration will be used.
         */
        // 'cacheDuration' => 3600,


        /**
         * ---------------------------------------------------------------------
         * Excluded Field Classes
         * ---------------------------------------------------------------------
         *
         * An array of fully qualified PHP class names.
         * Any fields matching these classes will **not** be returned in the API.
         *
         * Example: Exclude SEOmatic fields or extremely large content fields.
         */
        // 'excludeFieldClasses' => [
        //     \nystudio107\seomatic\fields\SeoSettings::class,
        //     \verbb\supertable\fields\SuperTableField::class,
        // ],


        /**
         * ---------------------------------------------------------------------
         * Include Full Entries for Relations
         * ---------------------------------------------------------------------
         *
         * If `true`, entry relationships (from Entries fields)
         * will return the **entire transformed entry object**.
         *
         * If `false`, only a lightweight reference is returned:
         * id, slug, uri, title.
         */
        // 'includeFullEntry' => true,


        /**
         * ---------------------------------------------------------------------
         * TypeScript Type Generation Mode
         * ---------------------------------------------------------------------
         *
         * Determines how TypeScript definitions are generated:
         *
         * - 'Manual' → Run via CLI using `php craft query-api/typescript/generate`
         * - 'auto' → Automatically regenerate types whenever the project config is written
         *
         * Default: 'manual'
         */
        // 'typeGenerationMode' => 'manual', // or: 'auto'


        /**
         * ---------------------------------------------------------------------
         * Path for Generated TypeScript Definitions
         * ---------------------------------------------------------------------
         *
         * Supports aliases like `@root`, `@webroot`, or custom Craft aliases.
         */
        // 'typeGenerationOutputPath' => '@root/queryApiTypes.generated.ts',


        /**
         * ---------------------------------------------------------------------
         * Hard Pick Mode
         * ---------------------------------------------------------------------
         *
         * If `true`, only values explicitly defined via fields() will be returned.
         *
         * If `false`, additional defaults such as sectionHandle and metadata
         * will be included automatically if present.
         *
         * ⚠️ NOTE: This will become the default in v4.
         */
        // 'hardPick' => false,


        /**
         * ---------------------------------------------------------------------
         * Asset Transforms (Custom Named SrcSet Definitions)
         * ---------------------------------------------------------------------
         *
         * Used when you want to use native asset transforms and want a srcSet
         * in the response.
         *
         * Available options:
         * - srcset: array of '100w', '200w', '1x', '2x', etc.
         * - generateOnSaveVolumes:
         *      - array of volume handles → generate only for these volumes
         *      - true → generate for all volumes
         *      - false → only generate on demand
         */
        /*'assetTransforms' => [
             'portrait' => [
                 'srcset' => ['100w', '200w', '400w'],
                 'generateOnSaveVolumes' => ['images', 'graphics'],
             ],
             'landscape' => [
                 'srcset' => ['1x', '2x'],
                 'generateOnSaveVolumes' => true,
             ],
         ],*/


        /**
         * ---------------------------------------------------------------------
         * Cache Invalidation Webhooks
         * ---------------------------------------------------------------------
         *
         * Endpoints that receive a POST request whenever Query API caches get
         * invalidated (element saved/deleted, caches cleared, schema changed).
         * Changes are batched per request and sent via the queue.
         *
         * Payload: {"event":"invalidate","all":false,"tags":["element::123", ...],"timestamp":1700000000}
         * If a secret is set, the request contains an `X-Query-Api-Signature`
         * header: `sha256=` + HMAC-SHA256 of the raw body.
         *
         * For entries going live (postDate) or expiring (expiryDate), run
         * `php craft query-api/cache/invalidate-scheduled` via cron (e.g. every minute).
         */
        // 'webhooks' => [
        //     [
        //         'url' => '$FRONTEND_REVALIDATE_URL',
        //         'secret' => '$FRONTEND_REVALIDATE_SECRET',
        //     ],
        // ],


        /**
         * ---------------------------------------------------------------------
         * Cache Tags Header
         * ---------------------------------------------------------------------
         *
         * If set, every API response contains the cache tags of the response
         * in this header (space separated). Tag your frontend cache entries
         * with them, to invalidate exactly the tags sent by the webhook.
         *
         */
        // 'cacheTagsHeader' => 'X-Query-Api-Cache-Tags',
    ],
];
