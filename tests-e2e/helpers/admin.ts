import { expect, type APIRequestContext } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { extractDashboardRows, extractPollLinks, extractResultsPage, type DashboardRow, type ResultsPage } from './html';
import { expectRedirectTo, newVisitor, postForm } from './httpClient';

/** Usuário administrador de demonstração criado por database.sql. */
export const ADMIN_USER = 'admin';
/** E-mail do mesmo usuário (login também aceita e-mail). */
export const ADMIN_EMAIL = 'admin@example.com';
/** Senha de demonstração, pública no README e no seed. */
export const ADMIN_PASSWORD = 'admin123'; // gitleaks:allow

/** Caminho do seed do repositório (raiz do clone, um nível acima de tests-e2e/). */
const SEED_SQL_PATH = resolve(__dirname, '..', '..', 'database.sql');

/**
 * Lê do database.sql o hash bcrypt gravado na coluna `senha` do admin.
 * Usado para provar que enviar o hash como senha NÃO autentica.
 */
export function readSeedAdminPasswordHash(): string {
  const sql = readFileSync(SEED_SQL_PATH, 'utf8');
  const match = /INSERT INTO usuarios[\s\S]*?'admin',\s*'[^']+',\s*'(\$2[aby]\$\d{2}\$[^']+)'/.exec(sql);
  if (!match) {
    throw new Error(`Hash bcrypt do admin não encontrado em ${SEED_SQL_PATH}`);
  }
  return match[1];
}

/**
 * Cria um contexto novo e autentica como admin via POST /admin/autenticar.
 * Afirma o 302 para /admin/dashboard; devolve o contexto já com o cookie de sessão.
 */
export async function loginAsAdmin(login: string = ADMIN_USER, password: string = ADMIN_PASSWORD): Promise<APIRequestContext> {
  const admin = await newVisitor();
  const response = await postForm(admin, '/admin/autenticar', { nome_usuario: login, senha: password });
  expectRedirectTo(response, '/admin/dashboard');
  return admin;
}

/** Dados para criar uma enquete pelo painel. */
export interface NewPollInput {
  /** Título; use `uniqueTitle()` para não colidir entre execuções. */
  title: string;
  /** Descrição opcional. */
  description?: string;
  /** `ativa` (padrão) ou `inativa`. */
  status?: 'ativa' | 'inativa';
  /** Textos das opções, enviados como `opcoes[]` na ordem dada (vazios são enviados também). */
  options: string[];
}

/** Enquete criada pelo painel e localizada de volta no dashboard. */
export interface CreatedPoll {
  /** Id no banco (lido da coluna ID do dashboard). */
  id: number;
  /** Título exatamente como enviado. */
  title: string;
  /** Status persistido. */
  status: string;
  /** Slug público, se a enquete está ativa e aparece em /enquetes; senão `undefined`. */
  slug: string | undefined;
}

/**
 * Título único por execução, para que dados criados por um teste nunca colidam
 * com os de outra execução no mesmo banco.
 */
export function uniqueTitle(prefix: string): string {
  return `E2E ${prefix} ${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}

/**
 * Monta os pares do formulário de enquete (formEnquete.php) para POST /admin/salvar[/{id}].
 */
export function pollFormEntries(input: NewPollInput): Array<[string, string]> {
  const entries: Array<[string, string]> = [
    ['titulo', input.title],
    ['descricao', input.description ?? ''],
    ['status', input.status ?? 'ativa'],
  ];
  for (const option of input.options) {
    entries.push(['opcoes[]', option]);
  }
  return entries;
}

/**
 * Cria uma enquete como admin (POST /admin/salvar), afirma o 302 para o dashboard e
 * localiza a linha recém-criada pelo título. Se ativa, também resolve o slug em /enquetes.
 */
export async function createPoll(admin: APIRequestContext, input: NewPollInput): Promise<CreatedPoll> {
  const response = await postForm(admin, '/admin/salvar', pollFormEntries(input));
  expectRedirectTo(response, '/admin/dashboard');

  const row = await findDashboardRowByTitle(admin, input.title);
  if (!row) {
    throw new Error(`Enquete "${input.title}" não apareceu no dashboard após criação`);
  }
  const slug = row.status === 'ativa' ? await findPublicSlugByTitle(admin, input.title) : undefined;
  return { id: row.id, title: input.title, status: row.status, slug };
}

/**
 * Lê o dashboard e devolve todas as linhas da tabela.
 */
export async function dashboardRows(admin: APIRequestContext): Promise<DashboardRow[]> {
  const response = await admin.get('/admin/dashboard');
  expect(response.status()).toBe(200);
  return extractDashboardRows(await response.text());
}

/**
 * Procura no dashboard a linha cujo título é exatamente `title`.
 */
export async function findDashboardRowByTitle(admin: APIRequestContext, title: string): Promise<DashboardRow | undefined> {
  return (await dashboardRows(admin)).find((row) => row.title === title);
}

/**
 * Procura na listagem pública o slug da enquete com o título dado (só enquetes ativas aparecem).
 */
export async function findPublicSlugByTitle(ctx: APIRequestContext, title: string): Promise<string | undefined> {
  const response = await ctx.get('/enquetes');
  expect(response.status()).toBe(200);
  return extractPollLinks(await response.text()).find((link) => link.title === title)?.slug;
}

/**
 * Lê /admin/resultados/{id} como admin e devolve a página parseada.
 * É a única forma, sem acesso direto ao banco, de contar votos por opção.
 */
export async function resultsOf(admin: APIRequestContext, pollId: number): Promise<ResultsPage> {
  const response = await admin.get(`/admin/resultados/${pollId}`);
  expect(response.status(), `GET /admin/resultados/${pollId}`).toBe(200);
  return extractResultsPage(await response.text());
}

/**
 * Percentual como a view PHP imprime: `round(votes / total * 100, 2)` ecoado como float
 * (PHP não mostra zeros à direita: 50.0 → "50", 33.333 → "33.33").
 */
export function expectedPercent(votes: number, total: number): string {
  if (total === 0) {
    return '0';
  }
  return String(Number(((votes / total) * 100).toFixed(2)));
}

/**
 * Textos das opções da página de resultados ordenados (ordem de code unit, previsível). A view ordena por
 * `total_votos DESC`, então empates não têm ordem garantida — compare como conjunto.
 */
export function sortedOptionTexts(page: ResultsPage): string[] {
  return page.rows.map((row) => row.text).sort();
}
