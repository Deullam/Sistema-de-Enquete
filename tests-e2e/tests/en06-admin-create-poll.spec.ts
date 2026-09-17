import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  createPoll,
  dashboardRows,
  findDashboardRowByTitle,
  findPublicSlugByTitle,
  loginAsAdmin,
  pollFormEntries,
  resultsOf,
  sortedOptionTexts,
  uniqueTitle,
} from '../helpers/admin';
import { extractEditForm, extractPollLinks } from '../helpers/html';
import { disposeAll, expectNoRedirect, expectRedirectTo, newVisitor, postForm } from '../helpers/httpClient';
import { voteFormOf } from '../helpers/voting';

/** Mensagem que AdminController::salvar() ecoa quando a validação falha. */
const VALIDATION_ERROR_TEXT = 'Erro: O título é obrigatório e a enquete deve ter pelo menos 2 opções.';

// EN-06 — Administrador cria uma nova enquete.
test.describe('EN-06 admin creates a poll', () => {
  let admin: APIRequestContext;

  test.beforeAll(async () => {
    admin = await loginAsAdmin();
  });

  test.afterAll(async () => {
    await disposeAll(admin);
  });

  test('shouldRenderEmptyCreateForm', async () => {
    const response = await admin.get('/admin/criar');
    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<h1>Criar Nova Enquete</h1>');
    const form = extractEditForm(html);
    expect(form.action).toBe('/admin/salvar');
    expect(form.title).toBe('');
    expect(form.description).toBe('');
    expect(form.options).toEqual(['', '']);
  });

  // AC1: título + ≥2 opções → 302 /admin/dashboard; aparece no dashboard e, se ativa, em /enquetes
  // com o slug único (slug-base + uniqid) e todas as opções.
  test('shouldCreateActivePollVisibleInDashboardAndPublicListing', async () => {
    const title = uniqueTitle('create-active');
    const description = 'Descrição da enquete criada no E2E';
    const rowsBefore = await dashboardRows(admin);

    const poll = await createPoll(admin, { title, description, status: 'ativa', options: ['Um', 'Dois', 'Três'] });

    expect(poll.status).toBe('ativa');
    expect(poll.slug).toBeDefined();
    // slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', titulo)) + '-' + uniqid()
    expect(poll.slug).toMatch(/^e2e-create-active-[a-z0-9-]+-[0-9a-f]{13}$/);
    expect((await dashboardRows(admin)).length).toBe(rowsBefore.length + 1);

    const visitor = await newVisitor();
    try {
      const form = await voteFormOf(visitor, poll.slug!);
      expect(form.pollId).toBe(poll.id);
      expect(form.options.map((option) => option.text)).toEqual(['Um', 'Dois', 'Três']);
      const detail = await visitor.get(`/enquetes/${poll.slug}`);
      expect(await detail.text()).toContain(`<p>${description}</p>`);
    } finally {
      await disposeAll(visitor);
    }

    const results = await resultsOf(admin, poll.id);
    expect(results.title).toBe(title);
    expect(results.total).toBe(0);
    expect(sortedOptionTexts(results)).toEqual(['Dois', 'Três', 'Um']);
  });

  // AC1: enquete inativa aparece no dashboard mas NÃO em /enquetes.
  test('shouldCreateInactivePollHiddenFromPublicListing', async () => {
    const title = uniqueTitle('create-inactive');
    const poll = await createPoll(admin, { title, status: 'inativa', options: ['Sim', 'Não'] });
    expect(poll.status).toBe('inativa');
    expect(poll.slug).toBeUndefined();
    expect(await findPublicSlugByTitle(admin, title)).toBeUndefined();
  });

  // AC1: dois POSTs com o MESMO título geram slugs distintos (uniqid) — sem colisão no UNIQUE.
  test('shouldGenerateDistinctSlugsForSameTitle', async () => {
    const title = uniqueTitle('create-twice');
    const first = await createPoll(admin, { title, status: 'ativa', options: ['A', 'B'] });
    const second = await postForm(admin, '/admin/salvar', pollFormEntries({ title, status: 'ativa', options: ['A', 'B'] }));
    expectRedirectTo(second, '/admin/dashboard');

    const rows = (await dashboardRows(admin)).filter((row) => row.title === title);
    expect(rows).toHaveLength(2);
    const listing = await admin.get('/enquetes');
    const slugs = extractPollLinks(await listing.text())
      .filter((link) => link.title === title)
      .map((link) => link.slug);
    expect(slugs).toHaveLength(2);
    expect(new Set(slugs).size).toBe(2);
    expect(slugs).toContain(first.slug);
  });

  // AC2: 3 opções com 1 em branco → só as 2 não vazias são persistidas.
  test('shouldDiscardBlankOptionsOnCreate', async () => {
    const title = uniqueTitle('create-blank-option');
    const poll = await createPoll(admin, { title, status: 'ativa', options: ['Primeira', '   ', 'Terceira'] });
    const results = await resultsOf(admin, poll.id);
    expect(sortedOptionTexts(results)).toEqual(['Primeira', 'Terceira']);

    const edit = await admin.get(`/admin/editar/${poll.id}`);
    expect(extractEditForm(await edit.text()).options).toEqual(['Primeira', 'Terceira']);
  });

  // Título e opções passam por htmlspecialchars nas views (sem XSS refletido).
  test('shouldEscapeHtmlInTitleAndOptions', async () => {
    const title = `${uniqueTitle('create-escape')} <b>"x"</b> & 'y'`;
    const poll = await createPoll(admin, { title, status: 'ativa', options: ['<i>um</i>', 'dois & três'] });
    const dashboard = await admin.get('/admin/dashboard');
    const html = await dashboard.text();
    expect(html).not.toContain('<b>"x"</b>');
    expect(html).toContain('&lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; &#039;y&#039;');
    const results = await resultsOf(admin, poll.id);
    expect(sortedOptionTexts(results)).toEqual(['<i>um</i>', 'dois & três']);
    expect(await (await admin.get(`/admin/resultados/${poll.id}`)).text()).not.toContain('<i>um</i>');
  });

  // Negativo: título vazio → nada gravado, mensagem de erro, sem redirect.
  test('shouldRejectEmptyTitleWithoutSaving', async () => {
    const rowsBefore = await dashboardRows(admin);
    const response = await postForm(admin, '/admin/salvar', pollFormEntries({ title: '   ', options: ['A', 'B'] }));
    expectNoRedirect(response);
    expect(await response.text()).toContain(VALIDATION_ERROR_TEXT);
    expect((await dashboardRows(admin)).length).toBe(rowsBefore.length);
  });

  // Negativo: 0 ou 1 opção não vazia → nada gravado.
  for (const [label, options] of [
    ['no options', []],
    ['one option', ['Única']],
    ['one option plus blanks', ['Única', '', '  ']],
  ] as const) {
    test(`shouldRejectFewerThanTwoOptionsWithoutSaving ${label}`, async () => {
      const title = uniqueTitle('create-few-options');
      const response = await postForm(admin, '/admin/salvar', pollFormEntries({ title, options: [...options] }));
      expectNoRedirect(response);
      expect(await response.text()).toContain(VALIDATION_ERROR_TEXT);
      expect(await findDashboardRowByTitle(admin, title)).toBeUndefined();
    });
  }

  // Negativo: GET /admin/salvar autenticado (sem POST) → 302 /admin/dashboard, nada gravado.
  test('shouldRedirectGetOnSaveRouteToDashboard', async () => {
    const rowsBefore = await dashboardRows(admin);
    const response = await admin.get('/admin/salvar');
    expectRedirectTo(response, '/admin/dashboard');
    expect((await dashboardRows(admin)).length).toBe(rowsBefore.length);
  });

  // Negativo: mesmo POST sem sessão → 302 /admin/login, nada gravado (reforça EN-05).
  test('shouldRejectAnonymousCreate', async () => {
    const anonymous = await newVisitor();
    try {
      const title = uniqueTitle('create-anonymous');
      const response = await postForm(anonymous, '/admin/salvar', pollFormEntries({ title, options: ['A', 'B'] }));
      expectRedirectTo(response, '/admin/login');
      expect(await findDashboardRowByTitle(admin, title)).toBeUndefined();
    } finally {
      await disposeAll(anonymous);
    }
  });
});
