import { test, expect, type APIRequestContext } from '@playwright/test';
import { createPoll, loginAsAdmin, resultsOf, uniqueTitle, type CreatedPoll } from '../helpers/admin';
import { disposeAll, expectRedirectTo, newVisitor, postForm } from '../helpers/httpClient';
import {
  castVote,
  voteFormOf,
  VOTE_DUPLICATE_TEXT,
  VOTE_DUPLICATE_TITLE,
  VOTE_FAILURE_TEXT,
  VOTE_SUCCESS_TEXT,
} from '../helpers/voting';

/** Id de opção que não existe em `opcoes` (viola a FK de `votos`). */
const NONEXISTENT_OPTION_ID = 999_999_999;

// EN-03 — Visitante vota; voto duplicado é bloqueado por sessão, não por IP.
test.describe('EN-03 voting and per-session duplicate block', () => {
  let admin: APIRequestContext;
  let poll: CreatedPoll;

  // Enquete própria desta suíte, para as contagens não dependerem do seed nem de outros specs.
  test.beforeAll(async () => {
    admin = await loginAsAdmin();
    poll = await createPoll(admin, {
      title: uniqueTitle('voting'),
      description: 'Enquete usada pelos testes de voto',
      status: 'ativa',
      options: ['Opção A', 'Opção B', 'Opção C'],
    });
    expect(poll.slug).toBeDefined();
  });

  test.afterAll(async () => {
    await disposeAll(admin);
  });

  /** Contagem atual de votos da opção `optionId`, lida da página de resultados. */
  async function votesOf(optionText: string): Promise<number> {
    const results = await resultsOf(admin, poll.id);
    const row = results.rows.find((candidate) => candidate.text === optionText);
    if (!row) {
      throw new Error(`Opção "${optionText}" não está na página de resultados`);
    }
    return row.votes;
  }

  // AC1 + AC2 + AC3 em sequência numa mesma enquete, com sessões controladas.
  test('shouldCountFirstVoteBlockSecondInSameSessionAndAllowNewSession', async () => {
    const firstVisitor = await newVisitor();
    const secondVisitor = await newVisitor();
    try {
      const form = await voteFormOf(firstVisitor, poll.slug!);
      expect(form.pollId).toBe(poll.id);
      expect(form.options).toHaveLength(3);
      const [optionA, optionB] = form.options;

      const totalBefore = (await resultsOf(admin, poll.id)).total;
      const votesABefore = await votesOf(optionA.text);
      const votesBBefore = await votesOf(optionB.text);

      // AC1: primeiro voto da sessão → página de sucesso, +1 na opção escolhida.
      const first = await castVote(firstVisitor, form.pollId, optionA.id);
      expect(first.status()).toBe(200);
      const firstHtml = await first.text();
      expect(firstHtml).toContain('<h1>Obrigado por Votar!</h1>');
      expect(firstHtml).toContain(VOTE_SUCCESS_TEXT);
      expect(await votesOf(optionA.text)).toBe(votesABefore + 1);
      expect((await resultsOf(admin, poll.id)).total).toBe(totalBefore + 1);

      // AC2: mesma sessão, mesma enquete, opção diferente → "Voto Duplicado", nada gravado.
      const duplicate = await castVote(firstVisitor, form.pollId, optionB.id);
      expect(duplicate.status()).toBe(200);
      const duplicateHtml = await duplicate.text();
      expect(duplicateHtml).toContain(`<h1>${VOTE_DUPLICATE_TITLE}</h1>`);
      expect(duplicateHtml).toContain(VOTE_DUPLICATE_TEXT);
      expect(duplicateHtml).not.toContain(VOTE_SUCCESS_TEXT);
      expect(await votesOf(optionB.text)).toBe(votesBBefore);
      expect((await resultsOf(admin, poll.id)).total).toBe(totalBefore + 1);

      // AC3: cliente sem os cookies (sessão nova), mesmo IP → aceito de novo.
      // Comportamento documentado no README: o IP é gravado, mas não bloqueia repetição.
      const fresh = await castVote(secondVisitor, form.pollId, optionB.id);
      expect(fresh.status()).toBe(200);
      expect(await fresh.text()).toContain(VOTE_SUCCESS_TEXT);
      expect(await votesOf(optionB.text)).toBe(votesBBefore + 1);
      expect((await resultsOf(admin, poll.id)).total).toBe(totalBefore + 2);
    } finally {
      await disposeAll(firstVisitor, secondVisitor);
    }
  });

  // Negativo: corpo sem opcao_id ou sem enquete_id → 302 /enquetes, nada gravado.
  for (const [label, body] of [
    ['missing opcao_id', { enquete_id: 'poll' }],
    ['missing enquete_id', { opcao_id: 'option' }],
    ['empty body', {}],
  ] as const) {
    test(`shouldRedirectToListingWithoutSavingWhen ${label}`, async () => {
      const visitor = await newVisitor();
      try {
        const form = await voteFormOf(visitor, poll.slug!);
        const totalBefore = (await resultsOf(admin, poll.id)).total;

        const fields: Record<string, string> = {};
        if ('enquete_id' in body) fields.enquete_id = String(form.pollId);
        if ('opcao_id' in body) fields.opcao_id = String(form.options[0].id);

        const response = await postForm(visitor, '/enquetes/votar', fields);
        expectRedirectTo(response, '/enquetes');
        expect((await resultsOf(admin, poll.id)).total).toBe(totalBefore);
      } finally {
        await disposeAll(visitor);
      }
    });
  }

  // Negativo: GET /enquetes/votar (sem POST) → 302 /enquetes.
  test('shouldRedirectGetOnVoteRouteToListing', async () => {
    const visitor = await newVisitor();
    try {
      const response = await visitor.get('/enquetes/votar');
      expectRedirectTo(response, '/enquetes');
    } finally {
      await disposeAll(visitor);
    }
  });

  // Negativo: opcao_id inexistente (FK) → página de erro genérica, nada gravado,
  // e a enquete NÃO fica marcada como votada na sessão (voto válido em seguida é aceito).
  test('shouldShowGenericErrorForUnknownOptionAndKeepSessionUnmarked', async () => {
    const visitor = await newVisitor();
    try {
      const form = await voteFormOf(visitor, poll.slug!);
      const totalBefore = (await resultsOf(admin, poll.id)).total;

      const failed = await castVote(visitor, form.pollId, NONEXISTENT_OPTION_ID);
      expect(failed.status()).toBe(200);
      const failedHtml = await failed.text();
      expect(failedHtml).toContain('<h1>Erro Inesperado</h1>');
      expect(failedHtml).toContain(VOTE_FAILURE_TEXT);
      expect(failedHtml).not.toContain(VOTE_SUCCESS_TEXT);
      // Sem vazamento de detalhes do banco na tela.
      expect(failedHtml).not.toMatch(/SQLSTATE|PDOException|foreign key/i);
      expect((await resultsOf(admin, poll.id)).total).toBe(totalBefore);

      const retry = await castVote(visitor, form.pollId, form.options[2].id);
      expect(retry.status()).toBe(200);
      expect(await retry.text()).toContain(VOTE_SUCCESS_TEXT);
      expect((await resultsOf(admin, poll.id)).total).toBe(totalBefore + 1);
    } finally {
      await disposeAll(visitor);
    }
  });

  // BUG (registrado em findings): EnqueteController::votar() não confere se opcao_id pertence
  // ao enquete_id enviado. Hoje o voto é gravado na opção da OUTRA enquete e o enquete_id
  // informado é marcado como votado na sessão — o que permite votar várias vezes na mesma
  // opção trocando apenas o enquete_id. Comportamento esperado: recusar sem gravar.
  test.fixme('shouldRejectOptionThatDoesNotBelongToSubmittedPoll', async () => {
    const otherPoll = await createPoll(admin, {
      title: uniqueTitle('voting-other'),
      status: 'ativa',
      options: ['Outra 1', 'Outra 2'],
    });
    const visitor = await newVisitor();
    try {
      const otherForm = await voteFormOf(visitor, otherPoll.slug!);
      const otherTotalBefore = (await resultsOf(admin, otherPoll.id)).total;

      const response = await castVote(visitor, poll.id, otherForm.options[0].id);
      expect(await response.text()).not.toContain(VOTE_SUCCESS_TEXT);
      expect((await resultsOf(admin, otherPoll.id)).total).toBe(otherTotalBefore);
    } finally {
      await disposeAll(visitor);
    }
  });
});
