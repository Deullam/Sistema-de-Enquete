import { test, expect, type APIRequestContext } from '@playwright/test';
import { containsJavaScriptAssets, extractPollLinks } from '../helpers/html';
import { disposeAll, newVisitor } from '../helpers/httpClient';

/** Enquetes do seed (database.sql): duas ativas, uma inativa. */
const SEED_ACTIVE_POLLS = [
  { slug: 'atividade-fim-de-semana', title: 'Atividade Favorita de Fim de Semana' },
  { slug: 'linguagem-programacao', title: 'Melhor Linguagem de Programação' },
];
const SEED_INACTIVE_SLUG = 'ambiente-trabalho';

// EN-01 — Visitante lista as enquetes ativas (GET / e GET /enquetes).
test.describe('EN-01 public poll listing', () => {
  let visitor: APIRequestContext;

  test.beforeEach(async () => {
    visitor = await newVisitor();
  });

  test.afterEach(async () => {
    await disposeAll(visitor);
  });

  // AC1: GET / e GET /enquetes → 200, só títulos de enquetes ativas, cada um com link /enquetes/{slug}.
  for (const path of ['/', '/enquetes']) {
    test(`shouldListOnlyActiveSeedPollsWithSlugLinksOn ${path}`, async () => {
      const response = await visitor.get(path);
      expect(response.status()).toBe(200);
      expect(response.headers()['content-type']).toContain('text/html');

      const html = await response.text();
      expect(html).toContain('<h1>Nossas Enquetes</h1>');
      expect(html).toContain('<ul class="enquete-lista">');

      const links = extractPollLinks(html);
      for (const seedPoll of SEED_ACTIVE_POLLS) {
        expect(links).toContainEqual(seedPoll);
        expect(html).toContain(`href="/enquetes/${seedPoll.slug}"`);
      }
      // Negativo: a inativa do seed nunca aparece, nem por slug nem por título.
      expect(links.map((link) => link.slug)).not.toContain(SEED_INACTIVE_SLUG);
      expect(html).not.toContain('Ambiente de Trabalho Preferido');
      expect(html).not.toContain('Nenhuma enquete encontrada no momento.');
    });
  }

  // AC1 (complemento): cada link listado abre de fato (200) — nenhum slug quebrado na listagem.
  test('shouldLinkEveryListedPollToAWorkingDetailPage', async () => {
    const response = await visitor.get('/enquetes');
    expect(response.status()).toBe(200);
    const links = extractPollLinks(await response.text());
    expect(links.length).toBeGreaterThanOrEqual(SEED_ACTIVE_POLLS.length);

    for (const link of links) {
      const detail = await visitor.get(`/enquetes/${link.slug}`);
      expect(detail.status(), `GET /enquetes/${link.slug}`).toBe(200);
      expect(await detail.text()).toContain(`<h1>${escapeForHtml(link.title)}</h1>`);
    }
  });

  // AC3: nenhuma página pública contém <script> nem referência a arquivo .js.
  for (const path of ['/', '/enquetes', `/enquetes/${SEED_ACTIVE_POLLS[0].slug}`, '/admin/login']) {
    test(`shouldServePublicPageWithoutJavaScript ${path}`, async () => {
      const response = await visitor.get(path);
      expect(response.status()).toBe(200);
      const html = await response.text();
      expect(containsJavaScriptAssets(html), `JavaScript encontrado em ${path}`).toBe(false);
      expect(html).toContain('<link rel="stylesheet" href="/css/estilo.css">');
    });
  }

  // AC3 (complemento): o único asset estático é o CSS, servido direto pelo Apache (sem passar pelo Router).
  test('shouldServeStylesheetAsStaticFile', async () => {
    const response = await visitor.get('/css/estilo.css');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/css');
    expect(await response.text()).not.toContain('<h1>Erro 404</h1>');
  });

  // AC2 (nenhuma enquete ativa → "Nenhuma enquete encontrada no momento.") NÃO é coberto aqui:
  // exigiria desativar as enquetes do seed no banco compartilhado, e editar uma enquete
  // recalcula o slug (AdminController::salvar), o que quebraria EN-02/EN-03. Lacuna registrada.
});

/** Reproduz htmlspecialchars() para o pouco que os títulos do seed precisam (sem entidades). */
function escapeForHtml(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
