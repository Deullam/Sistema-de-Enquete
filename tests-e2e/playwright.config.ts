import { defineConfig } from '@playwright/test';
import { BASE_URL } from './helpers/httpClient';

/**
 * Suíte E2E em HTTP puro: nenhum projeto de browser, só `request` (APIRequestContext).
 * A pilha (php:8.4-apache + mysql:8.0) sobe antes via docker-compose.e2e.yml.
 * workers: 1 e fullyParallel: false porque todos os specs compartilham o mesmo banco.
 */
export default defineConfig({
  testDir: './tests',
  testMatch: /.*\.spec\.ts$/,
  globalSetup: './global-setup.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: true,
  reporter: 'list',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  use: {
    baseURL: BASE_URL,
    trace: 'off',
  },
});
