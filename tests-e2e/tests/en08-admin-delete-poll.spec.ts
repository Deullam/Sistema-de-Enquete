import { test, expect, type APIRequestContext } from '@playwright/test';
import {
  createPoll,
  dashboardRows,
  findDashboardRowByTitle,
  findPublicSlugByTitle,
  loginAsAdmin,
  resultsOf,
  uniqueTitle,
  type CreatedPoll,
} from '../helpers/admin';
import { disposeAll, expectRedirectTo, newVisitor, postForm } from '../helpers/httpClient';
import { castVoteAsNewVisitor, voteFormOfNewVisitor } from '../helpers/voting';

/** Id que não existe em `enquetes`. */
const NONEXISTENT_POLL_ID = 999_999_999;

// EN-08 — Administrador exclui uma enquete, somente via POST.
test.describe('EN-08 admin deletes a poll only via POST', () => {
  let admin: APIRequestContext;

  test.beforeAll(async () => {
    admin = await loginAsAdmin();
  });

  test.afterAll(async () => {
    await disposeAll(admin);
  });

  /** Enquete ativa nova, com um voto registrado, para exercitar o cascade. */
  async function pollWithOneVote(prefix: string): Promise<CreatedPoll> {
    const poll = await createPoll(admin, { title: uniqueTitle(prefix), status: 'ativa', options: ['Del 1', 'Del 2'] });
    const form = await voteFormOfNewVisitor(poll.slug!);
    await castVoteAsNewVisitor(poll.id, form.options[0].id);
    expect((await resultsOf(admin, poll.id)).total).toBe(1);
    return poll;
  }

  // AC1: POST /admin/excluir/{id} → 302 /admin/dashboard; some do dashboard, da listagem pública
  // e /admin/resultados/{id} passa a 404 (opções e votos vão junto por ON DELETE CASCADE).
  test('shouldDeletePollWithOptionsAndVotesViaPost', async () => {
    const poll = await pollWithOneVote('delete-post');
    const rowsBefore = await dashboardRows(admin);

    const response = await postForm(admin, `/admin/excluir/${poll.id}`, {});
    expectRedirectTo(response, '/admin/dashboard');

    expect((await dashboardRows(admin)).length).toBe(rowsBefore.length - 1);
    expect(await findDashboardRowByTitle(admin, poll.title)).toBeUndefined();
    expect(await findPublicSlugByTitle(admin, poll.title)).toBeUndefined();

    const results = await admin.get(`/admin/resultados/${poll.id}`);
    expect(results.status()).toBe(404);
    expect(await results.text()).toContain('Enquete não encontrada.');

    const edit = await admin.get(`/admin/editar/${poll.id}`);
    expect(edit.status()).toBe(404);

    const visitor = await newVisitor();
    try {
      expect((await visitor.get(`/enquetes/${poll.slug}`)).status()).toBe(404);
    } finally {
      await disposeAll(visitor);
    }
  });

  // Negativo: GET /admin/excluir/{id} (método errado) → 302 /admin/dashboard e NADA excluído.
  test('shouldNotDeleteViaGet', async () => {
    const poll = await pollWithOneVote('delete-get');
    const rowsBefore = await dashboardRows(admin);

    const response = await admin.get(`/admin/excluir/${poll.id}`);
    expectRedirectTo(response, '/admin/dashboard');

    expect((await dashboardRows(admin)).length).toBe(rowsBefore.length);
    expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id });
    expect((await resultsOf(admin, poll.id)).total).toBe(1);
    expect(await findPublicSlugByTitle(admin, poll.title)).toBe(poll.slug);
  });

  // Negativo: sem sessão, POST /admin/excluir/{id} → 302 /admin/login e nada excluído.
  test('shouldNotDeleteWhenAnonymous', async () => {
    const poll = await pollWithOneVote('delete-anonymous');
    const anonymous = await newVisitor();
    try {
      const response = await postForm(anonymous, `/admin/excluir/${poll.id}`, {});
      expectRedirectTo(response, '/admin/login');
    } finally {
      await disposeAll(anonymous);
    }
    expect(await findDashboardRowByTitle(admin, poll.title)).toMatchObject({ id: poll.id });
    expect((await resultsOf(admin, poll.id)).total).toBe(1);
  });

  // Negativo: id inexistente → DELETE afeta 0 linhas, sem erro, 302 /admin/dashboard
  // (comportamento atual: sem feedback de "não encontrado", TODO no próprio controller).
  test('shouldRedirectNormallyWhenDeletingUnknownPoll', async () => {
    const rowsBefore = await dashboardRows(admin);
    const response = await postForm(admin, `/admin/excluir/${NONEXISTENT_POLL_ID}`, {});
    expectRedirectTo(response, '/admin/dashboard');
    expect((await dashboardRows(admin)).length).toBe(rowsBefore.length);
  });

  // Excluir uma enquete não afeta as demais (só o id informado).
  test('shouldDeleteOnlyTheRequestedPoll', async () => {
    const keep = await pollWithOneVote('delete-keep');
    const remove = await createPoll(admin, { title: uniqueTitle('delete-remove'), status: 'inativa', options: ['R1', 'R2'] });

    expectRedirectTo(await postForm(admin, `/admin/excluir/${remove.id}`, {}), '/admin/dashboard');

    expect(await findDashboardRowByTitle(admin, remove.title)).toBeUndefined();
    expect(await findDashboardRowByTitle(admin, keep.title)).toMatchObject({ id: keep.id });
    expect((await resultsOf(admin, keep.id)).total).toBe(1);
  });
});
