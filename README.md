<div align="center">
	<a href="https://packagist.org/packages/samuelreichoer/craft-query-api"  align="center">
      <img src="https://online-images-sr.netlify.app/assets/craft-query-api.png" width="100" alt="Craft Query API">
	</a>
  <br>
	<h1 align="center">The Query API for Craft CMS</h1>
  <p align="center">
    Craft Query API is a Craft CMS plugin to use the loved query builder in your favorite js-framework.
  </p>
  <br/>
</div>

<p align="center">
  <a href="https://packagist.org/packages/samuelreichoer/craft-query-api">
    <img src="https://img.shields.io/packagist/v/samuelreichoer/craft-query-api?label=version&color=blue">
  </a>
  <a href="https://packagist.org/packages/samuelreichoer/craft-query-api">
    <img src="https://img.shields.io/packagist/dt/samuelreichoer/craft-query-api?color=blue">
  </a>
  <a href="https://packagist.org/packages/samuelreichoer/craft-query-api">
    <img src="https://img.shields.io/packagist/php-v/samuelreichoer/craft-query-api?color=blue">
  </a>
  <a href="https://packagist.org/packages/samuelreichoer/craft-query-api">
    <img src="https://img.shields.io/packagist/l/samuelreichoer/craft-query-api?color=blue">
  </a>
</p>


> [!WARNING]  
> This npm package is still in production and important features may change.

## Features

- API to query addresses, assets, entries and users based on url parameters.
- API for query all urls of every active page with template for prerendering.
- Automatic detection of imagerx transforms.
- Automatic detection of native and custom fields on all elementTypes.

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Qick start

1. Set up a craft project by using that guide: https://craftcms.com/docs/getting-started-tutorial/install/
2. Install the Craft Query API Plugin using that command:
    ```bash
    composer require samuelreichor/craft-query-api && php craft plugin/install craft-query-api
    ```
3. Add following config to prevent cors origin errors in that file `config/app.web.php`
    ```php
      <?php

      return [
          'as corsFilter' => [
              'class' => \craft\filters\Cors::class,

              // Add your origins here
              'cors' => [
                  'Origin' => [
                      'http://localhost:3000',
                      'http://localhost:5173',
                  ],
                  'Access-Control-Request-Method' => ['GET'],
                  'Access-Control-Request-Headers' => ['*'],
                  'Access-Control-Allow-Credentials' => true,
                  'Access-Control-Max-Age' => 86400,
                  'Access-Control-Expose-Headers' => [],
              ],
          ],
      ];
    ```

4. That's it you can test it by hitting that endpoint `${PRIMARY_SITE_URL}/v1/api/customQuery`. You should get an empty
   array as response.

## Optional things to configure:

### Set headless mode in your `config/general.php` to `true`

Read more about the headless
mode [here](https://craftcms.com/docs/getting-started-tutorial/more/graphql.html#optional-enable-headless-mode).

### Tell Craft where your frontend lives

This is important, to get previews of entries working.

Add a new env var:

```
WEBSITE_URL="http://localhost:3000"
```

Add new alias in the config/general.php:

```
->aliases([
    '@web' => getenv('PRIMARY_SITE_URL'),
    '@websiteUrl' => getenv('WEBSITE_URL'),
])
```

And finally go to your control panel settings -> sites -> and change the base URL of your Site.

### Configure ImagerX

If you are using ImagerX (what I recommend) then you have to generate all transforms of the images before you can query
them. Therefore you need a `imager-x-generate.php` File in your config folder with all named transforms in it. This can
look like that:

```php
<?php

return [
    'volumes' => [
        'images' => ['auto', 'square', 'landscape', 'portrait', 'dominantColor'],
    ]
];
```

The plugin will automatically detect the named transforms and widths defined in your `imager-x-transforms.php`. In the
response, you'll get an object where the key is the name of the transform, and the value is a srcset of all defined
transforms.

### Configure SEO Matic

Seo Matic has its own Endpoints. You can enable these in the plugin setting.

## Further Resources

- [Core JS Query Builder](https://github.com/samuelreichor/js-craftcms-api)
- [Craft CMS Vue Plugin](https://github.com/samuelreichor/vue-craftcms)
- [Craft CMS Nuxt Plugin](https://github.com/samuelreichor/nuxt-craftcms)

## Support

- Bugs or Feature Requests? [Submit an issue](/../../issues/new).

## Contributing

Contributions are welcome! <3


