import { test, expect, type APIRequestContext } from '@playwright/test';
import { extractVoteForm } from '../helpers/html';
import { disposeAll, newVisitor } from '../helpers/httpClient';

/** Enquete ativa do seed com suas opções, na ordem de inserção do database.sql. */
const SEED_ACTIVE_POLL = {
  slug: 'linguagem-programacao',
  title: 'Melhor Linguagem de Programação',
  description: 'Qual linguagem de programação você prefere?',
  options: ['PHP', 'JavaScript', 'Python'],
};
const SEED_INACTIVE_SLUG = 'ambiente-trabalho';

// EN-02 — Visitante abre o detalhe de uma enquete pelo slug.
test.describe('EN-02 poll detail by slug', () => {
  let visitor: APIRequestContext;

  test.beforeEach(async () => {
    visitor = await newVisitor();
  });

  test.afterEach(async () => {
    await disposeAll(visitor);
  });

  // AC1: slug ativo → 200 com título, descrição e <form> POST /enquetes/votar
  // (hidden enquete_id + um radio opcao_id por opção cadastrada).
  test('shouldRenderActivePollWithVoteForm', async () => {
    const response = await visitor.get(`/enquetes/${SEED_ACTIVE_POLL.slug}`);
    expect(response.status()).toBe(200);

    const html = await response.text();
    expect(html).toContain(`<title>${SEED_ACTIVE_POLL.title}</title>`);
    expect(html).toContain(`<h1>${SEED_ACTIVE_POLL.title}</h1>`);
    expect(html).toContain(`<p>${SEED_ACTIVE_POLL.description}</p>`);
    expect(html).toContain('<form action="/enquetes/votar" method="POST">');

    const form = extractVoteForm(html);
    expect(form.pollId).toBeGreaterThan(0);
    expect(form.options.map((option) => option.text)).toEqual(SEED_ACTIVE_POLL.options);
    const ids = form.options.map((option) => option.id);
    expect(new Set(ids).size).toBe(ids.length);
    for (const id of ids) {
      expect(id).toBeGreaterThan(0);
    }
    expect(html).toContain('<button type="submit">Votar</button>');
  });

  // Negativo: slug de enquete inativa → 404 (findBySlugWithOptions filtra status='ativa').
  test('shouldReturn404ForInactivePollSlug', async () => {
    const response = await visitor.get(`/enquetes/${SEED_INACTIVE_SLUG}`);
    expect(response.status()).toBe(404);
    const html = await response.text();
    expect(html).toContain('404 - Enquete não encontrada');
    expect(html).not.toContain('/enquetes/votar');
    expect(html).not.toContain('Ambiente de Trabalho Preferido');
  });

  // Negativo: slug inexistente → 404.
  test('shouldReturn404ForUnknownPollSlug', async () => {
    const response = await visitor.get('/enquetes/nao-existe');
    expect(response.status()).toBe(404);
    const html = await response.text();
    expect(html).toContain('404 - Enquete não encontrada');
    expect(html).not.toContain('/enquetes/votar');
  });
});
