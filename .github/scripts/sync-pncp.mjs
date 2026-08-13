const cities = [
  ['palhoca', '4211900'],
  ['biguacu', '4202305'],
  ['governador-celso-ramos', '4206009'],
  ['tijucas', '4218004'],
  ['santo-amaro-da-imperatriz', '4215703'],
  ['antonio-carlos', '4201208'],
];

const apiKey = process.env.APP_API_KEY ?? '';
const siteApiUrl = process.env.SITE_API_URL ?? '';
if (!apiKey || !siteApiUrl) {
  throw new Error('Configuracao protegida incompleta para sincronizar licitacoes.');
}

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

async function waitForReceiver() {
  const receiverUrl = new URL(siteApiUrl);
  receiverUrl.searchParams.set('acao', 'receber-pncp');
  for (let attempt = 1; attempt <= 30; attempt += 1) {
    try {
      const response = await fetch(receiverUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-API-KEY': apiKey,
          'User-Agent': 'LemeGovLicitacoes/3.0 (+https://lemeinformatica.com.br/gov/)',
        },
        body: JSON.stringify({ cidade: '__healthcheck__', dados: [] }),
        signal: AbortSignal.timeout(15_000),
      });
      if (response.status === 422) return;
    } catch {}
    await wait(10_000);
  }
  throw new Error('A API receptora de licitacoes nao ficou pronta dentro do prazo.');
}

async function requestJson(url, options = {}, label = 'requisicao') {
  let lastError = 'resposta indisponivel';
  for (let attempt = 1; attempt <= 6; attempt += 1) {
    try {
      const response = await fetch(url, {
        ...options,
        headers: {
          Accept: 'application/json',
          'User-Agent': 'LemeGovLicitacoes/3.0 (+https://lemeinformatica.com.br/gov/)',
          ...(options.headers ?? {}),
        },
        signal: AbortSignal.timeout(30_000),
      });
      const body = await response.text();
      if (response.ok) {
        const payload = JSON.parse(body);
        if (payload && typeof payload === 'object') return payload;
        throw new Error('JSON invalido');
      }
      lastError = `HTTP ${response.status}: ${body.slice(0, 240)}`;
      if (response.status !== 429 && response.status < 500) break;
    } catch (error) {
      lastError = error instanceof Error ? error.message : String(error);
    }
    await wait(Math.min(60_000, attempt * 10_000));
  }
  throw new Error(`${label}: ${lastError}`);
}

const localDate = new Intl.DateTimeFormat('sv-SE', {
  timeZone: 'America/Sao_Paulo',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
}).format(new Date()).replaceAll('-', '');

await waitForReceiver();

for (const [city, ibge] of cities) {
  let page = 1;
  let totalPages = 1;
  do {
    const pncpUrl = new URL('https://pncp.gov.br/api/consulta/v1/contratacoes/proposta');
    pncpUrl.searchParams.set('dataFinal', localDate);
    pncpUrl.searchParams.set('codigoMunicipioIbge', ibge);
    pncpUrl.searchParams.set('pagina', String(page));
    pncpUrl.searchParams.set('tamanhoPagina', '50');

    const pncp = await requestJson(pncpUrl, {}, `PNCP ${city}, pagina ${page}`);
    const records = Array.isArray(pncp.data) ? pncp.data : [];
    totalPages = Math.max(1, Math.min(100, Number.parseInt(pncp.totalPaginas ?? '1', 10) || 1));

    const ingestUrl = new URL(siteApiUrl);
    ingestUrl.searchParams.set('acao', 'receber-pncp');
    const result = await requestJson(ingestUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-KEY': apiKey,
      },
      body: JSON.stringify({
        cidade: city,
        pagina: page,
        totalPaginas: totalPages,
        dados: records,
      }),
    }, `gravacao de ${city}, pagina ${page}`);

    if (result.ok !== true) {
      throw new Error(`A API do site rejeitou ${city}, pagina ${page}.`);
    }
    console.log(`${city}: pagina ${page}/${totalPages}, ${records.length} licitacoes.`);
    page += 1;
    if (page <= totalPages) await wait(1_500);
  } while (page <= totalPages);
}
