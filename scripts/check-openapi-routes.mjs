import { readFileSync } from 'node:fs';
import { parse } from 'yaml';

const routesSource = readFileSync(new URL('../app/Config/Routes.php', import.meta.url), 'utf8');
const spec = parse(readFileSync(new URL('../docs/openapi.yaml', import.meta.url), 'utf8'));
const groupStart = routesSource.indexOf("$routes->group('api/v1'");
const groupEnd = routesSource.indexOf('// Compatibility shims', groupStart);

if (groupStart === -1 || groupEnd === -1) {
  throw new Error('Unable to locate the api/v1 route group.');
}

const routeOperations = new Set();
const routePattern = /\$routes->(get|post|patch|delete)\('([^']+)'/g;

for (const match of routesSource.slice(groupStart, groupEnd).matchAll(routePattern)) {
  const [, method, route] = match;
  const parameters = route.startsWith('channels/')
    ? ['channelId']
    : route.startsWith('messages/')
      ? ['messageId', 'emoji']
      : route.startsWith('webhooks/')
        ? ['webhookId']
        : route.startsWith('webhook-deliveries/')
          ? ['deliveryId']
      : [];
  let parameterIndex = 0;
  const normalizedRoute = route.replace(/\(:(?:num|segment)\)/g, () => {
    const parameter = parameters[parameterIndex];
    parameterIndex += 1;
    if (!parameter) {
      throw new Error(`No OpenAPI parameter mapping exists for ${route}.`);
    }
    return `{${parameter}}`;
  });

  routeOperations.add(`${method.toUpperCase()} /api/v1/${normalizedRoute}`);
}

const specOperations = new Set();
const operationMethods = new Set(['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace']);

for (const [path, pathItem] of Object.entries(spec.paths ?? {})) {
  for (const method of Object.keys(pathItem ?? {})) {
    if (operationMethods.has(method)) {
      specOperations.add(`${method.toUpperCase()} ${path}`);
    }
  }
}

const missing = [...routeOperations].filter((operation) => !specOperations.has(operation)).sort();
const extra = [...specOperations].filter((operation) => !routeOperations.has(operation)).sort();

if (missing.length > 0 || extra.length > 0) {
  const details = [
    missing.length > 0 ? `Missing from docs/openapi.yaml:\n- ${missing.join('\n- ')}` : '',
    extra.length > 0 ? `Not present in the api/v1 route group:\n- ${extra.join('\n- ')}` : '',
  ].filter(Boolean);
  throw new Error(details.join('\n'));
}

console.log(`OpenAPI route coverage is complete (${routeOperations.size} operations).`);
