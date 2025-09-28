# Release Notes for Craft Query API

## 3.5.0 - 2025-09-28

### Added

- Add support for `relatedTo` entry queries.
- Add support for `andRelatedTo` entry queries.
- Add support for `notRelated` entry queries.
- Add support for `andNotRelatedTo` entry queries.
- Add generated fields to custom query response and ts types.

### Fixed
- Fixes caching issues in plugin development.

## 3.4.0 - 2025-09-25

### Added

- Add `typeGenerationMode` setting, to enable automatic ts type generation after project config changes.
- Add `typeGenerationOutputPath` setting, to set a default path for the automatic and manual ts type generation.
- Add `modifyTypeByField` util, to make typing of custom fields easier.
- Add `getFieldHandleOrAttributeForField` util, to make typing for custom fields easier.

### Fixed
- Fixed ts output for fields that have an attribute but not as a public property.

### Changed
- Make TypeScript controller more reusable

## 3.3.0 - 2025-09-14

### Added

- Add enhancements to the `fields` property. It's now possible to filter the response with dot notation (entries.fieldHandle).
- Add new `*` wildcard to the `fields` property. This enables filtering of nested elements, while including everything else.
- Add new `includeFullEntry()` property. If this is true, related entries through the entries field get included in the response.
- Add new `includeFullEntry` setting. Globally disable or enable this property with the queryApi.php config.

### Fixed
- Fixed an issue with the serialization error handling of nested arrays.
- Fixed type issues of plugin settings.

### Changed
- Simplify matrix/content block base transformer.

## 3.2.1 - 2025-09-07

### Fixed

- Improve error handling of serialization errors that can occur with custom fields installed by modules or plugins in custom query endpoint.
- Fix php error that can occur if fields are trashed and accessed with getFields().

## 3.2.0 - 2025-07-20

### Added

- Added Support for the new Content Block field in Craft 5.8.0.
- Added icon transformer to reduce response data.

### Fixed

- Fixes a bug in the ts generation for Entry Types. It now checks for the title field in the entry type. If there is one, the title: string type gets added.

## 3.1.2 - 2025-06-20
- Remove authorOf query param from user, because not really doable.
- Add CraftPageBase to auto type generation.
- Fix caching invalidation issue with the fields() and element type property.
- Fix missing CORS header on cached results.

## 3.1.1 - 2025-05-17
- Fixed small indent issue on type generation.
- Fixed caching issues with preview urls.

## 3.1.0 - 2025-05-04
### Added
- Console command that generates TypeScript types for all element types.
- Add event to adjust generated types and to add types.
- Query API now requires Craft CMS 5.7 or higher.

### Improved
- Native link fields do return all data now and not just url and element type.
- Native field detection does work better now.
- Small refactorings for better readability.

## 3.0.1 - 2025-04-30
- Fix permission error in allRoutes api endpoint and add tests for this endpoint.
- Change some namings of allRoutes logic to enhance readability.

## 3.0.0 - 2025-03-23

### Breaking
- Added bearer token logic, including schemas and tokens. A bearer token is now required to query data.
- Changed plugin handle from `craft-query-api` to `query-api`.

### Added
- Control Panel section for schemas.
- Control Panel section for tokens.
- Permission logic based on schema scope.
- Clear "Query API data cache" option in `/admin/utilities/clear-caches`.
- CLI commands to create a public schema and token.
- Support for admin() parameter in user queries.

### Improved
- The `getFullUriFromUrl` utility no longer throws an error when querying entries without a section.
- Now the ready-to-rock JSON output is cached instead of the raw database query result.
- API error responses are more accurate now.

## 2.0.2 - 2025-03-16
- Change plugin name from 'Craft Query API' to 'Query API' and fix grammar in composer description.

## 2.0.1 - 2025-03-11
- Add fullUri property to metadata of entry to enhance dx experience in js frameworks.

## 2.0.0 - 2025-02-24
### Breaking
- Query API respects your field limit now. Relational fields with a max relation of 1 will not return an array anymore. They will return an object now.

### Added
- Support for Color Field
- Support for Country Field

### Bug Fixes
- Fix failing query of url encoded arrays for siteIds.

## 1.1.4 - 2024-01-09
- Fix missing property declaration in base transformer.

## 1.1.3 - 2024-12-20
- Add support for search query.
- Add `EVENT_REGISTER_ELEMENT_TYPES` for defining custom element types.
- Add settings for cache duration and exclude Field Classes.
- Change file and class name of `FieldTransformerEvent` to `RegisterFieldTransformersEvent`.

## 1.1.2 - 2024-11-23
- Add support for level, sectionId and type for entry queries.
- Add `EVENT_REGISTER_FIELD_TRANSFORMERS` for defining custom field transformer.
- Update Readme Typo.

## 1.1.1 - 2024-11-15
- Minimize JSON response data by deleting metadata that is not really useful.
- Fix a bug of the entry transformer when entries without section get queried.

## 1.1.0 - 2024-11-08
- Change API endpoints from `/<version>/api/controlername` to `/<version>/api/queryApi/controlername`.
- Cache duration is now the default Craft cache duration defined in `/config/general.php`.
- Update readme.
- Fix formatting issues in all PHP files.
- Fix missing title field in matrix transformer.

## 1.0.3 - 2024-10-31
- Change composer package name to my real repo name.

## 1.0.2 - 2024-10-30
- Add more information to the readme. Change Changelog format.

## 1.0.1 - 2024-10-30
- Add icon to plugin and more information in the readme.

## 1.0.0 - 2024-10.30
- Initial release.
