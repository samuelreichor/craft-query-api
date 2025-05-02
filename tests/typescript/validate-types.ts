process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
import {
  craftAddressSchema,
  craftPageDefaultFieldsSchema,
  craftPageRelationalFieldsWithMaxSettingSchema,
  craftUserSchema,
  craftVolumeGraphicsSchema,
  craftVolumeImagesSchema
} from './generated-schemas';

type Mode = 'dev' | 'stage';
const environment: Mode = process.argv[2] as Mode;

if(!environment) {
  console.error('You have to provide a environment (stage, dev)')
}

const token = 'tyE9LViYm0HvcVbUErN1wwIa3qyeby1K'
const baseUrl = {
  dev: 'https://backend-craftcms.ddev.site:8443/v1/api/queryApi/customQuery',
  stage: 'https://query-api.testing.steelcity-creative.at/v1/api/queryApi/customQuery',
}
const urlsToCheck = [
  {
    url: '?elementType=entries&section=fieldCheck&id=48&one=1',
    schema: craftPageDefaultFieldsSchema,
  },
  {
    url: '?elementType=entries&section=fieldCheck&id=60&one=1',
    schema: craftPageRelationalFieldsWithMaxSettingSchema,
  },
  {
    url: '?elementType=addresses&one=1',
    schema: craftAddressSchema,
  },
  {
    url: '?elementType=assets&volume=images&one=1',
    schema: craftVolumeImagesSchema,
  },
  {
    url: '?elementType=assets&volume=graphics&one=1',
    schema: craftVolumeGraphicsSchema,
  },
  {
    url: '?elementType=users&group=editor&one=1',
    schema: craftUserSchema,
  },
];

async function validateResponses(mode: 'dev' | 'stage') {
  for (const { url, schema } of urlsToCheck) {
    const apiUrl = baseUrl[mode] + url;
    try {
      console.log(`Fetching ${apiUrl}`);

      const response = await fetch(apiUrl, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        },
      });

      const data = await response.json();
      schema.parse(data); // throws if invalid

      console.log('\x1b[32m%s\x1b[0m', `✔ ${url} passed validation`);
    } catch (err) {
      console.error(`Validation failed for ${apiUrl}`);
      console.error(err);
      process.exitCode = 1;
    }
  }
}

validateResponses(environment)
