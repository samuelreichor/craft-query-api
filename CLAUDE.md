# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Query API is a Craft CMS 5 plugin that provides a REST API for querying Craft elements using URL parameters. It enables headless CMS setups by exposing entries, assets, users, addresses, categories, and tags through a configurable API with schema-based access control.

## Development Commands

```bash
# Code style checking (ECS with Craft CMS rules)
composer check-cs

# Fix code style issues automatically
composer fix-cs

# Static analysis (PHPStan level 5)
composer phpstan
```

## Architecture

### Request Flow

1. API requests hit `DefaultController::actionGetCustomQueryResult()`
2. Token authentication validates against `QueryApiSchema` permissions
3. `ElementQueryService` builds and executes Craft element queries with eager loading
4. `JsonTransformerService` routes elements to appropriate transformers
5. Results are cached using Craft's tag-based cache dependency system

### Core Services (registered in QueryApi::config())

- **ElementQueryService**: Builds element queries, handles custom element type registration via `EVENT_REGISTER_ELEMENT_TYPES`, manages eager loading
- **JsonTransformerService**: Dispatches elements to their transformers, supports custom transformers for custom element types
- **SchemaService**: Manages API schemas (project config synced)
- **TokenService**: Handles bearer token authentication
- **CacheService**: Cache invalidation and duration management
- **TypescriptService**: Generates TypeScript definitions from Craft schema

### Transformer System

All transformers extend `BaseTransformer` which provides:
- Field transformation with predefined field selection (dot-notation paths like `entries.title`)
- Nested field filtering with "Pick" mode vs "All + Overrides" mode
- Custom field transformer registration via `EVENT_REGISTER_FIELD_TRANSFORMERS`
- Automatic handling of Matrix, Content Blocks, and relation fields

Element-specific transformers: `EntryTransformer`, `AssetTransformer`, `UserTransformer`, `CategoryTransformer`, `TagTransformer`, `AddressTransformer`

### Extension Points

- **Custom Element Types**: Register via `ElementQueryService::EVENT_REGISTER_ELEMENT_TYPES` with `RegisterElementType` model
- **Custom Field Transformers**: Register via `BaseTransformer::EVENT_REGISTER_FIELD_TRANSFORMERS`
- **Custom Type Definitions**: Register via `TypescriptService::EVENT_REGISTER_TYPE_DEFINITIONS`

### API Endpoints

- `/<version>/api/queryApi/customQuery` - Main query endpoint
- `/<version>/api/queryApi/allRoutes` - Get all entry URLs
- `/<version>/api/queryApi/allRoutes/<siteIds>` - Get URLs for specific sites

### Configuration

Settings in `config/query-api.php`:
- `cacheDuration`: Override default cache duration
- `excludeFieldClasses`: Field classes to exclude from responses
- `includeFullEntry`: Return full entry data in relation fields (default: minimal)
- `hardPick`: Strict field selection mode
- `typeGenerationMode`: 'manual' or 'auto' TypeScript generation
- `typeGenerationOutputPath`: Where to write TypeScript definitions

### Namespace

`samuelreichoer\queryapi`
