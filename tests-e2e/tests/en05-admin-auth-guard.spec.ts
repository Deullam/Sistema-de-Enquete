import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  createPoll,
  findDashboardRowByTitle,
  loginAsAdmin,
  pollFormEntries,
  uniqueTitle,
  type CreatedPoll,
} from '../helpers/admin';
import { disposeAll, expectRedirectTo, newVisitor, postForm } from '../helpers/httpClient';

/** Caminhos que tentam alcançar métodos não-públicos do controller pela URL. */
const NON_PUBLIC_ACTION_PATHS = [
  '/admin/view',
  '/admin/view/features/admin/views/dashboard',
  '/admin/verificarLogin',
  '/admin/__construct',
];

// EN-05 — Rotas do /admin exigem sessão; não há atalho para renderizar view sem controller.
// Rede de regressão do commit "fix(router): dispatch only public controller actions".
test.describe('EN-05 admin routes require an authenticated session', () => {
  let admin: APIRequestContext;
  let anonymous: APIRequestContext;
  let poll: CreatedPoll;

  test.beforeAll(async () => {
    admin = await loginAsAdmin();
    poll = await createPoll(admin, {
      title: uniqueTitle('guard'),
      status: 'inativa',
      options: ['Guard 1', 'Guard 2'],
    });
  });

  test.beforeEach(async () => {
    anonymous = await newVisitor();
  });

  test.afterEach(async () => {
    await disposeAll(anonymous);
  });

  test.afterAll(async () => {
    await disposeAll(admin);
  });

  // AC1: GET /admin/dashboard sem sessão → 302 /admin/login, sem renderizar a tabela.
  test('shouldRedirectAnonymousDashboardToLoginWithoutRenderingIt', async () => {
    const response = await anonymous.get('/admin/dashboard');
    expectRedirectTo(response, '/admin/login');
    const body = await response.text();
    expect(body).not.toContain('Gerenciamento de Enquetes');
    expect(body).not.toContain('tabela-admin');
  });

  // AC2: cada rota protegida por GET redireciona sem executar a ação.
  for (const path of ['/admin', '/admin/criar', '/admin/editar/POLL_ID', '/admin/resultados/POLL_ID', '/admin/excluir/POLL_ID', '/admin/salvar']) {
    test(`shouldRedirectAnonymousGetToLogin ${path}`, async () => {
      const response = await anonymous.get(path.replace('POLL_ID', String(poll.id)));
      expectRedirectTo(response, '/admin/login');
      const body = await response.text();
      expect(body).not.toContain('form-admin');
      expect(body).not.toContain('Total de Votos Registrados');
      expect(body).not.toContain(poll.title);
    });
  }

  // AC2: POST /admin/salvar sem sessão → 302 /admin/login e NADA gravado.
  test('shouldNotCreatePollWhenAnonymousPostsToSave', async () => {
    const title = uniqueTitle('guard-create');
    const response = await postForm(anonymous, '/admin/salvar', pollFormEntries({ title, options: ['X', 'Y'] }));
    expectRedirectTo(response, '/admin/login');
    expect(await findDashboardRowByTitle(admin, title)).toBeUndefined();
  });

  // AC2: POST /admin/salvar/{id} sem sessão → 302 /admin/login e enquete inalterada.
  test('shouldNotUpdatePollWhenAnonymousPostsToSaveWithId', async () => {
    const hijackedTitle = uniqueTitle('guard-edit');
    const response = await postForm(
      anonymous,
      `/admin/salvar/${poll.id}`,
      pollFormEntries({ title: hijackedTitle, status: 'ativa', options: ['X', 'Y'] }),
    );
    expectRedirectTo(response, '/admin/login');
    const row = await findDashboardRowByTitle(admin, poll.title);
    expect(row).toMatchObject({ id: poll.id, status: 'inativa' });
    expect(await findDashboardRowByTitle(admin, hijackedTitle)).toBeUndefined();
  });

  // AC2: POST /admin/excluir/{id} sem sessão → 302 /admin/login e enquete continua lá.
  test('shouldNotDeletePollWhenAnonymousPostsToDelete', async () => {
    const response = await postForm(anonymous, `/admin/excluir/${poll.id}`, {});
    expectRedirectTo(response, '/admin/login');
    expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id });
  });

  // AC3: sessão autenticada que passou por logout volta a ser redirecionada.
  test('shouldRedirectToLoginAgainAfterLogout', async () => {
    const session = await loginAsAdmin();
    try {
      expect((await session.get('/admin/dashboard')).status()).toBe(200);
      expectRedirectTo(await session.get('/admin/logout'), '/admin/login');
      expectRedirectTo(await session.get('/admin/dashboard'), '/admin/login');
      expectRedirectTo(await session.get(`/admin/editar/${poll.id}`), '/admin/login');
    } finally {
      await disposeAll(session);
    }
  });

  // Negativo (regressão do bypass): métodos protected/private/mágicos NUNCA são despachados,
  // com ou sem sessão — 404 "Método não encontrado", sem renderizar view alguma.
  for (const path of NON_PUBLIC_ACTION_PATHS) {
    test(`shouldReturn404ForNonPublicActionWithoutSession ${path}`, async () => {
      const response = await anonymous.get(path);
      expect(response.status()).toBe(404);
      const body = await response.text();
      expect(body).toContain('<h1>Erro 404</h1>');
      expect(body).toContain('Método não encontrado');
      expect(body).not.toContain('Gerenciamento de Enquetes');
      expect(body).not.toContain('<table');
      expect(body).not.toContain('tabela-admin');
    });

    test(`shouldReturn404ForNonPublicActionWithSession ${path}`, async () => {
      const response = await admin.get(path);
      expect(response.status()).toBe(404);
      const body = await response.text();
      expect(body).toContain('Método não encontrado');
      expect(body).not.toContain('Gerenciamento de Enquetes');
    });
  }

  // Negativo: POST também não alcança view() (o Router não distingue método HTTP para isso).
  test('shouldReturn404ForNonPublicActionViaPost', async () => {
    const response = await postForm(anonymous, '/admin/view/features/admin/views/dashboard', {});
    expect(response.status()).toBe(404);
    expect(await response.text()).toContain('Método não encontrado');
  });
});
