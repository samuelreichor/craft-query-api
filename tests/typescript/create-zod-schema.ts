import { execSync } from 'child_process';
import { Project } from "ts-morph"

type Mode = 'dev' | 'stage';
const environment: Mode = process.argv[2] as Mode;

if(!environment) {
  console.error('You have to provide a environment (stage, dev)')
}

console.log('Generating TypeScript types via Craft CLI...');
execSync('npm run generate:types:' + environment, { stdio: 'inherit' });

console.log('Converting generated TypeScript to Zod...');
execSync('npm run zodify', { stdio: 'inherit' });
console.log('\x1b[32m%s\x1b[0m', '✔ Zod schemas generated successfully');

console.log('Update generated "craftEntryTypeRelationalFieldsWithMaxSettingSchema" to prevent an error')
const project = new Project();
const file = project.addSourceFileAtPath("./tests/typescript/generated-schemas.ts");

const variable = file.getVariableDeclaration("craftEntryTypeRelationalFieldsWithMaxSettingSchema");
if (variable) {
  variable.setType("z.ZodObject<any, any, any>");
  variable.setInitializer(`z.object({
    title: z.string(),
    singleRelatedAddress: craftAddressSchema.nullable(),
    singleRelatedAsset: craftAssetSchema.nullable(),
    singleRelatedCategory: craftCategoryNewsFilterSchema.nullable(),
    singleMatrix: craftEntryTypeCtaSchema.nullable(),
    singleRelatedEntry: craftEntryRelationSchema.nullable(),
    singleRelatedUser: craftUserSchema.nullable(),
    matrixMaxRelations: z.lazy(() => craftEntryTypeRelationalFieldsWithMaxSettingSchema.nullable()),
  })`);
  file.saveSync();
}
console.log('\x1b[32m%s\x1b[0m', '✔ craftEntryTypeRelationalFieldsWithMaxSettingSchema schema successfully updated');


