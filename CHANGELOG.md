# Release Notes for Craft Query API

## 1.0.0 - 2024-10.30
- Initial release

## 1.0.1 - 2024-10.30
- Add icon to plugin and more information in the readme.

## 1.0.2 - 2024-10.30
- Add more information to the readme. Change Changelog format

## 1.0.3 - 2024-10.31
- Change composer package name to my real repo name.

## 1.1.0 - 2024-11.08

- Change API endpoints from `/<version>/api/controlername` to `/<version>/api/queryApi/controlername`.
- Cache duration is now the default craft cache duration defined in `/config/general.php`.
- Update readme
- Fix formatting issues in all php files.
- Fix missing title field in matrix transformer.

## 1.1.1 - 2024-11-15

- Minimize json response data by deleting metadata that is not really useful.
- Fix a bug of the entry transformer when entries without section get queried.
