// assets/js/tablet.js — Home do cardápio digital da mesa
const DESTAQUES = 'destaques';
const CHAVE_CARRINHO = `tablet_carrinho_mesa_${TB_MESA ?? 0}`;

const escapeHtml = texto => String(texto ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[c]));
const moeda = valor => Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const temFoto = url => /^https?:\/\//i.test(url || '') && !/placehold\.co/i.test(url);

const ICONES = [
    [/hamb|burg|lanche/i, 'bi-egg-fried'],
    [/por[cç]|entrada|batata/i, 'bi-basket2-fill'],
    [/bebida|suco|refri|drink/i, 'bi-cup-straw'],
    [/sobremesa|doce|brownie/i, 'bi-cake2-fill'],
];
const iconeDe = texto => (ICONES.find(([re]) => re.test(texto)) || [null, 'bi-stars'])[1];

const estado = {
    secao: DESTAQUES,
    carrinho: JSON.parse(sessionStorage.getItem(CHAVE_CARRINHO) || '[]'),
    carrossel: null,
    timerCarrossel: null,
    pausaCarrosselAte: 0,
    statusMesa: TB_STATUS_MESA,
    totalComanda: 0,
};

const elementoCarrossel = document.getElementById('carrosselDestaques');

function pararCarrossel() {
    clearTimeout(estado.timerCarrossel);
    estado.timerCarrossel = null;
    estado.carrossel?.dispose();
    estado.carrossel = null;
}

function agendarProximoSlide() {
    clearTimeout(estado.timerCarrossel);
    if (!estado.carrossel || estado.secao !== DESTAQUES) return;
    const esperaPausa = Math.max(0, estado.pausaCarrosselAte - Date.now());
    estado.timerCarrossel = setTimeout(() => {
        if (esperaPausa) {
            agendarProximoSlide();
            return;
        }
        estado.carrossel.next();
        agendarProximoSlide();
    }, esperaPausa || 4500);
}

function pausarCarrossel() {
    estado.pausaCarrosselAte = Date.now() + 6000;
    agendarProximoSlide();
}

['pointerdown', 'touchstart', 'click', 'keydown'].forEach(evento => {
    elementoCarrossel.addEventListener(evento, pausarCarrossel, { passive: true });
});

// ---------- Menu lateral ----------
function renderizarCategorias() {
    const destaque = `
        <button type="button" class="tb-cat ${estado.secao === DESTAQUES ? 'ativo' : ''}" data-secao="${DESTAQUES}">
            <span class="tb-cat-thumb tb-cat-thumb-destaque"><i class="bi bi-star-fill"></i></span>
            <span class="tb-cat-texto">
                <span class="tb-cat-nome">Destaques da Casa</span>
                <span class="tb-cat-desc">Os favoritos dos nossos clientes</span>
            </span>
        </button>`;

    const itens = TB_CATEGORIAS.map(c => {
        const thumb = temFoto(c.imagem_url)
            ? `<img src="${escapeHtml(c.imagem_url)}" alt="" loading="lazy">`
            : `<i class="bi ${iconeDe(c.nome)}"></i>`;
        return `
            <button type="button" class="tb-cat ${estado.secao === Number(c.id) ? 'ativo' : ''}" data-secao="${Number(c.id)}">
                <span class="tb-cat-thumb">${thumb}</span>
                <span class="tb-cat-texto">
                    <span class="tb-cat-nome">${escapeHtml(c.nome)}</span>
                    <span class="tb-cat-desc">${escapeHtml(c.descricao)}</span>
                    <span class="tb-cat-meta">${Number(c.qtd)} ${Number(c.qtd) === 1 ? 'opção' : 'opções'} · a partir de ${moeda(c.preco_min)}</span>
                </span>
            </button>`;
    }).join('');

    document.getElementById('listaCategorias').innerHTML = destaque + itens;
}

document.getElementById('listaCategorias').addEventListener('click', e => {
    const btn = e.target.closest('.tb-cat');
    if (!btn) return;
    estado.secao = btn.dataset.secao === DESTAQUES ? DESTAQUES : Number(btn.dataset.secao);
    renderizarCategorias();
    renderizarProdutos();
    document.querySelector('.tb-main').scrollTo({ top: 0, behavior: 'smooth' });
});

// ---------- Grade de produtos ----------
function renderizarProdutos() {
    let lista;
    const titulo = document.getElementById('tituloSecao');
    const subtitulo = document.getElementById('subtituloSecao');

    const grid = document.getElementById('gridProdutos');
    if (estado.secao === DESTAQUES) {
        lista = TB_DESTAQUES.map(id => TB_PRODUTOS.find(p => Number(p.id) === id)).filter(Boolean);
        titulo.textContent = 'Destaques da Casa';
        subtitulo.textContent = 'Os pratos mais pedidos pelos nossos clientes';
        grid.classList.add('d-none');
        elementoCarrossel.classList.remove('d-none');
        renderizarCarrossel(lista);
    } else {
        const cat = TB_CATEGORIAS.find(c => Number(c.id) === estado.secao);
        lista = TB_PRODUTOS.filter(p => Number(p.id_categoria) === estado.secao);
        titulo.textContent = cat ? cat.nome : 'Cardápio';
        subtitulo.textContent = `${lista.length} ${lista.length === 1 ? 'opção disponível' : 'opções disponíveis'}`;
        pararCarrossel();
        elementoCarrossel.classList.add('d-none');
        grid.classList.remove('d-none');
    }

    const maisVendidos = [...TB_PRODUTOS].filter(p => Number(p.vendidos) > 0)
        .sort((a, b) => b.vendidos - a.vendidos).slice(0, 3).map(p => Number(p.id));

    if (estado.secao === DESTAQUES) return;
    if (lista.length === 0) {
        grid.innerHTML = `<div class="tb-vazio"><i class="bi bi-emoji-frown"></i><p>Nenhum produto disponível nesta categoria.</p></div>`;
        return;
    }

    grid.innerHTML = lista.map(p => {
        const cat = TB_CATEGORIAS.find(c => Number(c.id) === Number(p.id_categoria));
        const foto = temFoto(p.imagem_url)
            ? `<img src="${escapeHtml(p.imagem_url)}" alt="${escapeHtml(p.nome)}" loading="lazy">`
            : `<div class="tb-card-semfoto"><i class="bi ${iconeDe(cat ? cat.nome : p.nome)}"></i></div>`;
        const selo = maisVendidos.includes(Number(p.id))
            ? '<span class="tb-selo"><i class="bi bi-fire"></i> Mais pedido</span>' : '';
        const noCarrinho = estado.carrinho.find(i => i.id === Number(p.id));

        return `
            <article class="tb-card">
                <div class="tb-card-foto">${foto}${selo}</div>
                <div class="tb-card-corpo">
                    <h2 class="tb-card-nome">${escapeHtml(p.nome)}</h2>
                    <p class="tb-card-desc">${escapeHtml(p.descricao || 'Preparado na hora com ingredientes selecionados.')}</p>
                    <div class="tb-card-rodape">
                        <span class="tb-preco">${moeda(p.preco)}</span>
                        <button type="button" class="tb-add" data-id="${Number(p.id)}" aria-label="Adicionar ${escapeHtml(p.nome)}" ${!podePedir() ? 'disabled' : ''}>
                            ${noCarrinho ? `<span class="tb-add-qtd">${noCarrinho.quantidade}</span>` : ''}
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </div>
            </article>`;
    }).join('');
}

function renderizarCarrossel(lista) {
    pararCarrossel();
    const slides = document.getElementById('slidesDestaques');
    const indicadores = document.getElementById('indicadoresDestaques');
    const controles = elementoCarrossel.querySelectorAll('.carousel-control-prev, .carousel-control-next');

    if (lista.length === 0) {
        slides.innerHTML = '<div class="tb-vazio"><i class="bi bi-emoji-frown"></i><p>Nenhum destaque disponível.</p></div>';
        indicadores.innerHTML = '';
        controles.forEach(controle => controle.classList.add('d-none'));
        return;
    }

    controles.forEach(controle => controle.classList.toggle('d-none', lista.length < 2));
    slides.innerHTML = lista.map((p, indice) => {
        const cat = TB_CATEGORIAS.find(c => Number(c.id) === Number(p.id_categoria));
        const imagem = temFoto(p.imagem_url)
            ? `<img src="${escapeHtml(p.imagem_url)}" alt="${escapeHtml(p.nome)}" ${indice === 0 ? 'fetchpriority="high"' : 'loading="lazy"'}>`
            : `<div class="tb-card-semfoto"><i class="bi ${iconeDe(cat ? cat.nome : p.nome)}"></i></div>`;
        const maisPedido = Number(p.vendidos) > 0;
        const noCarrinho = estado.carrinho.find(i => i.id === Number(p.id));
        return `
            <div class="carousel-item ${indice === 0 ? 'active' : ''}">
                <article class="tb-destaque-slide">
                    ${imagem}
                    <div class="tb-destaque-overlay">
                        <span class="tb-destaque-selo"><i class="bi ${maisPedido ? 'bi-fire' : 'bi-star-fill'}"></i> ${maisPedido ? 'Mais Pedido' : 'Destaque da Casa'}</span>
                        <h2 class="tb-destaque-nome">${escapeHtml(p.nome)}</h2>
                        <p class="tb-destaque-desc">${escapeHtml(p.descricao || 'Preparado na hora com ingredientes selecionados.')}</p>
                        <div class="tb-destaque-rodape">
                            <span class="tb-destaque-preco">${moeda(p.preco)}</span>
                            <button type="button" class="tb-add-destaque" data-id="${Number(p.id)}" aria-label="Adicionar ${escapeHtml(p.nome)} ao pedido" ${!podePedir() ? 'disabled' : ''}>
                                Adicionar ao Pedido + ${noCarrinho ? `<span class="tb-add-qtd">${noCarrinho.quantidade}</span>` : ''}
                            </button>
                        </div>
                    </div>
                </article>
            </div>`;
    }).join('');

    indicadores.innerHTML = lista.length > 1 ? lista.map((p, indice) => `
        <button type="button" data-bs-target="#carrosselDestaques" data-bs-slide-to="${indice}" ${indice === 0 ? 'class="active" aria-current="true"' : ''} aria-label="Ir ao destaque ${indice + 1}: ${escapeHtml(p.nome)}"></button>`).join('') : '';

    if (lista.length > 1) {
        estado.carrossel = new bootstrap.Carousel(elementoCarrossel, {
            interval: false,
            ride: false,
            touch: true,
            wrap: true,
        });
        agendarProximoSlide();
    }
}

document.querySelector('.tb-main').addEventListener('click', e => {
    const btn = e.target.closest('.tb-add');
    const destaqueBtn = e.target.closest('.tb-add-destaque');
    const botao = btn || destaqueBtn;
    if (!botao) return;
    if (!podePedir()) return mostrarToast('Esta mesa não está aceitando novos pedidos.');
    const produto = TB_PRODUTOS.find(p => Number(p.id) === Number(botao.dataset.id));
    if (!produto) return;
    alterarQuantidade(Number(produto.id), 1);
    botao.classList.remove('tb-add-pulso');
    void botao.offsetWidth;
    botao.classList.add('tb-add-pulso');
    mostrarToast(`${produto.nome} adicionado ao pedido`);
});

// ---------- Pedido (carrinho) ----------
function alterarQuantidade(id, delta) {
    const item = estado.carrinho.find(i => i.id === id);
    if (item) {
        item.quantidade = Math.min(50, item.quantidade + delta);
        if (item.quantidade <= 0) estado.carrinho = estado.carrinho.filter(i => i.id !== id);
    } else if (delta > 0) {
        estado.carrinho.push({ id, quantidade: 1 });
    }
    sessionStorage.setItem(CHAVE_CARRINHO, JSON.stringify(estado.carrinho));
    renderizarCarrinho();
    renderizarProdutos();
}

function renderizarCarrinho() {
    // Ignora produtos que deixaram de estar disponíveis
    const itens = estado.carrinho
        .map(i => ({ ...i, produto: TB_PRODUTOS.find(p => Number(p.id) === i.id) }))
        .filter(i => i.produto);
    const qtd = itens.reduce((s, i) => s + i.quantidade, 0);
    const total = itens.reduce((s, i) => s + i.quantidade * Number(i.produto.preco), 0);

    document.getElementById('btnCarrinho').classList.toggle('d-none', qtd === 0);
    document.getElementById('carrinhoQtd').textContent = qtd;
    document.getElementById('carrinhoTotal').textContent = moeda(total);
    document.getElementById('painelTotal').textContent = moeda(total);
    const enviar = document.getElementById('btnEnviarPedido');
    if (enviar) enviar.disabled = !qtd || !podePedir();

    document.getElementById('painelItens').innerHTML = itens.length === 0
        ? '<p class="text-muted text-center mt-4">Seu pedido está vazio.</p>'
        : itens.map(i => `
            <div class="tb-painel-item">
                <div class="flex-grow-1">
                    <div class="fw-semibold">${escapeHtml(i.produto.nome)}</div>
                    <div class="text-muted small">${moeda(i.produto.preco)} cada</div>
                </div>
                <div class="tb-qtd">
                    <button type="button" data-id="${i.id}" data-delta="-1" aria-label="Diminuir"><i class="bi bi-dash-lg"></i></button>
                    <span>${i.quantidade}</span>
                    <button type="button" data-id="${i.id}" data-delta="1" aria-label="Aumentar"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>`).join('');
}

function podePedir() { return Boolean(TB_MESA) && estado.statusMesa !== 'fechamento'; }

async function enviarPedido() {
    if (!podePedir() || !estado.carrinho.length) return;
    const botao = document.getElementById('btnEnviarPedido');
    botao.disabled = true;
    try {
        const resposta = await fetch('api/enviar_pedido.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mesa: TB_MESA, itens: estado.carrinho.map(i => ({ id_produto: i.id, quantidade: i.quantidade })) })
        });
        const dados = await resposta.json();
        if (!resposta.ok || !dados.sucesso) throw new Error(dados.erro || 'Não foi possível enviar o pedido.');
        estado.carrinho = [];
        sessionStorage.removeItem(CHAVE_CARRINHO);
        renderizarCarrinho(); renderizarProdutos();
        bootstrap.Offcanvas.getInstance(document.getElementById('painelPedido'))?.hide();
        mostrarToast('Pedido enviado à cozinha!');
        carregarComanda();
    } catch (erro) {
        mostrarToast(erro.message);
        botao.disabled = !estado.carrinho.length || !podePedir();
    }
}

function renderizarItensComanda(itens, vazio) {
    if (!itens.length) return `<p class="text-muted small">${vazio}</p>`;
    return `<ul class="list-group mb-3">${itens.map(i => `
      <li class="list-group-item px-0"><div class="d-flex justify-content-between gap-2"><div><strong>${Number(i.quantidade)}× ${escapeHtml(i.produto_nome)}</strong><div class="small text-muted">Pedido às ${escapeHtml(i.hora_pedido)}</div></div><div class="text-end"><span class="badge ${i.status_item === 'na_cozinha' ? 'text-bg-warning' : 'text-bg-success'}">${i.status_item === 'na_cozinha' ? 'Em preparo' : 'Entregue'}</span><div class="small fw-semibold mt-1">${moeda(i.subtotal)}</div></div></div></li>`).join('')}</ul>`;
}

async function carregarComanda() {
    if (!TB_MESA) return;
    try {
        const resposta = await fetch(`api/comanda_mesa.php?mesa=${encodeURIComponent(TB_MESA)}`, { cache: 'no-store' });
        const dados = await resposta.json();
        if (!resposta.ok || !dados.sucesso) throw new Error(dados.erro || 'Falha ao consultar comanda.');
        estado.totalComanda = Number(dados.total || 0);
        estado.statusMesa = dados.mesa.status;
        document.getElementById('comandaTotalTopo').textContent = moeda(estado.totalComanda);
        document.getElementById('comandaTotalRodape').textContent = moeda(estado.totalComanda);
        document.getElementById('comandaCorpo').innerHTML = `
          <h6 class="fw-bold text-warning-emphasis">Em Preparo / Não Entregues</h6>${renderizarItensComanda(dados.itens_cozinha, 'Nenhum item aguardando preparo.')}
          <h6 class="fw-bold text-success mt-4">Entregues / Consumidos</h6>${renderizarItensComanda(dados.itens_entregues, 'Nenhum item entregue ainda.')}`;
        const fechando = estado.statusMesa === 'fechamento';
        document.getElementById('btnPedirConta').disabled = fechando;
        document.getElementById('avisoFechamento').classList.toggle('d-none', !fechando);
        document.getElementById('btnEnviarPedido').disabled = fechando || !estado.carrinho.length;
        document.querySelectorAll('.tb-add, .tb-add-destaque').forEach(b => b.disabled = fechando);
    } catch (erro) {
        document.getElementById('comandaCorpo').innerHTML = `<div class="alert alert-danger">${escapeHtml(erro.message)}</div>`;
    }
}

async function pedirConta() {
    if (!confirm('Deseja chamar o caixa para fechar sua conta?')) return;
    const botao = document.getElementById('btnPedirConta'); botao.disabled = true;
    try {
        const resposta = await fetch('api/pedir_conta.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mesa: TB_MESA }) });
        const dados = await resposta.json();
        if (!resposta.ok || !dados.sucesso) throw new Error(dados.erro || 'Não foi possível solicitar o fechamento.');
        estado.statusMesa = 'fechamento';
        document.getElementById('avisoFechamento').classList.remove('d-none');
        document.getElementById('btnEnviarPedido').disabled = true;
        document.querySelectorAll('.tb-add, .tb-add-destaque').forEach(b => b.disabled = true);
        mostrarToast('O caixa foi avisado.');
    } catch (erro) { mostrarToast(erro.message); botao.disabled = false; }
}

document.getElementById('btnEnviarPedido')?.addEventListener('click', enviarPedido);
document.getElementById('btnPedirConta')?.addEventListener('click', pedirConta);

document.getElementById('painelItens').addEventListener('click', e => {
    const btn = e.target.closest('button[data-delta]');
    if (btn) alterarQuantidade(Number(btn.dataset.id), Number(btn.dataset.delta));
});

let timerToast;
function mostrarToast(texto) {
    const toast = document.getElementById('toast');
    toast.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${escapeHtml(texto)}`;
    toast.classList.add('visivel');
    clearTimeout(timerToast);
    timerToast = setTimeout(() => toast.classList.remove('visivel'), 1800);
}

renderizarCategorias();
renderizarProdutos();
renderizarCarrinho();
if (TB_MESA) { carregarComanda(); setInterval(carregarComanda, 12000); }
