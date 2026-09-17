/**
 * Parser mínimo, por regex, do HTML que as views PHP da aplicação emitem.
 * Não é um parser genérico: cada função sabe exatamente o trecho de view que lê
 * (EnqueteView, DetalheEnqueteView, dashboard, formEnquete, resultados).
 * Sem dependência extra (Node não tem DOMParser e o projeto não quer pacote a mais).
 */

/** Item da listagem pública (`/enquetes`). */
export interface PollLink {
  /** Slug usado na URL `/enquetes/{slug}`. */
  slug: string;
  /** Título exibido no link. */
  title: string;
}

/** Opção de voto do formulário público (`<input type="radio" name="opcao_id">`). */
export interface VoteOption {
  /** Valor do radio = id da opção no banco. */
  id: number;
  /** Texto exibido ao lado do radio. */
  text: string;
}

/** Formulário de voto da página de detalhe (`/enquetes/{slug}`). */
export interface VoteForm {
  /** Valor do hidden `enquete_id`. */
  pollId: number;
  /** Opções na ordem em que aparecem. */
  options: VoteOption[];
}

/** Linha da tabela do dashboard administrativo. */
export interface DashboardRow {
  /** Id da enquete (primeira coluna). */
  id: number;
  /** Título (segunda coluna). */
  title: string;
  /** Status como o banco guarda: `ativa` | `inativa`. */
  status: string;
}

/** Linha da página de resultados (`/admin/resultados/{id}`). */
export interface ResultRow {
  /** Texto da opção. */
  text: string;
  /** Contagem exibida em "N voto(s)". */
  votes: number;
  /** Percentual exibido entre parênteses, como string crua (ex.: "33.33", "50", "0"). */
  percent: string;
}

/** Página de resultados inteira. */
export interface ResultsPage {
  /** Título da enquete. */
  title: string;
  /** "Total de Votos Registrados". */
  total: number;
  /** Uma linha por opção. */
  rows: ResultRow[];
}

/** Estado do formulário de edição (`/admin/editar/{id}`). */
export interface EditForm {
  /** `action` do form, ex.: `/admin/salvar/7`. */
  action: string;
  /** Valor do input `titulo`. */
  title: string;
  /** Conteúdo do textarea `descricao`. */
  description: string;
  /** Valor da `<option selected>` do select `status`. */
  status: string | undefined;
  /** Valores dos inputs `opcoes[]`, na ordem. */
  options: string[];
}

/**
 * Desfaz as entidades que `htmlspecialchars()` (ENT_QUOTES) produz, para comparar
 * com o texto original enviado nos formulários.
 */
export function decodeHtml(text: string): string {
  return text
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&')
    .trim();
}

/**
 * Extrai os links da listagem pública (EnqueteView.php): `<a href="/enquetes/{slug}">título</a>`.
 * Ignora os links do cabeçalho porque eles não têm o padrão `/enquetes/{slug}`.
 */
export function extractPollLinks(html: string): PollLink[] {
  const links: PollLink[] = [];
  const pattern = /<a href="\/enquetes\/([^"]+)">\s*([\s\S]*?)\s*<\/a>/g;
  for (const match of html.matchAll(pattern)) {
    links.push({ slug: decodeHtml(match[1]), title: decodeHtml(match[2]) });
  }
  return links;
}

/**
 * Extrai o formulário de voto da página de detalhe (DetalheEnqueteView.php).
 * Lança se o form ou o hidden `enquete_id` não existirem — o teste deve falhar aí, claramente.
 */
export function extractVoteForm(html: string): VoteForm {
  const formMatch = /<form action="\/enquetes\/votar" method="POST">([\s\S]*?)<\/form>/.exec(html);
  if (!formMatch) {
    throw new Error('Formulário POST /enquetes/votar não encontrado no HTML');
  }
  const form = formMatch[1];
  const idMatch = /<input type="hidden" name="enquete_id" value="(\d+)">/.exec(form);
  if (!idMatch) {
    throw new Error('Hidden enquete_id não encontrado no formulário de voto');
  }
  const options: VoteOption[] = [];
  const radioPattern = /<input type="radio" name="opcao_id" value="(\d+)" required>\s*([\s\S]*?)\s*<\/label>/g;
  for (const match of form.matchAll(radioPattern)) {
    options.push({ id: Number(match[1]), text: decodeHtml(match[2]) });
  }
  return { pollId: Number(idMatch[1]), options };
}

/**
 * Extrai as linhas da tabela do dashboard (dashboard.php): id, título e status.
 * Devolve lista vazia se a tabela não estiver na página.
 */
export function extractDashboardRows(html: string): DashboardRow[] {
  const rows: DashboardRow[] = [];
  const pattern = /<tr>\s*<td>(\d+)<\/td>\s*<td>([\s\S]*?)<\/td>\s*<td>\s*<span class="status status-([a-z]+)">/g;
  for (const match of html.matchAll(pattern)) {
    rows.push({ id: Number(match[1]), title: decodeHtml(match[2]), status: match[3] });
  }
  return rows;
}

/**
 * Extrai a página de resultados (resultados.php): título, total e uma linha por opção.
 */
export function extractResultsPage(html: string): ResultsPage {
  const titleMatch = /<h2>([\s\S]*?)<\/h2>/.exec(html);
  const totalMatch = /Total de Votos Registrados:<\/strong>\s*(\d+)/.exec(html);
  if (!titleMatch || !totalMatch) {
    throw new Error('Página de resultados sem <h2> de título ou sem "Total de Votos Registrados"');
  }
  const rows: ResultRow[] = [];
  const rowPattern =
    /<span class="opcao-texto">([\s\S]*?)<\/span>\s*<span class="votos-contagem">\s*(\d+) voto\(s\) \(([\d.]+)%\)/g;
  for (const match of html.matchAll(rowPattern)) {
    rows.push({ text: decodeHtml(match[1]), votes: Number(match[2]), percent: match[3] });
  }
  return { title: decodeHtml(titleMatch[1]), total: Number(totalMatch[1]), rows };
}

/**
 * Extrai o formulário de criação/edição (formEnquete.php).
 */
export function extractEditForm(html: string): EditForm {
  const actionMatch = /<form action="([^"]+)" method="POST" class="form-admin">/.exec(html);
  const titleMatch = /name="titulo" required\s*value="([^"]*)"/.exec(html);
  const descriptionMatch = /<textarea id="descricao" name="descricao">([\s\S]*?)<\/textarea>/.exec(html);
  if (!actionMatch || !titleMatch || !descriptionMatch) {
    throw new Error('Formulário de enquete incompleto no HTML (action, titulo ou descricao ausentes)');
  }
  const statusMatch = /<option value="([a-z]+)" selected>/.exec(html);
  const options: string[] = [];
  const optionPattern = /name="opcoes\[\]"\s*value="([^"]*)"/g;
  for (const match of html.matchAll(optionPattern)) {
    options.push(decodeHtml(match[1]));
  }
  return {
    action: actionMatch[1],
    title: decodeHtml(titleMatch[1]),
    description: decodeHtml(descriptionMatch[1]),
    status: statusMatch?.[1],
    options,
  };
}

/**
 * Verdadeiro se o HTML contém alguma tag `<script>` ou referência a arquivo `.js`
 * (promessa do README: a aplicação não usa JavaScript).
 */
export function containsJavaScriptAssets(html: string): boolean {
  return /<script\b/i.test(html) || /\.js["'?\s>]/i.test(html);
}
