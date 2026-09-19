import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const rootDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
export const baseURL = 'http://127.0.0.1:8085';
export const databasePath = path.join(rootDir, 'writable/database/e2e.sqlite');
export const cachePath = path.join(rootDir, 'writable/cache/e2e');

const inheritedEnvironment = Object.fromEntries(
  Object.entries(process.env).filter((entry): entry is [string, string] => entry[1] !== undefined),
);

export const applicationEnvironment: Record<string, string> = {
  ...inheritedEnvironment,
  CI_ENVIRONMENT: 'development',
  APP_URL: `${baseURL}/`,
  APP_PORT: '8085',
  DB_DRIVER: 'SQLite3',
  WEBSOCKET_URL: 'ws://127.0.0.1:8080',
  WEBSOCKET_TOKEN_SECRET: `e2e-${'x'.repeat(32)}`,
  app_baseURL: `${baseURL}/`,
  app_CSPEnabled: 'false',
  cookie_secure: 'false',
  cache_file_storePath: cachePath,
  database_sqlite_database: databasePath,
};
