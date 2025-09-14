function isValidDataResp(keys, isArr = false) {
  const resp = res.getBody();
  test("Response has all required properties", function () {
    if (isArr) {
      expect(Array.isArray(resp)).to.be.true;
      resp.forEach(obj => {
        keys.forEach(key => {
          expect(obj).to.have.property(key);
        })
      })
    } else {
      keys.forEach(key => {
        expect(resp).to.have.property(key);
      })
    }
  });
}

function isValidNestedDataResp(reqKeys) {
  const body = res.getBody();

  Object.entries(reqKeys).forEach(([key, [keysToCheck, isArray, expectedType]]) => {
    const nestedData = body[key];

    test(`Property "${key}" should exist`, function () {
      expect(nestedData).to.not.be.undefined;
    });

    test(`"${key}" should be ${isArray ? 'an array' : (keysToCheck.length ? 'an object' : 'a primitive')}`, function () {
      if (isArray) {
        expect(Array.isArray(nestedData)).to.be.true;

        nestedData.forEach((item, i) => {
          keysToCheck.forEach((innerKey) => {
            expect(item, `Missing key "${innerKey}" in item ${i} of "${key}"`).to.have.property(innerKey);
          });
        });

      } else {
        if (keysToCheck.length === 0) {
          // Primitive Check mit optionaler Typprüfung
          expect(typeof nestedData, `"${key}" should be of type "${expectedType}"`).to.not.equal('object');

          if (expectedType) {
            expect(typeof nestedData, `"${key}" should be of type "${expectedType}"`).to.equal(expectedType);
          }

        } else {
          expect(nestedData).to.be.an('object');
          keysToCheck.forEach((innerKey) => {
            expect(nestedData).to.have.property(innerKey);
          });
        }
      }
    });
  });
}

function assertAllRelationalFieldsAreObjects(obj, path = '') {
  Object.entries(obj).forEach(([key, value]) => {
    const fullPath = path ? `${path}.${key}` : key;

    // Nur Keys, die mit "singleRelated", "singleMatrix", oder "matrixMaxRelations" beginnen
    const isRelational = /^singleRelated|^singleMatrix|^matrixMaxRelations/.test(key);

    if (isRelational) {
      test(`"${fullPath}" should be object or null (not array)`, function () {
        if (value !== null) {
          expect(Array.isArray(value), `"${fullPath}" should not be an array`).to.be.false;
          expect(typeof value, `"${fullPath}" should be an object`).to.equal('object');
        }
      });
    }

    // Wenn value selbst ein Objekt ist, tiefer prüfen
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      assertAllRelationalFieldsAreObjects(value, fullPath);
    }
  });
}

const reqKeys = {
  addresses: ['metadata', 'title', 'addressLine1', 'countryCode', 'locality', 'postalCode'],
  assets: ['metadata', 'height', 'width', 'focalPoint', 'url', 'srcSets'],
  entries: ['metadata', 'sectionHandle', 'title'],
  users: ['metadata', 'username', 'email'],
  option: ['label', 'value', 'selected', 'valid'],
}

function isValidAddressResp(isArr = false) {
  isValidDataResp(reqKeys.addresses, isArr)
}

function isValidAssetResp(isArr = false) {
  isValidDataResp(reqKeys.assets, isArr)
}

function isValidEntryResp(isArr = false) {
  isValidDataResp(reqKeys.entries, isArr)
}

function isValidUserResp(isArr = false) {
  isValidDataResp(reqKeys.users, isArr)
}

function isValidNavNodeResp(isArr = false) {
  const properties = ['metadata', 'title', 'url', 'type', 'level',]

  isValidDataResp(properties, isArr)
}

function isValidAllDefaultFieldsResp(isArr = false) {
  const properties = {
    address: [reqKeys.addresses, true],
    asset: [reqKeys.assets, true],
    buttonGroup: [reqKeys.option, false],
    categories: [['metadata', 'title', 'slug', 'uri'], true],
    checkboxes: [reqKeys.option, true],
    color: [['hex', 'rgb', 'hsl'], false],
    country: [['name', 'countryCode', 'threeLetterCode', 'locale', 'currencyCode', 'timezones'], false],
    date: [['date', 'timezone'], false],
    dropdown: [reqKeys.option, false],
    email: [[], false, 'string'],
    entries: [['title', 'slug', 'url', 'id'], true],
    iconField: [[], false, 'string'],
    json: [['id'], false],
    lightswitch: [[], false, 'boolean'],
    linkField: [['elementType', 'url'], false],
    matrix: [['type', 'title', 'headlineTag'], true],
    money: [['amount', 'currency'], false],
    multiSelect: [reqKeys.option, true],
    number: [[], false, 'number'],
    plainText: [[], false, 'string'],
    radioButtons: [reqKeys.option, false],
    range: [[], false, 'number'],
    table: [['col1', 'col2', 'col1Handle', 'col2Handle'], true],
    tags: [['metadata', 'title', 'slug'], true],
    time: [['date', 'timezone'], false],
    users: [reqKeys.users, true],
  }
  isValidNestedDataResp(properties, true)
}

function isValidMaxFieldSettingStructure() {
  const resp = res.getBody()
  assertAllRelationalFieldsAreObjects(resp);
}

function isValidAllRoutesResp() {
  const resp = res.getBody();
  test("Response of allRoutes endpoint is valid", function () {
      expect(Array.isArray(resp)).to.be.true;
      resp.forEach(url => {
        expect(url).to.be.a('string').that.does.include('http');
      })
  });
}

// Returns all nodes that match a dotted path with [] array markers, starting from root.
function getNodesAtPath(root, path) {
  if (!path) return [root];
  const tokens = path.split('.');
  let nodes = [root];

  tokens.forEach((token) => {
    const isArrayToken = token.endsWith('[]');
    const key = isArrayToken ? token.slice(0, -2) : token;

    const next = [];
    nodes.forEach((node) => {
      const val = node?.[key];

      test(`Path "${key}" exists`, function () {
        expect(val, `Missing "${key}"`).to.not.be.undefined;
      });

      if (isArrayToken) {
        test(`"${key}" is an array`, function () {
          expect(Array.isArray(val), `"${key}" should be an array`).to.be.true;
        });
        if (Array.isArray(val)) {
          val.forEach((item) => next.push(item));
        }
      } else {
        next.push(val);
      }
    });

    nodes = next;
  });

  return nodes;
}

// Validates keys and/or type for every matched node at path,
// and optionally validates nested children relative to that node.
function assertPath(body, path, rule) {
  const { keys = [], type, children } = rule || {};
  const nodes = getNodesAtPath(body, path);

  nodes.forEach((node, i) => {
    if (type) {
      test(`"${path}"[${i}] is of type "${type}"`, function () {
        // primitives: typeof; objects/arrays gesondert
        if (type === 'array') expect(Array.isArray(node)).to.be.true;
        else if (type === 'object') expect(node).to.be.an('object');
        else expect(typeof node).to.equal(type);
      });
    }
    if (keys.length) {
      test(`"${path}"[${i}] has required keys`, function () {
        expect(node).to.be.an('object');
        keys.forEach((k) => expect(node, `Missing key "${k}" at ${path}[${i}]`).to.have.property(k));
      });
    }
    if (children) {
      Object.entries(children).forEach(([childPath, childRule]) => {
        // childPath ist relativ zum aktuellen node, z.B. "entries[]" oder "asset[]"
        assertPath(node, childPath, childRule);
      });
    }
  });
}

// Public API: pass a schema (map path -> rule)
function isValidNestedByPaths(schema) {
  const body = res.getBody();
  Object.entries(schema).forEach(([path, rule]) => assertPath(body, path, rule));
}


module.exports = {
  isValidDataResp,
  isValidNestedDataResp,
  isValidAddressResp,
  isValidAssetResp,
  isValidEntryResp,
  isValidUserResp,
  isValidNavNodeResp,
  isValidAllDefaultFieldsResp,
  isValidMaxFieldSettingStructure,
  isValidAllRoutesResp,
  isValidNestedByPaths,
}
