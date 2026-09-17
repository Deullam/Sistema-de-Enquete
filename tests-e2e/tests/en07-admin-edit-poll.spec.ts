import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  createPoll,
  findDashboardRowByTitle,
  findPublicSlugByTitle,
  loginAsAdmin,
  pollFormEntries,
  resultsOf,
  sortedOptionTexts,
  uniqueTitle,
  type CreatedPoll,
} from '../helpers/admin';
import { extractEditForm } from '../helpers/html';
import { disposeAll, expectNoRedirect, expectRedirectTo, newVisitor, postForm } from '../helpers/httpClient';
import { castVoteAsNewVisitor, voteFormOfNewVisitor } from '../helpers/voting';

/** Mensagem que AdminController::salvar() ecoa quando a validação falha. */
const VALIDATION_ERROR_TEXT = 'Erro: O título é obrigatório e a enquete deve ter pelo menos 2 opções.';
/** Id que não existe em `enquetes`. */
const NONEXISTENT_POLL_ID = 999_999_999;

// EN-07 — Administrador edita uma enquete existente.
test.describe('EN-07 admin edits a poll', () => {
  let admin: APIRequestContext;

  test.beforeAll(async () => {
    admin = await loginAsAdmin();
  });

  test.afterAll(async () => {
    await disposeAll(admin);
  });

  /** Cria uma enquete nova só para o teste corrente, para cada caso partir de estado conhecido. */
  async function freshPoll(prefix: string, status: 'ativa' | 'inativa' = 'ativa'): Promise<CreatedPoll> {
    return createPoll(admin, {
      title: uniqueTitle(prefix),
      description: `Descrição original ${prefix}`,
      status,
      options: ['Original 1', 'Original 2'],
    });
  }

  // AC1: GET /admin/editar/{id} → 200 com o formulário preenchido.
  test('shouldPrefillEditFormWithCurrentData', async () => {
    const poll = await freshPoll('edit-prefill', 'inativa');
    const response = await admin.get(`/admin/editar/${poll.id}`);
    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<h1>Editar Enquete</h1>');
    const form = extractEditForm(html);
    expect(form.action).toBe(`/admin/salvar/${poll.id}`);
    expect(form.title).toBe(poll.title);
    expect(form.description).toBe('Descrição original edit-prefill');
    expect(form.status).toBe('inativa');
    expect(form.options).toEqual(['Original 1', 'Original 2']);
  });

  // AC2: POST /admin/salvar/{id} válido → enquete atualizada, opções antigas apagadas e novas
  // inseridas; dashboard, formulário e resultados refletem os dados novos.
  test('shouldUpdatePollAndReplaceOptions', async () => {
    const poll = await freshPoll('edit-update');
    const oldForm = await voteFormOfNewVisitor(poll.slug!);
    const oldOptionIds = oldForm.options.map((option) => option.id);
    // Um voto na opção antiga, para provar que ele some junto com a opção (ON DELETE CASCADE).
    await castVoteAsNewVisitor(poll.id, oldForm.options[0].id);
    expect((await resultsOf(admin, poll.id)).total).toBe(1);

    const newTitle = uniqueTitle('edit-updated');
    const response = await postForm(
      admin,
      `/admin/salvar/${poll.id}`,
      pollFormEntries({ title: newTitle, description: 'Descrição nova', status: 'ativa', options: ['Nova A', 'Nova B', 'Nova C'] }),
    );
    expectRedirectTo(response, '/admin/dashboard');

    expect(await findDashboardRowByTitle(admin, poll.title)).toBeUndefined();
    expect(await findDashboardRowByTitle(admin, newTitle)).toMatchObject({ id: poll.id, status: 'ativa' });

    const form = extractEditForm(await (await admin.get(`/admin/editar/${poll.id}`)).text());
    expect(form.title).toBe(newTitle);
    expect(form.description).toBe('Descrição nova');
    expect(form.options).toEqual(['Nova A', 'Nova B', 'Nova C']);

    const results = await resultsOf(admin, poll.id);
    expect(results.title).toBe(newTitle);
    expect(sortedOptionTexts(results)).toEqual(['Nova A', 'Nova B', 'Nova C']);
    // Opções antigas foram apagadas (e o voto nelas, em cascata): o total volta a zero.
    expect(results.total).toBe(0);

    // Comportamento atual, registrado: a edição recalcula o slug SEM o sufixo uniqid()
    // (slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', titulo))), então a URL pública muda.
    const newSlug = await findPublicSlugByTitle(admin, newTitle);
    expect(newSlug).toBe(newTitle.toLowerCase().replace(/[^a-z0-9-]+/g, '-'));
    expect(newSlug).not.toBe(poll.slug);
    const newForm = await voteFormOfNewVisitor(newSlug!);
    expect(newForm.pollId).toBe(poll.id);
    expect(newForm.options.map((option) => option.text)).toEqual(['Nova A', 'Nova B', 'Nova C']);
    for (const option of newForm.options) {
      expect(oldOptionIds).not.toContain(option.id);
    }
  });

  // AC2: mudar status para inativa tira a enquete de /enquetes sem tirá-la do dashboard.
  test('shouldHidePollFromPublicListingWhenSetInactive', async () => {
    const poll = await freshPoll('edit-deactivate');
    expect(poll.slug).toBeDefined();
    const response = await postForm(
      admin,
      `/admin/salvar/${poll.id}`,
      pollFormEntries({ title: poll.title, status: 'inativa', options: ['Original 1', 'Original 2'] }),
    );
    expectRedirectTo(response, '/admin/dashboard');
    expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id, status: 'inativa' });
    expect(await findPublicSlugByTitle(admin, poll.title)).toBeUndefined();
  });

  // Negativo: id inexistente → 404 "Enquete não encontrada.".
  test('shouldReturn404WhenEditingUnknownPoll', async () => {
    const response = await admin.get(`/admin/editar/${NONEXISTENT_POLL_ID}`);
    expect(response.status()).toBe(404);
    const body = await response.text();
    expect(body).toContain('Enquete não encontrada.');
    expect(body).not.toContain('form-admin');
  });

  // Negativo: título vazio ou <2 opções → nada alterado, mensagem de erro, sem redirect.
  for (const [label, input] of [
    ['empty title', { title: '', options: ['X', 'Y'] }],
    ['one option', { title: 'Título válido', options: ['X'] }],
    ['only blank options', { title: 'Título válido', options: ['', '   '] }],
  ] as const) {
    test(`shouldRejectInvalidUpdateWithoutChanging ${label}`, async () => {
      const poll = await freshPoll('edit-invalid');
      const response = await postForm(
        admin,
        `/admin/salvar/${poll.id}`,
        pollFormEntries({ title: input.title, status: 'inativa', options: [...input.options] }),
      );
      expectNoRedirect(response);
      expect(await response.text()).toContain(VALIDATION_ERROR_TEXT);

      expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id, status: 'ativa' });
      const form = extractEditForm(await (await admin.get(`/admin/editar/${poll.id}`)).text());
      expect(form.title).toBe(poll.title);
      expect(form.options).toEqual(['Original 1', 'Original 2']);
      expect(await findPublicSlugByTitle(admin, poll.title)).toBe(poll.slug);
    });
  }

  // Negativo: GET /admin/salvar/{id} autenticado (sem POST) → 302 /admin/dashboard, nada alterado.
  test('shouldRedirectGetOnSaveWithIdToDashboard', async () => {
    const poll = await freshPoll('edit-get');
    expectRedirectTo(await admin.get(`/admin/salvar/${poll.id}`), '/admin/dashboard');
    expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id, status: 'ativa' });
  });

  // Negativo: sem sessão, GET /admin/editar/{id} e POST /admin/salvar/{id} → 302 /admin/login, nada alterado.
  test('shouldRejectAnonymousEdit', async () => {
    const poll = await freshPoll('edit-anonymous');
    const anonymous = await newVisitor();
    try {
      expectRedirectTo(await anonymous.get(`/admin/editar/${poll.id}`), '/admin/login');
      const hijacked = uniqueTitle('edit-hijacked');
      const response = await postForm(
        anonymous,
        `/admin/salvar/${poll.id}`,
        pollFormEntries({ title: hijacked, options: ['X', 'Y'] }),
      );
      expectRedirectTo(response, '/admin/login');
      expect(await findDashboardRowByTitle(admin, hijacked)).toBeUndefined();
      expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id });
    } finally {
      await disposeAll(anonymous);
    }
  });
});
