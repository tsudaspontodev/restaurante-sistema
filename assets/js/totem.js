// assets/js/totem.js
const TEMPO_REINICIO = 15;
const TEMPO_INATIVIDADE = 120;

const estado = {
    consumo: null,
    categoriaAtual: TOTEM_CATEGORIAS.length ? Number(TOTEM_CATEGORIAS[0].id) : null,
    carrinho: [],
    pagamento: null,
};

const modalProduto = new bootstrap.Modal(document.getElementById('modalProduto'));
let produtoModal = null;
let qtdModal = 1;
let timerReinicio = null;
let timerInatividade = null;

const formatarMoeda = valor => 'R$ ' + Number(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

const escapeHtml = texto => String(texto ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[c]));

const labelConsumo = () => estado.consumo === 'viagem' ? 'Para viagem' : 'Comer aqui';

function mostrarEtapa(id) {
    document.querySelectorAll('.totem-etapa').forEach(el => el.classList.add('d-none'));
    document.getElementById(id).classList.remove('d-none');
    window.scrollTo(0, 0);
}

function reiniciarTotem() {
    clearInterval(timerReinicio);
    estado.consumo = null;
    estado.carrinho = [];
    estado.pagamento = null;
    estado.categoriaAtual = TOTEM_CATEGORIAS.length ? Number(TOTEM_CATEGORIAS[0].id) : null;
    document.querySelectorAll('.totem-categoria').forEach((b, i) => b.classList.toggle('active', i === 0));
    modalProduto.hide();
    renderizarCarrinho();
    mostrarEtapa('etapaInicio');
}

// Volta ao início se o cliente abandonar o pedido no meio
function registrarAtividade() {
    clearTimeout(timerInatividade);
    timerInatividade = setTimeout(() => {
        const naConfirmacao = !document.getElementById('etapaConfirmacao').classList.contains('d-none');
        const noInicio = !document.getElementById('etapaInicio').classList.contains('d-none');
        if (!naConfirmacao && !noInicio) reiniciarTotem();
    }, TEMPO_INATIVIDADE * 1000);
}
['click', 'touchstart', 'keydown'].forEach(ev => document.addEventListener(ev, registrarAtividade, { passive: true }));

// ---------- Etapa 1: tipo de consumo ----------
document.querySelectorAll('[data-consumo]').forEach(btn => {
    btn.addEventListener('click', () => {
        estado.consumo = btn.dataset.consumo;
        document.getElementById('labelConsumo').textContent = labelConsumo();
        renderizarProdutos();
        mostrarEtapa('etapaCardapio');
    });
});

document.getElementById('btnTrocarConsumo').addEventListener('click', () => {
    estado.consumo = estado.consumo === 'local' ? 'viagem' : 'local';
    document.getElementById('labelConsumo').textContent = labelConsumo();
});

document.getElementById('btnCancelar').addEventListener('click', () => {
    if (estado.carrinho.length === 0 || confirm('Cancelar o pedido e voltar ao início?')) reiniciarTotem();
});

// ---------- Etapa 2: cardápio ----------
document.querySelectorAll('.totem-categoria').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.totem-categoria').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        estado.categoriaAtual = Number(btn.dataset.categoria);
        renderizarProdutos();
    });
});

function renderizarProdutos() {
    const grid = document.getElementById('gridProdutos');
    const lista = TOTEM_PRODUTOS.filter(p => Number(p.id_categoria) === estado.categoriaAtual);
    grid.innerHTML = lista.map(p => `
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card totem-produto shadow-sm h-100" data-produto="${p.id}">
                <img src="${escapeHtml(p.imagem_url)}" class="card-img-top" alt="">
                <div class="card-body d-flex flex-column">
                    <h6 class="fw-bold mb-1">${escapeHtml(p.nome)}</h6>
                    <p class="small text-muted mb-3">${escapeHtml(p.descricao)}</p>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <span class="fs-5 fw-bold">${formatarMoeda(p.preco)}</span>
                        <span class="btn btn-warning btn-sm fw-semibold"><i class="bi bi-plus-lg"></i> Adicionar</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    grid.querySelectorAll('[data-produto]').forEach(card => {
        card.addEventListener('click', () => abrirModalProduto(Number(card.dataset.produto)));
    });
}

function abrirModalProduto(idProduto) {
    produtoModal = TOTEM_PRODUTOS.find(p => Number(p.id) === idProduto);
    qtdModal = 1;
    document.getElementById('modalProdutoImg').src = produtoModal.imagem_url || '';
    document.getElementById('modalProdutoNome').textContent = produtoModal.nome;
    document.getElementById('modalProdutoDescricao').textContent = produtoModal.descricao || '';
    document.getElementById('modalProdutoObs').value = '';
    atualizarModalQtd();
    modalProduto.show();
}

function atualizarModalQtd() {
    document.getElementById('modalProdutoQtd').textContent = qtdModal;
    document.getElementById('modalProdutoSubtotal').textContent = formatarMoeda(produtoModal.preco * qtdModal);
}

document.getElementById('modalProdutoMenos').addEventListener('click', () => {
    if (qtdModal > 1) { qtdModal--; atualizarModalQtd(); }
});
document.getElementById('modalProdutoMais').addEventListener('click', () => {
    if (qtdModal < 50) { qtdModal++; atualizarModalQtd(); }
});

document.getElementById('modalProdutoAdicionar').addEventListener('click', () => {
    const observacao = document.getElementById('modalProdutoObs').value.trim();
    const existente = estado.carrinho.find(i => i.id_produto === Number(produtoModal.id) && i.observacao === observacao);
    if (existente) {
        existente.quantidade = Math.min(50, existente.quantidade + qtdModal);
    } else {
        estado.carrinho.push({
            id_produto: Number(produtoModal.id),
            nome: produtoModal.nome,
            preco: Number(produtoModal.preco),
            quantidade: qtdModal,
            observacao,
        });
    }
    modalProduto.hide();
    renderizarCarrinho();
});

const totalCarrinho = () => estado.carrinho.reduce((soma, i) => soma + i.preco * i.quantidade, 0);

function renderizarCarrinho() {
    const box = document.getElementById('carrinhoItens');
    if (estado.carrinho.length === 0) {
        box.innerHTML = `
            <div class="text-center text-muted p-4">
                <i class="bi bi-cart-x fs-1"></i>
                <p class="mt-2 mb-0">Toque em um produto para adicionar</p>
            </div>`;
    } else {
        box.innerHTML = estado.carrinho.map((item, idx) => `
            <div class="d-flex justify-content-between align-items-start p-3 border-bottom">
                <div class="me-2">
                    <div class="fw-semibold">${escapeHtml(item.nome)}</div>
                    ${item.observacao ? `<div class="small text-danger">Obs: ${escapeHtml(item.observacao)}</div>` : ''}
                    <div class="btn-group btn-group-sm mt-2">
                        <button type="button" class="btn btn-outline-secondary" data-carrinho-menos="${idx}"><i class="bi bi-dash"></i></button>
                        <span class="btn btn-light fw-bold">${item.quantidade}</span>
                        <button type="button" class="btn btn-outline-secondary" data-carrinho-mais="${idx}"><i class="bi bi-plus"></i></button>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">${formatarMoeda(item.preco * item.quantidade)}</div>
                    <button type="button" class="btn btn-link text-danger p-0 mt-2" data-carrinho-remover="${idx}">
                        <i class="bi bi-trash"></i> Remover
                    </button>
                </div>
            </div>
        `).join('');
    }

    document.getElementById('carrinhoTotal').textContent = formatarMoeda(totalCarrinho());
    document.getElementById('btnIrPagamento').disabled = estado.carrinho.length === 0;
}

document.getElementById('carrinhoItens').addEventListener('click', e => {
    const btn = e.target.closest('button');
    if (!btn) return;
    if (btn.dataset.carrinhoMais !== undefined) {
        const item = estado.carrinho[btn.dataset.carrinhoMais];
        item.quantidade = Math.min(50, item.quantidade + 1);
    } else if (btn.dataset.carrinhoMenos !== undefined) {
        const idx = Number(btn.dataset.carrinhoMenos);
        if (--estado.carrinho[idx].quantidade <= 0) estado.carrinho.splice(idx, 1);
    } else if (btn.dataset.carrinhoRemover !== undefined) {
        estado.carrinho.splice(Number(btn.dataset.carrinhoRemover), 1);
    }
    renderizarCarrinho();
});

// ---------- Etapa 3: pagamento ----------
const INSTRUCOES_PAGAMENTO = {
    'Cartão Crédito': 'Insira ou aproxime seu cartão de crédito na maquininha ao lado.',
    'Cartão Débito': 'Insira ou aproxime seu cartão de débito na maquininha ao lado.',
    'PIX': 'Escaneie o QR Code exibido na maquininha com o app do seu banco.',
    'Dinheiro': 'Após confirmar, vá ao caixa com a sua senha e pague em dinheiro. O pedido é liberado depois do pagamento.',
};

document.getElementById('btnIrPagamento').addEventListener('click', () => {
    estado.pagamento = null;
    document.querySelectorAll('.totem-pagamento').forEach(b => b.classList.remove('active'));
    document.getElementById('instrucaoPagamento').classList.add('d-none');
    document.getElementById('erroPagamento').classList.add('d-none');
    document.getElementById('btnConfirmarPagamento').disabled = true;

    document.getElementById('resumoConsumo').innerHTML = estado.consumo === 'viagem'
        ? '<i class="bi bi-bag-check me-1"></i> Para viagem'
        : '<i class="bi bi-cup-hot me-1"></i> Comer aqui';
    document.getElementById('resumoItens').innerHTML = estado.carrinho.map(item => `
        <li class="list-group-item d-flex justify-content-between px-0">
            <span>
                ${item.quantidade}x ${escapeHtml(item.nome)}
                ${item.observacao ? `<br><small class="text-danger">Obs: ${escapeHtml(item.observacao)}</small>` : ''}
            </span>
            <span class="fw-semibold">${formatarMoeda(item.preco * item.quantidade)}</span>
        </li>
    `).join('');
    document.getElementById('resumoTotal').textContent = formatarMoeda(totalCarrinho());
    mostrarEtapa('etapaPagamento');
});

document.getElementById('btnVoltarCardapio').addEventListener('click', () => mostrarEtapa('etapaCardapio'));

document.querySelectorAll('.totem-pagamento').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.totem-pagamento').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        estado.pagamento = btn.dataset.pagamento;
        const instrucao = document.getElementById('instrucaoPagamento');
        instrucao.textContent = INSTRUCOES_PAGAMENTO[estado.pagamento];
        instrucao.classList.remove('d-none');
        document.getElementById('btnConfirmarPagamento').disabled = false;
    });
});

document.getElementById('btnConfirmarPagamento').addEventListener('click', async () => {
    const btn = document.getElementById('btnConfirmarPagamento');
    const erroBox = document.getElementById('erroPagamento');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processando...';
    erroBox.classList.add('d-none');

    try {
        const res = await fetch('../api/totem_actions.php?acao=finalizar_pedido', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo_consumo: estado.consumo,
                forma_pagamento: estado.pagamento,
                itens: estado.carrinho.map(i => ({
                    id_produto: i.id_produto,
                    quantidade: i.quantidade,
                    observacao: i.observacao,
                })),
            }),
        });
        const data = await res.json();
        if (!data.sucesso) throw new Error(data.erro || 'Não foi possível concluir o pedido.');
        mostrarConfirmacao(data);
    } catch (erro) {
        erroBox.textContent = erro.message;
        erroBox.classList.remove('d-none');
        btn.disabled = false;
    } finally {
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Confirmar pagamento';
    }
});

// ---------- Etapa 4: confirmação ----------
function mostrarConfirmacao(data) {
    document.getElementById('confirmacaoSenha').textContent = data.senha;
    document.getElementById('confirmacaoDetalhe').textContent =
        `${labelConsumo()} • ${estado.pagamento} • Total R$ ${data.total_formatado}`;
    document.getElementById('confirmacaoTitulo').textContent = data.pagamento_pendente
        ? 'Pedido registrado!' : 'Pedido confirmado!';
    const aviso = document.getElementById('confirmacaoAviso');
    aviso.textContent = data.pagamento_pendente
        ? `Vá ao caixa, informe a senha ${data.senha} e pague R$ ${data.total_formatado}. Seu pedido será liberado após o pagamento.`
        : '';
    aviso.classList.toggle('d-none', !data.pagamento_pendente);
    mostrarEtapa('etapaConfirmacao');

    let restante = TEMPO_REINICIO;
    const contador = document.getElementById('contagemReinicio');
    contador.textContent = restante;
    clearInterval(timerReinicio);
    timerReinicio = setInterval(() => {
        restante--;
        contador.textContent = restante;
        if (restante <= 0) reiniciarTotem();
    }, 1000);
}

document.getElementById('btnNovoPedido').addEventListener('click', reiniciarTotem);

renderizarCarrinho();
