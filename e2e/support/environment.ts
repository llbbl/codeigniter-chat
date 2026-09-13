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
  DB_DRIVER: 'SQLite3',
  'app.baseURL': `${baseURL}/`,
  'app.CSPEnabled': 'false',
  'cookie.secure': 'false',
  'cache.file.storePath': cachePath,
  'database.sqlite.database': databasePath,
};
