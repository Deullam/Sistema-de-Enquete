import { expect, request, type APIRequestContext, type APIResponse } from '@playwright/test';

/**
 * URL base da aplicação em contêiner (ver docker-compose.e2e.yml, porta 8089 do host).
 * Pode ser sobrescrita com E2E_BASE_URL para apontar a outra publicação da pilha.
 */
export const BASE_URL: string = process.env.E2E_BASE_URL ?? 'http://localhost:8089';

/**
 * Nome do cookie de sessão do PHP (session.name padrão).
 */
export const SESSION_COOKIE_NAME = 'PHPSESSID';

/**
 * Par chave/valor de um formulário HTML; a lista permite chaves repetidas (ex.: `opcoes[]`).
 */
export type FormEntries = ReadonlyArray<readonly [string, string]> | Readonly<Record<string, string>>;

/**
 * Cria um cliente HTTP novo, sem cookies: equivale a um visitante com sessão PHP distinta.
 * `maxRedirects: 0` garante que cada 302 seja afirmado explicitamente pelos testes,
 * em vez de ser seguido em silêncio.
 */
export async function newVisitor(): Promise<APIRequestContext> {
  return request.newContext({ baseURL: BASE_URL, maxRedirects: 0 });
}

/**
 * Envia um POST `application/x-www-form-urlencoded`, como um `<form method="POST">` faria.
 * Aceita lista de pares para campos repetidos (`opcoes[]`).
 */
export async function postForm(ctx: APIRequestContext, path: string, fields: FormEntries): Promise<APIResponse> {
  const params = new URLSearchParams();
  const entries: ReadonlyArray<readonly [string, string]> = Array.isArray(fields)
    ? (fields as ReadonlyArray<readonly [string, string]>)
    : Object.entries(fields as Record<string, string>);
  for (const [key, value] of entries) {
    params.append(key, value);
  }
  return ctx.post(path, {
    data: params.toString(),
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
  });
}

/**
 * Afirma que a resposta é um redirecionamento 302 para `location` (valor exato do header
 * `Location`, que a aplicação emite relativo, ex.: `/admin/login`).
 */
export function expectRedirectTo(response: APIResponse, location: string): void {
  expect(response.status(), `esperava 302 para ${location}, veio ${response.status()} em ${response.url()}`).toBe(302);
  expect(response.headers()['location']).toBe(location);
}

/**
 * Afirma que a resposta NÃO é redirecionamento (status 200 e sem header `Location`).
 */
export function expectNoRedirect(response: APIResponse): void {
  expect(response.status()).toBe(200);
  expect(response.headers()['location']).toBeUndefined();
}

/**
 * Devolve o valor atual do cookie de sessão PHP guardado no contexto, ou `undefined`
 * se o servidor ainda não emitiu um.
 */
export async function sessionCookieOf(ctx: APIRequestContext): Promise<string | undefined> {
  const state = await ctx.storageState();
  return state.cookies.find((cookie) => cookie.name === SESSION_COOKIE_NAME)?.value;
}

/**
 * Faz um GET enviando manualmente um cookie de sessão específico, ignorando o jar do
 * contexto. Serve para provar que um id de sessão antigo não vale mais no servidor.
 */
export async function getWithSessionCookie(
  ctx: APIRequestContext,
  path: string,
  sessionId: string,
): Promise<APIResponse> {
  return ctx.get(path, { headers: { cookie: `${SESSION_COOKIE_NAME}=${sessionId}` } });
}

/**
 * Descarta com segurança vários contextos ao fim de um teste.
 */
export async function disposeAll(...contexts: Array<APIRequestContext | undefined>): Promise<void> {
  for (const ctx of contexts) {
    if (ctx) {
      await ctx.dispose();
    }
  }
}

