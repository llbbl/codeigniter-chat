import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';
import { parse } from 'yaml';

const root = document.querySelector('#swagger-ui');

if (!(root instanceof HTMLElement)) {
  throw new Error('Swagger UI mount point is missing.');
}

const encodedSpec = root.dataset.openapiBase64;

if (!encodedSpec) {
  throw new Error('The embedded OpenAPI specification is missing.');
}

const binarySpec = atob(encodedSpec);
const specBytes = Uint8Array.from(binarySpec, (character) => character.charCodeAt(0));
const spec = parse(new TextDecoder().decode(specBytes));

SwaggerUIBundle({
  deepLinking: true,
  displayRequestDuration: true,
  dom_id: '#swagger-ui',
  filter: true,
  requestInterceptor(request) {
    request.credentials = 'same-origin';
    return request;
  },
  spec,
});
