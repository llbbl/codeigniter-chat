import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import fs from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { applicationEnvironment, cachePath, databasePath, rootDir } from './support/environment';

const tokenPath = path.join(rootDir, 'writable/websocket_tokens.json');

export default async function globalSetup(): Promise<() => Promise<void>> {
  const tokenBackup = fs.existsSync(tokenPath) ? fs.readFileSync(tokenPath) : null;

  removeDatabaseFiles();
  removeCacheFiles();
  fs.mkdirSync(cachePath, { recursive: true });
  execFileSync('php', ['spark', 'migrate', '--all'], {
    cwd: rootDir,
    env: applicationEnvironment,
    stdio: 'inherit',
  });
  execFileSync('php', ['spark', 'db:seed', 'Tests\\Support\\Database\\Seeds\\E2eSeeder'], {
    cwd: rootDir,
    env: applicationEnvironment,
    stdio: 'inherit',
  });

  await assertPortFree(8080);
  const webSocketServer = spawn('php', ['spark', 'chat:websocket', '--port', '8080'], {
    cwd: rootDir,
    env: applicationEnvironment,
    stdio: 'ignore',
  });
  try {
    await waitForPort(webSocketServer, 8080);
  } catch (error) {
    await stopProcess(webSocketServer);
    throw error;
  }

  return async () => {
    await stopProcess(webSocketServer);
    removeDatabaseFiles();
    removeCacheFiles();

    if (tokenBackup === null) {
      fs.rmSync(tokenPath, { force: true });
    } else {
      fs.writeFileSync(tokenPath, tokenBackup);
    }
  };
}

function assertPortFree(port: number): Promise<void> {
  return new Promise((resolve, reject) => {
    const server = net.createServer();

    server.once('error', () => {
      reject(new Error(`Port ${port} is already in use; refusing to run E2E against an unknown server`));
    });
    server.listen(port, '127.0.0.1', () => {
      server.close(error => {
        if (error) {
          reject(error);
          return;
        }

        resolve();
      });
    });
  });
}

function waitForPort(child: ChildProcess, port: number): Promise<void> {
  return new Promise((resolve, reject) => {
    let settled = false;
    const finish = (callback: () => void): void => {
      if (settled) {
        return;
      }

      settled = true;
      clearTimeout(timeout);
      callback();
    };
    const timeout = setTimeout(
      () => finish(() => reject(new Error(`WebSocket server did not open port ${port}`))),
      15_000,
    );
    const poll = (): void => {
      if (settled) {
        return;
      }

      if (child.exitCode !== null) {
        finish(() => reject(new Error(`WebSocket server exited with code ${child.exitCode}`)));
        return;
      }

      const socket = net.connect({ host: '127.0.0.1', port });
      socket.once('connect', () => {
        socket.destroy();
        finish(resolve);
      });
      socket.once('error', () => {
        socket.destroy();
        if (!settled) {
          setTimeout(poll, 100);
        }
      });
    };

    poll();
  });
}

function stopProcess(child: ChildProcess): Promise<void> {
  if (child.exitCode !== null) {
    return Promise.resolve();
  }

  return new Promise(resolve => {
    const timeout = setTimeout(() => {
      child.kill('SIGKILL');
      resolve();
    }, 5_000);

    child.once('exit', () => {
      clearTimeout(timeout);
      resolve();
    });
    child.kill('SIGTERM');
  });
}

function removeCacheFiles(): void {
  fs.rmSync(cachePath, { recursive: true, force: true });
}

function removeDatabaseFiles(): void {
  for (const suffix of ['', '-shm', '-wal']) {
    fs.rmSync(`${databasePath}${suffix}`, { force: true });
  }
}
