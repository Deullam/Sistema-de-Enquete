import { test, expect } from '@playwright/test';
import { loginAsAdmin } from '../helpers/admin';
import {
  disposeAll,
  expectRedirectTo,
  getWithSessionCookie,
  newVisitor,
  sessionCookieOf,
  SESSION_COOKIE_NAME,
} from '../helpers/httpClient';

// EN-10 — Logout encerra de fato a sessão administrativa.
test.describe('EN-10 admin logout ends the session', () => {
  // AC1: GET /admin/logout → 302 /admin/login, cookie de sessão expirado (setcookie no passado).
  test('shouldRedirectToLoginAndExpireSessionCookie', async () => {
    const admin = await loginAsAdmin();
    try {
      const sessionId = await sessionCookieOf(admin);
      expect(sessionId).toBeTruthy();

      const response = await admin.get('/admin/logout');
      expectRedirectTo(response, '/admin/login');

      const setCookie = response.headersArray().filter((header) => header.name.toLowerCase() === 'set-cookie');
      const expired = setCookie.find((header) => header.value.startsWith(`${SESSION_COOKIE_NAME}=`));
      expect(expired, 'esperava Set-Cookie expirando PHPSESSID').toBeDefined();
      expect(expired!.value).toMatch(/PHPSESSID=deleted/i);
      expect(expired!.value).toMatch(/Max-Age=0/i);
      expect(expired!.value).toMatch(/expires=Thu, 01[- ]Jan[- ]1970/i);

      // O jar do cliente honrou a expiração: não há mais cookie de sessão guardado.
      expect(await sessionCookieOf(admin)).toBeUndefined();
    } finally {
      await disposeAll(admin);
    }
  });

  // AC2: o MESMO id de sessão, reenviado manualmente após o logout, não dá mais acesso
  // (session_destroy() no servidor, não só o cookie apagado no cliente).
  test('shouldRejectOldSessionIdAfterLogout', async () => {
    const admin = await loginAsAdmin();
    const probe = await newVisitor();
    try {
      const sessionId = (await sessionCookieOf(admin))!;
      expect((await getWithSessionCookie(probe, '/admin/dashboard', sessionId)).status()).toBe(200);

      expectRedirectTo(await admin.get('/admin/logout'), '/admin/login');

      const afterLogout = await getWithSessionCookie(probe, '/admin/dashboard', sessionId);
      expectRedirectTo(afterLogout, '/admin/login');
      expect(await afterLogout.text()).not.toContain('Gerenciamento de Enquetes');
      expectRedirectTo(await getWithSessionCookie(probe, '/admin/criar', sessionId), '/admin/login');

      // E o contexto original, já sem cookie, também volta ao login.
      expectRedirectTo(await admin.get('/admin/dashboard'), '/admin/login');
    } finally {
      await disposeAll(admin, probe);
    }
  });

  // Após o logout, o cabeçalho volta a oferecer "Login" em vez de "Sair".
  test('shouldShowLoginLinkInsteadOfLogoutAfterLogout', async () => {
    const admin = await loginAsAdmin();
    try {
      expect(await (await admin.get('/enquetes')).text()).toContain('href="/admin/logout"');
      expectRedirectTo(await admin.get('/admin/logout'), '/admin/login');
      const html = await (await admin.get('/enquetes')).text();
      expect(html).toContain('href="/admin/login"');
      expect(html).not.toContain('href="/admin/logout"');
    } finally {
      await disposeAll(admin);
    }
  });

  // Negativo: logout sem sessão autenticada → 302 /admin/login sem erro (idempotente).
  test('shouldRedirectToLoginWhenLoggingOutWithoutSession', async () => {
    const anonymous = await newVisitor();
    try {
      const response = await anonymous.get('/admin/logout');
      expectRedirectTo(response, '/admin/login');
      expect(await response.text()).not.toMatch(/Warning:|Notice:|Fatal error/);
      // Repetir continua inofensivo.
      expectRedirectTo(await anonymous.get('/admin/logout'), '/admin/login');
    } finally {
      await disposeAll(anonymous);
    }
  });
});
