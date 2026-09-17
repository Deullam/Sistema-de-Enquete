import { test, expect, type APIRequestContext } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_USER, readSeedAdminPasswordHash } from '../helpers/admin';
import { extractDashboardRows } from '../helpers/html';
import { disposeAll, expectNoRedirect, expectRedirectTo, newVisitor, postForm, sessionCookieOf } from '../helpers/httpClient';

/** Mensagem exibida pela view de login quando a autenticação falha. */
const LOGIN_ERROR_TEXT = 'Usuário ou senha inválidos.';

// EN-04 — Administrador faz login com senha correta (usuário ou e-mail) e é rejeitado
// em qualquer outro caso. Rede de regressão do commit "remove plaintext password fallback".
test.describe('EN-04 admin login', () => {
  let visitor: APIRequestContext;

  test.beforeEach(async () => {
    visitor = await newVisitor();
  });

  test.afterEach(async () => {
    await disposeAll(visitor);
  });

  /** Afirma que a tentativa foi recusada: 200 na própria página de login, sem sessão criada. */
  async function expectLoginRejected(login: string, password: string): Promise<string> {
    const response = await postForm(visitor, '/admin/autenticar', { nome_usuario: login, senha: password });
    expectNoRedirect(response);
    const html = await response.text();
    expect(html).toContain(LOGIN_ERROR_TEXT);
    expect(html).toContain('<form action="/admin/autenticar" method="POST"');
    expect(html).not.toContain('Gerenciamento de Enquetes');
    expect(html).not.toContain('href="/admin/logout"');

    // Sem usuario_id na sessão: a rota protegida continua redirecionando para o login.
    const dashboard = await visitor.get('/admin/dashboard');
    expectRedirectTo(dashboard, '/admin/login');
    return html;
  }

  test('shouldRenderLoginForm', async () => {
    const response = await visitor.get('/admin/login');
    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<h1>Login do Painel Administrativo</h1>');
    expect(html).toContain('name="nome_usuario"');
    expect(html).toContain('type="password" id="senha" name="senha"');
    expect(html).not.toContain(LOGIN_ERROR_TEXT);
  });

  // AC1: usuário + senha corretos → 302 /admin/dashboard, sessão criada.
  test('shouldLoginWithUsernameAndRedirectToDashboard', async () => {
    const response = await postForm(visitor, '/admin/autenticar', { nome_usuario: ADMIN_USER, senha: ADMIN_PASSWORD });
    expectRedirectTo(response, '/admin/dashboard');
    expect(await sessionCookieOf(visitor)).toBeTruthy();

    // AC3: dashboard na mesma sessão → 200 com TODAS as enquetes (ativas e inativas).
    const dashboard = await visitor.get('/admin/dashboard');
    expect(dashboard.status()).toBe(200);
    const html = await dashboard.text();
    expect(html).toContain('<h1>Gerenciamento de Enquetes</h1>');
    expect(html).toContain('href="/admin/logout"');
    const rows = extractDashboardRows(html);
    const seedTitles = rows.map((row) => `${row.title}|${row.status}`);
    expect(seedTitles).toContain('Atividade Favorita de Fim de Semana|ativa');
    expect(seedTitles).toContain('Melhor Linguagem de Programação|ativa');
    expect(seedTitles).toContain('Ambiente de Trabalho Preferido|inativa');
  });

  // AC2: e-mail no campo nome_usuario, senha correta → também aceito.
  test('shouldLoginWithEmailInsteadOfUsername', async () => {
    const response = await postForm(visitor, '/admin/autenticar', { nome_usuario: ADMIN_EMAIL, senha: ADMIN_PASSWORD });
    expectRedirectTo(response, '/admin/dashboard');
    const dashboard = await visitor.get('/admin/dashboard');
    expect(dashboard.status()).toBe(200);
    expect(await dashboard.text()).toContain('<h1>Gerenciamento de Enquetes</h1>');
  });

  // Negativo: senha errada → recusado, sem redirect, sem sessão.
  test('shouldRejectWrongPassword', async () => {
    await expectLoginRejected(ADMIN_USER, 'senha-errada'); // gitleaks:allow
  });

  // Negativo (regressão principal): o hash bcrypt da coluna `senha` enviado como senha
  // NÃO autentica — password_verify(hash, hash) é false e não há comparação direta de string.
  test('shouldRejectHashSubmittedAsPassword', async () => {
    const storedHash = readSeedAdminPasswordHash();
    expect(storedHash).toMatch(/^\$2[aby]\$\d{2}\$/);
    await expectLoginRejected(ADMIN_USER, storedHash);
    await expectLoginRejected(ADMIN_EMAIL, storedHash);
  });

  // Negativo: senha vazia → recusado.
  test('shouldRejectEmptyPassword', async () => {
    await expectLoginRejected(ADMIN_USER, '');
  });

  // Negativo: usuário inexistente → recusado sem 500 e com a MESMA resposta da senha errada
  // (não revela se o usuário existe).
  test('shouldRejectUnknownUserWithSameResponseAsWrongPassword', async () => {
    const unknownUserHtml = await expectLoginRejected('nao-existe-' + Date.now(), 'qualquer'); // gitleaks:allow
    const wrongPasswordHtml = await expectLoginRejected(ADMIN_USER, 'senha-errada'); // gitleaks:allow
    expect(unknownUserHtml).toBe(wrongPasswordHtml);
  });

  // Negativo: GET /admin/autenticar (sem POST) → 302 /admin/login.
  test('shouldRedirectGetOnAuthenticateRouteToLogin', async () => {
    const response = await visitor.get('/admin/autenticar');
    expectRedirectTo(response, '/admin/login');
  });

  // Negativo: campos ausentes no POST → recusado (defaults para string vazia), sem 500.
  test('shouldRejectPostWithoutCredentialFields', async () => {
    const response = await postForm(visitor, '/admin/autenticar', {});
    expectNoRedirect(response);
    expect(await response.text()).toContain(LOGIN_ERROR_TEXT);
    expectRedirectTo(await visitor.get('/admin/dashboard'), '/admin/login');
  });
});
