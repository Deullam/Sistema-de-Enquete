import { request } from '@playwright/test';
import { BASE_URL } from './helpers/httpClient';

/** Tentativas de contato com a aplicação antes de desistir (1 s entre cada). */
const MAX_ATTEMPTS = 30;

/**
 * Falha cedo, com mensagem clara, se a pilha do docker-compose.e2e.yml não estiver no ar.
 * Espera determinística: tenta GET /enquetes até obter QUALQUER resposta HTTP
 * (o conteúdo é responsabilidade dos specs), no máximo MAX_ATTEMPTS vezes.
 */
export default async function globalSetup(): Promise<void> {
  const ctx = await request.newContext({ baseURL: BASE_URL, maxRedirects: 0 });
  try {
    for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt += 1) {
      try {
        const response = await ctx.get('/enquetes', { timeout: 2_000 });
        if (response.status() > 0) {
          return;
        }
      } catch {
        // servidor ainda não aceita conexão; tenta de novo
      }
      await new Promise((done) => setTimeout(done, 1_000));
    }
    throw new Error(
      `Aplicação não respondeu em ${BASE_URL} após ${MAX_ATTEMPTS} tentativas. ` +
        'Suba a pilha: cd tests-e2e && docker compose -f docker-compose.e2e.yml up -d --wait',
    );
  } finally {
    await ctx.dispose();
  }
}
