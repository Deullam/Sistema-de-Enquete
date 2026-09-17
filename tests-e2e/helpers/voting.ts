import { expect, type APIRequestContext, type APIResponse } from '@playwright/test';
import { extractVoteForm, type VoteForm } from './html';
import { newVisitor, postForm } from './httpClient';

/** Texto da página de sucesso de voto (mensagem_sucesso.php via EnqueteController::votar). */
export const VOTE_SUCCESS_TEXT = 'Seu voto foi computado com sucesso.';
/** Título e texto da página de voto duplicado. */
export const VOTE_DUPLICATE_TITLE = 'Voto Duplicado';
export const VOTE_DUPLICATE_TEXT = 'Você já participou desta enquete.';
/** Texto da página de erro quando salvarVoto() devolve false (ex.: FK violada). */
export const VOTE_FAILURE_TEXT = 'Não foi possível registrar seu voto no momento. Tente novamente mais tarde.';

/**
 * Abre /enquetes/{slug} e devolve o formulário de voto parseado (enquete_id + opções).
 */
export async function voteFormOf(ctx: APIRequestContext, slug: string): Promise<VoteForm> {
  const response = await ctx.get(`/enquetes/${slug}`);
  expect(response.status(), `GET /enquetes/${slug}`).toBe(200);
  return extractVoteForm(await response.text());
}

/**
 * Envia POST /enquetes/votar com os ids informados, mantendo os cookies do contexto.
 * Não afirma nada: cada teste decide o que espera da resposta.
 */
export async function castVote(ctx: APIRequestContext, pollId: number, optionId: number): Promise<APIResponse> {
  return postForm(ctx, '/enquetes/votar', { enquete_id: String(pollId), opcao_id: String(optionId) });
}

/**
 * Vota com um visitante NOVO (sessão nova) e afirma a página de sucesso.
 * Usado para acumular N votos numa enquete de forma determinística.
 */
export async function castVoteAsNewVisitor(pollId: number, optionId: number): Promise<void> {
  const visitor = await newVisitor();
  try {
    const response = await castVote(visitor, pollId, optionId);
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain(VOTE_SUCCESS_TEXT);
  } finally {
    await visitor.dispose();
  }
}

/**
 * Igual a voteFormOf(), mas com um visitante descartável (sessão nova, descartada ao fim).
 */
export async function voteFormOfNewVisitor(slug: string): Promise<VoteForm> {
  const visitor = await newVisitor();
  try {
    return await voteFormOf(visitor, slug);
  } finally {
    await visitor.dispose();
  }
}
