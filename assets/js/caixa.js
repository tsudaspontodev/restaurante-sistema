// assets/js/caixa.js
let mesaAtualId = null;
let requisicaoMesaAtiva = 0;
let atualizandoMapa = false;

const escapeHtml = texto => String(texto ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[c]));
const modalElement = document.getElementById('modalMesa');
const modalBootstrap = new bootstrap.Modal(modalElement);

function formularioLancamento() {
    const opcoes = CAIXA_PRODUTOS.map(p => `<option value="${Number(p.id)}">${escapeHtml(p.nome)} — ${Number(p.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</option>`).join('');
    return `<form id="formLancarItem" class="card card-body bg-light mb-3" data-mesa-id="${Number(mesaAtualId)}">
        <label class="form-label fw-bold" for="produtoLancamento">Lançar Novo Produto na Mesa</label>
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md"><select class="form-select" id="produtoLancamento" required ${CAIXA_PRODUTOS.length ? '' : 'disabled'}><option value="">Selecione um produto</option>${opcoes}</select></div>
            <div class="col-5 col-md-2"><label class="form-label small mb-1" for="quantidadeLancamento">Quantidade</label><input class="form-control" id="quantidadeLancamento" type="number" min="1" max="50" value="1" required></div>
            <div class="col-7 col-md-auto"><button class="btn btn-primary w-100" type="submit" ${CAIXA_PRODUTOS.length ? '' : 'disabled'}><i class="bi bi-plus-circle me-1"></i>Lançar na Mesa</button></div>
        </div>
        ${CAIXA_PRODUTOS.length ? '' : '<div class="small text-danger mt-2">Não há produtos ativos cadastrados.</div>'}
    </form>`;
}

// Abre o modal e carrega dados atualizados do servidor
function abrirModalMesa(idMesa) {
    mesaAtualId = idMesa;
    modalBootstrap.show();
    carregarDadosMesa(idMesa);
}

async function carregarDadosMesa(idMesa) {
    const requisicao = ++requisicaoMesaAtiva;
    const pagamentoSelecionado = document.getElementById('selectPagamento')?.value;
    const corpo = document.getElementById('modalMesaCorpo');
    const rolagemCorpo = corpo.scrollTop;
    const rodape = document.getElementById('modalMesaRodape');
    const titulo = document.getElementById('modalMesaTitulo');

    try {
        const res = await fetch(`api_mesa.php?id_mesa=${idMesa}`);
        const data = await res.json();

        if (!data.sucesso) throw new Error(data.erro);
        if (requisicao !== requisicaoMesaAtiva || mesaAtualId !== idMesa) return;

        const numFormatado = String(data.mesa.numero).padStart(2, '0');
        titulo.innerHTML = `<i class="bi bi-table me-2"></i>Mesa ${numFormatado} — Status: ${escapeHtml(data.mesa.status.toUpperCase())}`;

        // Formulário disponível para mesa livre, ocupada ou em fechamento.
        if (data.mesa.status === 'livre') {
            corpo.innerHTML = `${formularioLancamento()}<div class="text-center py-4 text-muted"><i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i><h5 class="mt-3">Esta mesa está livre.</h5><p class="mb-0">O primeiro lançamento abrirá a mesa automaticamente.</p></div>`;
            rodape.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button><a class="btn btn-outline-warning" href="../tablet/index.php?mesa=${Number(data.mesa.numero)}" target="_blank" rel="noopener"><i class="bi bi-table me-1"></i>Abrir Tablet desta Mesa</a>`;
            return;
        }

        const banner = data.mesa.status === 'fechamento'
            ? '<div class="alert alert-warning fw-bold"><i class="bi bi-bell-fill me-2"></i>Cliente solicitou o fechamento da conta pelo tablet.</div>' : '';
        const linhas = data.itens.map(item => `<tr>
            <td>${escapeHtml(item.hora_pedido)}</td><td>${Number(item.quantidade)}</td>
            <td>${escapeHtml(item.produto_nome)}${item.observacao ? `<div class="small text-danger">Obs: ${escapeHtml(item.observacao)}</div>` : ''}</td>
            <td><span class="badge ${item.status_item === 'na_cozinha' ? 'bg-warning text-dark' : 'bg-success'}">${item.status_item === 'na_cozinha' ? 'Na Cozinha/Bar' : 'Entregue'}</span></td>
            <td class="text-end">R$ ${Number(item.subtotal).toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
            <td>${item.status_item === 'na_cozinha' ? `<button class="btn btn-sm btn-success" onclick="marcarEntregue(${Number(item.id)})"><i class="bi bi-check-lg"></i> Entregue</button>` : '<i class="bi bi-check2-all text-success"></i>'}</td>
        </tr>`).join('');
        corpo.innerHTML = `${formularioLancamento()}${banner}${data.itens.length ? `<div class="table-responsive"><table class="table table-sm table-striped align-middle"><thead><tr><th>Horário</th><th>Qtd.</th><th>Produto</th><th>Status</th><th class="text-end">Subtotal</th><th>Ação</th></tr></thead><tbody>${linhas}</tbody></table></div>` : '<p class="text-muted">Ainda não há lançamentos nesta mesa.</p>'}`;

        rodape.innerHTML = `
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
                <div><div class="small text-muted">Total da Conta</div><strong class="fs-4">R$ ${data.total_formatado}</strong></div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-outline-secondary" onclick="carregarDadosMesa(${idMesa})" aria-label="Atualizar lançamentos"><i class="bi bi-arrow-clockwise"></i></button>
                <a class="btn btn-outline-warning" href="../tablet/index.php?mesa=${Number(data.mesa.numero)}" target="_blank" rel="noopener"><i class="bi bi-table me-1"></i>Abrir Tablet desta Mesa</a>
                <select id="selectPagamento" class="form-select form-select-sm" style="width: auto;">
                    <option value="Dinheiro">Dinheiro</option>
                    <option value="Cartão Crédito">Cartão Crédito</option>
                    <option value="Cartão Débito">Cartão Débito</option>
                    <option value="PIX">PIX</option>
                </select>
                <button type="button" class="btn btn-primary fw-bold" onclick="fecharConta(${idMesa})">
                    <i class="bi bi-cash-coin me-1"></i> Receber e Liberar Mesa
                </button>
                </div>
            </div>
        `;
        if (pagamentoSelecionado && document.getElementById('selectPagamento')) document.getElementById('selectPagamento').value = pagamentoSelecionado;
        corpo.scrollTop = rolagemCorpo;

    } catch (erro) {
        corpo.innerHTML = `<div class="alert alert-danger">Erro ao carregar mesa: ${escapeHtml(erro.message)}</div>`;
    }
}

async function marcarEntregue(idItem) {
    const res = await fetch('../api/caixa_actions.php?acao=marcar_entregue', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_item: idItem })
    });
    const data = await res.json();
    if (data.sucesso) {
        if (mesaAtualId !== null) carregarDadosMesa(mesaAtualId); // Recarrega o modal instantaneamente
        carregarPedidosTotem();
    } else {
        alert(data.erro);
    }
}

modalElement.addEventListener('submit', async event => {
    const form = event.target.closest('#formLancarItem');
    if (!form) return;
    event.preventDefault();
    const botao = form.querySelector('button[type="submit"]');
    const idProduto = Number(form.querySelector('#produtoLancamento').value);
    const quantidade = Number(form.querySelector('#quantidadeLancamento').value);
    if (!idProduto || !Number.isInteger(quantidade) || quantidade < 1 || quantidade > 50) {
        alert('Selecione um produto e informe uma quantidade entre 1 e 50.');
        return;
    }
    botao.disabled = true;
    try {
        const res = await fetch('api_mesa.php?acao=lancar_item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_mesa: mesaAtualId, id_produto: idProduto, quantidade })
        });
        const data = await res.json();
        if (!res.ok || !data.sucesso) throw new Error(data.erro || 'Não foi possível lançar o produto.');
        await Promise.all([carregarDadosMesa(mesaAtualId), atualizarMapaMesas()]);
    } catch (erro) {
        alert(erro.message);
        botao.disabled = false;
    }
});

async function abrirMesaManual(idMesa) {
    const res = await fetch('../api/caixa_actions.php?acao=abrir_mesa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_mesa: idMesa })
    });
    const data = await res.json();
    if (data.sucesso) location.reload();
    else alert(data.erro);
}

async function fecharConta(idMesa) {
    if (!confirm('Confirma o recebimento e o fechamento desta mesa?')) return;
    const formaPagamento = document.getElementById('selectPagamento').value;

    const res = await fetch('../api/caixa_actions.php?acao=fechar_conta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_mesa: idMesa, forma_pagamento: formaPagamento })
    });
    const data = await res.json();
    if (data.sucesso) {
        alert(data.mensagem);
        location.reload();
    } else {
        alert(data.erro);
    }
}

// ---------- Pedidos do Totem (sem mesa) ----------
async function carregarPedidosTotem() {
    const box = document.getElementById('pedidosTotem');
    const contador = document.getElementById('pedidosTotemQtd');
    if (!box) return;
    try {
        const res = await fetch('../api/caixa_actions.php?acao=pedidos_totem');
        const data = await res.json();
        if (!data.sucesso) throw new Error(data.erro);
        contador.textContent = data.pedidos.length;

        if (data.pedidos.length === 0) {
            box.innerHTML = `<p class="text-muted small fst-italic mb-0">Nenhum pedido do totem pendente.</p>`;
            return;
        }

        box.innerHTML = `<div class="row g-3">${data.pedidos.map(htmlPedidoTotem).join('')}</div>`;
    } catch (erro) {
        box.innerHTML = `<div class="alert alert-danger mb-0">Erro ao carregar pedidos do totem: ${escapeHtml(erro.message)}</div>`;
    }
}

function htmlPedidoTotem(pedido) {
    const pendente = pedido.status_conta === 'aberta';
    const consumo = pedido.tipo_consumo === 'viagem'
        ? '<i class="bi bi-bag"></i> Para viagem'
        : '<i class="bi bi-cup-hot"></i> Comer aqui';

    const itens = pedido.itens.map(item => {
        const obs = item.observacao
            ? `<div class="text-danger small fw-semibold"><i class="bi bi-exclamation-circle"></i> Obs: ${escapeHtml(item.observacao)}</div>`
            : '';
        let acao = '<span class="badge bg-secondary"><i class="bi bi-check2"></i> Entregue</span>';
        if (item.status_item === 'na_cozinha') {
            acao = pendente
                ? '<span class="badge bg-light text-muted border">Aguardando pagamento</span>'
                : `<button class="btn btn-sm btn-success" onclick="marcarEntregue(${Number(item.id)})"><i class="bi bi-check-lg"></i> Entregue</button>`;
        }
        return `
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-bold">${Number(item.quantidade)}x ${escapeHtml(item.produto_nome)}</span>
                    ${item.status_item === 'na_cozinha' ? `<span class="badge ${item.minutos_espera >= 15 ? 'bg-danger' : 'bg-warning text-dark'} ms-1">${Number(item.minutos_espera)} min</span>` : ''}
                    ${obs}
                </div>
                ${acao}
            </li>`;
    }).join('');

    const rodape = pendente ? `
        <div class="card-footer d-flex justify-content-between gap-2">
            <button class="btn btn-sm btn-outline-danger" onclick="cancelarPedidoTotem(${Number(pedido.id)})"><i class="bi bi-x-lg"></i> Cancelar</button>
            <button class="btn btn-sm btn-primary fw-bold" onclick="confirmarPagamentoTotem(${Number(pedido.id)}, '${escapeHtml(pedido.senha)}', '${escapeHtml(pedido.total_formatado)}')"><i class="bi bi-cash-coin"></i> Confirmar recebimento</button>
        </div>` : '';

    return `
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border-2 ${pendente ? 'border-warning' : 'border-primary'}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fs-5 fw-bold">Senha ${escapeHtml(pedido.senha)}</span>
                    ${pendente
                        ? '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> Pagar no caixa</span>'
                        : '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Pago</span>'}
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>${consumo} • ${escapeHtml(pedido.forma_pagamento)}</span>
                        <span><i class="bi bi-clock"></i> ${escapeHtml(pedido.hora_pedido)}</span>
                    </div>
                    <ul class="list-group mb-2">${itens}</ul>
                    <div class="text-end fw-bold">Total: R$ ${escapeHtml(pedido.total_formatado)}</div>
                </div>
                ${rodape}
            </div>
        </div>`;
}

async function acaoPedidoTotem(acao, idPedido) {
    const res = await fetch(`../api/caixa_actions.php?acao=${acao}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_pedido: idPedido })
    });
    const data = await res.json();
    if (!data.sucesso) alert(data.erro);
    carregarPedidosTotem();
}

function confirmarPagamentoTotem(idPedido, senha, total) {
    if (!confirm(`Confirma o recebimento de R$ ${total} em dinheiro da senha ${senha}?`)) return;
    acaoPedidoTotem('confirmar_pagamento_totem', idPedido);
}

function cancelarPedidoTotem(idPedido) {
    if (!confirm('Cancelar este pedido não pago? Os itens serão removidos.')) return;
    acaoPedidoTotem('cancelar_pedido_totem', idPedido);
}

async function atualizarMapaMesas() {
    if (atualizandoMapa) return;
    atualizandoMapa = true;
    try {
        const res = await fetch('api_status_mesas.php', { cache: 'no-store' });
        const data = await res.json();
        if (!data.sucesso) return;
        data.mesas.forEach(mesa => {
            const card = document.querySelector(`[data-mesa-id="${Number(mesa.id)}"]`);
            if (!card) return;
            const info = card.querySelector('.mesa-info');
            const statusAnterior = info.dataset.status;
            const valorAnterior = Number(info.dataset.valor || 0);
            const cozinhaAnterior = Number(info.dataset.cozinha || 0);
            const pedidoAnterior = Number(info.dataset.idPedido || 0);
            const valor = Number(mesa.valor_parcial || 0);
            const cozinha = Number(mesa.qtd_cozinha || 0);
            const idPedido = Number(mesa.id_pedido || 0);
            const status = card.querySelector('.mesa-status-label');
            const fechamento = mesa.status === 'fechamento';
            status.textContent = fechamento ? 'FECHAMENTO' : mesa.status.toUpperCase();
            status.className = `badge mesa-status-label ${fechamento ? 'bg-warning text-dark' : mesa.status === 'ocupada' ? 'bg-danger' : 'bg-success'}`;
            card.classList.remove('mesa-livre','mesa-ocupada','mesa-fechamento','border-success','border-danger','border-warning');
            card.classList.add(fechamento ? 'mesa-fechamento' : mesa.status === 'ocupada' ? 'mesa-ocupada' : 'mesa-livre', fechamento ? 'border-warning' : mesa.status === 'ocupada' ? 'border-danger' : 'border-success');
            info.dataset.status = mesa.status;
            info.dataset.valor = String(valor);
            info.dataset.cozinha = String(cozinha);
            info.dataset.idPedido = String(idPedido);
            info.querySelector('.mesa-livre-texto').classList.toggle('d-none', mesa.status !== 'livre');
            const valorEl = info.querySelector('.mesa-valor');
            valorEl.textContent = `R$ ${valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            valorEl.classList.toggle('d-none', mesa.status === 'livre');
            info.querySelector('.mesa-qtd-cozinha').textContent = cozinha;
            info.querySelector('.mesa-max-espera').textContent = Number(mesa.max_espera || 0);
            info.querySelector('.mesa-cozinha').classList.toggle('d-none', mesa.status === 'livre' || cozinha === 0);
            info.querySelector('.mesa-entregues').classList.toggle('d-none', mesa.status === 'livre' || cozinha > 0);

            const mudouStatus = statusAnterior !== mesa.status;
            const novoLancamento = valor > valorAnterior || cozinha > cozinhaAnterior || idPedido !== pedidoAnterior;
            const pulso = (mudouStatus && ['ocupada', 'fechamento'].includes(mesa.status)) || novoLancamento;
            if (pulso) {
                card.classList.remove('mesa-atualizada');
                void card.offsetWidth;
                card.classList.add('mesa-atualizada');
                setTimeout(() => card.classList.remove('mesa-atualizada'), 2200);
            }
            if (Number(mesaAtualId) === Number(mesa.id) && modalElement.classList.contains('show') && (mudouStatus || novoLancamento || cozinha !== cozinhaAnterior)) {
                carregarDadosMesa(mesaAtualId);
            }
            const linkTablet = document.querySelector(`[data-tablet-mesa="${Number(mesa.id)}"]`);
            if (linkTablet) {
                const texto = linkTablet.querySelector('.mesa-tablet-status');
                texto.textContent = fechamento ? 'Fechamento' : mesa.status === 'ocupada' ? 'Ocupada' : 'Livre';
                texto.className = `mesa-tablet-status ${fechamento ? 'text-warning-emphasis' : mesa.status === 'ocupada' ? 'text-danger' : 'text-success'}`;
            }
        });
    } catch (e) { /* Mantém o estado mais recente já exibido. */ }
    finally { atualizandoMapa = false; }
}

carregarPedidosTotem();
setInterval(carregarPedidosTotem, 20000);
atualizarMapaMesas();
setInterval(atualizarMapaMesas, 3000);
