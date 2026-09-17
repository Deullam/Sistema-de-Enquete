import { test, expect, type APIRequestContext } from '@playwright/test';
import { createPoll, expectedPercent, loginAsAdmin, resultsOf, uniqueTitle } from '../helpers/admin';
import { disposeAll, expectRedirectTo, newVisitor } from '../helpers/httpClient';
import { castVoteAsNewVisitor, voteFormOfNewVisitor } from '../helpers/voting';

/** Id que não existe em `enquetes`. */
const NONEXISTENT_POLL_ID = 999_999_999;

// EN-09 — Administrador consulta os resultados com contagem e percentuais corretos.
test.describe('EN-09 admin results page', () => {
  let admin: APIRequestContext;

  test.beforeAll(async () => {
    admin = await loginAsAdmin();
  });

  test.afterAll(async () => {
    await disposeAll(admin);
  });

  /**
   * Cria uma enquete e registra `distribution[i]` votos na i-ésima opção, cada voto em sessão nova.
   * Devolve o mapa texto da opção → votos esperados.
   */
  async function pollWithVotes(prefix: string, optionTexts: string[], distribution: number[]): Promise<{ id: number; expected: Map<string, number> }> {
    const poll = await createPoll(admin, { title: uniqueTitle(prefix), status: 'ativa', options: optionTexts });
    const form = await voteFormOfNewVisitor(poll.slug!);
    expect(form.options.map((option) => option.text)).toEqual(optionTexts);
    const expected = new Map<string, number>();
    for (const [index, option] of form.options.entries()) {
      for (let vote = 0; vote < distribution[index]; vote += 1) {
        await castVoteAsNewVisitor(poll.id, option.id);
      }
      expected.set(option.text, distribution[index]);
    }
    return { id: poll.id, expected };
  }

  // AC1: contagem exata por opção, percentual = round(votos/total*100, 2), soma = total.
  for (const [label, texts, distribution] of [
    ['5 votes over 3 options (60/40/0)', ['Alfa', 'Beta', 'Gama'], [3, 2, 0]],
    ['3 votes over 2 options (66.67/33.33)', ['Par', 'Ímpar'], [2, 1]],
    ['7 votes over 3 options (57.14/28.57/14.29)', ['Um', 'Dois', 'Três'], [4, 2, 1]],
  ] as const) {
    test(`shouldShowExactCountsAndRoundedPercentages ${label}`, async () => {
      const { id, expected } = await pollWithVotes('results', [...texts], [...distribution]);
      const total = distribution.reduce<number>((sum, votes) => sum + votes, 0);

      const results = await resultsOf(admin, id);
      expect(results.total).toBe(total);
      expect(results.rows).toHaveLength(texts.length);
      expect(results.rows.reduce((sum, row) => sum + row.votes, 0)).toBe(results.total);

      for (const row of results.rows) {
        const votes = expected.get(row.text);
        expect(votes, `opção "${row.text}" inesperada`).toBeDefined();
        expect(row.votes).toBe(votes);
        expect(row.percent).toBe(expectedPercent(votes!, total));
      }

      // ORDER BY total_votos DESC: contagens nunca crescem de uma linha para a seguinte.
      for (let index = 1; index < results.rows.length; index += 1) {
        expect(results.rows[index - 1].votes).toBeGreaterThanOrEqual(results.rows[index].votes);
      }
    });
  }

  // AC2: enquete sem votos → total 0, cada opção "0 voto(s) (0%)", sem divisão por zero.
  // Comportamento atual, registrado: o texto "Ainda não há votos para esta enquete." NÃO aparece,
  // porque o LEFT JOIN devolve uma linha por opção mesmo sem voto (resultados nunca fica vazio
  // para uma enquete com opções); a view só o mostraria para enquete sem nenhuma opção.
  test('shouldShowZeroCountsWithoutDivisionByZeroForPollWithoutVotes', async () => {
    const poll = await createPoll(admin, { title: uniqueTitle('results-empty'), status: 'inativa', options: ['Vazia 1', 'Vazia 2'] });
    const response = await admin.get(`/admin/resultados/${poll.id}`);
    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).not.toMatch(/NAN|INF|Division by zero|Warning:|Fatal error/i);

    const results = await resultsOf(admin, poll.id);
    expect(results.total).toBe(0);
    expect(results.rows).toHaveLength(2);
    for (const row of results.rows) {
      expect(row.votes).toBe(0);
      expect(row.percent).toBe('0');
    }
    expect(html).not.toContain('Ainda não há votos para esta enquete.');
  });

  // Negativo: id inexistente → 404 "Enquete não encontrada.".
  test('shouldReturn404ForUnknownPollResults', async () => {
    const response = await admin.get(`/admin/resultados/${NONEXISTENT_POLL_ID}`);
    expect(response.status()).toBe(404);
    const body = await response.text();
    expect(body).toContain('Enquete não encontrada.');
    expect(body).not.toContain('Total de Votos Registrados');
  });

  // Negativo: sem sessão → 302 /admin/login, sem números na resposta.
  test('shouldRedirectAnonymousResultsToLogin', async () => {
    const poll = await createPoll(admin, { title: uniqueTitle('results-anon'), status: 'ativa', options: ['A', 'B'] });
    const anonymous = await newVisitor();
    try {
      const response = await anonymous.get(`/admin/resultados/${poll.id}`);
      expectRedirectTo(response, '/admin/login');
      expect(await response.text()).not.toContain('Total de Votos Registrados');
    } finally {
      await disposeAll(anonymous);
    }
  });
});
