"""Gera docs/prototipos.html e .pdf com as telas reais anotadas (regras de negócio)."""
import base64, html, json
from playwright.sync_api import sync_playwright

BASE = 'http://localhost:8080'
W, H = 1366, 900


def caixas(page, marcadores):
    out = []
    for sel, titulo, regra in marcadores:
        loc = page.locator(sel).first
        b = loc.bounding_box()
        out.append({'box': b, 'titulo': titulo, 'regra': regra})
    return out


def shot(page):
    return base64.b64encode(page.screenshot()).decode()


telas = []
with sync_playwright() as p:
    br = p.chromium.launch(executable_path='/home/ubuntu/.local/bin/google-chrome')
    page = br.new_page(viewport={'width': W, 'height': H})

    # ---------- Totem: cardápio
    page.goto(BASE + '/totem/index.php')
    page.click('[data-consumo="viagem"]')
    page.locator('#gridProdutos [data-produto]').first.click()
    page.wait_for_selector('#modalProduto.show')
    page.click('#modalProdutoMais')
    page.fill('#modalProdutoObs', 'sem cebola')
    page.wait_for_timeout(300)
    telas.append({
        'titulo': 'Totem — escolha do produto (modal)',
        'descricao': 'Cliente toca num produto do cardápio e define quantidade e observação antes de colocar no carrinho.',
        'img': shot(page),
        'marcas': caixas(page, [
            ('#modalProdutoNome', 'Produto e preço', 'Dados de <b>produtos</b> (nome, descrição, imagem_url, preço). Só aparecem produtos com <code>ativo = 1</code>.'),
            ('#modalProdutoQtd', 'Quantidade', 'Mínimo 1 e máximo 50 por item; validado de novo no servidor.'),
            ('#modalProdutoObs', 'Observação', 'Texto livre opcional (ex.: “sem cebola”), gravado em <code>itens_pedido.observacao</code> com até 255 caracteres.'),
            ('#modalProdutoAdicionar', 'Adicionar', 'Subtotal = quantidade × preço. O item vai para o carrinho; nada é gravado no banco ainda.'),
        ]),
    })
    page.click('#modalProdutoAdicionar')
    page.wait_for_timeout(400)
    page.locator('#gridProdutos [data-produto]').nth(1).click()
    page.wait_for_selector('#modalProduto.show')
    page.click('#modalProdutoAdicionar')
    page.wait_for_timeout(500)
    telas.append({
        'titulo': 'Totem — cardápio e carrinho',
        'descricao': 'Tela principal do autoatendimento: o cliente navega pelas categorias, monta o carrinho e segue para o pagamento.',
        'img': shot(page),
        'marcas': caixas(page, [
            ('#labelConsumo', 'Tipo de consumo', '“Comer aqui” ou “Para viagem”, escolhido na tela inicial. Pode ser trocado a qualquer momento e é gravado em <code>pedidos.tipo_consumo</code> (local | viagem).'),
            ('#listaCategorias', 'Categorias', 'Lidas de <b>categorias</b>, na ordem do campo <code>ordem</code>. Só aparecem categorias com pelo menos um produto ativo.'),
            ('#gridProdutos [data-produto]', 'Produtos', 'Lidos de <b>produtos</b> com <code>ativo = 1</code>. Produto desativado no caixa some do totem.'),
            ('#carrinhoItens', 'Carrinho', 'Botões +/− (1 a 50) e remover. Mostra a observação de cada item.'),
            ('#carrinhoTotal', 'Total', 'Soma de quantidade × preço. O servidor recalcula com o preço do banco, ignorando o valor enviado pelo navegador.'),
            ('#btnIrPagamento', 'Ir para pagamento', 'Fica desabilitado enquanto o carrinho estiver vazio (fluxo de exceção).'),
            ('#btnCancelar', 'Cancelar', 'Pede confirmação se houver itens e volta à tela inicial. Após 120 s sem toque o totem também reinicia.'),
        ]),
    })

    # ---------- Totem: pagamento (com exemplo de exceção)
    page.click('#btnIrPagamento')
    page.click('[data-pagamento="Cartão Crédito"]')
    page.evaluate("""() => { const e = document.getElementById('erroPagamento');
        e.textContent = 'Um dos produtos não está mais disponível.'; e.classList.remove('d-none'); }""")
    page.wait_for_timeout(300)
    telas.append({
        'titulo': 'Totem — fechamento e pagamento',
        'descricao': 'Resumo do pedido e escolha da forma de pagamento. A mensagem vermelha mostra um exemplo de exceção devolvida pelo servidor.',
        'img': shot(page),
        'marcas': caixas(page, [
            ('#resumoConsumo', 'Consumo', 'Confirma se o pedido é para comer no local ou para viagem.'),
            ('#resumoItens', 'Resumo', 'Itens, quantidades, observações e subtotais. Leitura do carrinho.'),
            ('#opcoesPagamento', 'Forma de pagamento', 'Lista fechada: Cartão Crédito, Cartão Débito, PIX ou Dinheiro. Outro valor é recusado pela API.'),
            ('#instrucaoPagamento', 'Instrução', 'Orientação conforme a forma escolhida (maquininha, QR Code PIX ou pagar no caixa).'),
            ('#erroPagamento', 'Exceção', 'Erros da validação no servidor aparecem aqui: produto indisponível, quantidade inválida, forma inválida ou carrinho vazio. Nada é gravado.'),
            ('#btnConfirmarPagamento', 'Confirmar pagamento', 'Só habilita após escolher a forma. Grava numa transação: <b>pedidos</b> (C) com origem=totem e status_conta=fechada, e <b>itens_pedido</b> (C) com status na_cozinha.'),
            ('#btnVoltarCardapio', 'Voltar', 'Fluxo alternativo: volta ao cardápio mantendo o carrinho.'),
        ]),
    })

    # ---------- Caixa: mapa de mesas
    page.goto(BASE + '/caixa/index.php')
    page.wait_for_timeout(500)
    ocupada = '.mesa-ocupada:has(.alert-danger)'
    telas.append({
        'titulo': 'Caixa — mapa de mesas',
        'descricao': 'Painel do operador: situação de cada mesa em tempo real, com itens na cozinha, tempo de espera e valor parcial.',
        'img': shot(page),
        'marcas': caixas(page, [
            ('nav.navbar .navbar-nav', 'Menu', 'Navegação entre Mapa de Mesas, Produtos &amp; Cardápio e Dashboard BI.'),
            ('.mesa-livre', 'Mesa livre', 'Status lido de <code>mesas.status</code>. Ao clicar, permite abrir a mesa: mesas (U → ocupada) e pedidos (C, origem web, aberta). Mesa já aberta é recusada.'),
            (ocupada + ' .fs-5', 'Valor parcial', 'Soma de quantidade × preço_unitario dos <b>itens_pedido</b> da conta aberta.'),
            (ocupada + ' .alert-danger', 'Itens na cozinha', 'Quantidade de itens com status na_cozinha e maior tempo de espera em minutos.'),
            ('.d-flex.gap-3 .badge', 'Legenda', 'Verde = livre, vermelho = ocupada, amarelo = pediu a conta.'),
        ]),
    })

    # ---------- Caixa: conta da mesa
    page.locator(ocupada).first.click()
    page.wait_for_selector('#selectPagamento')
    page.wait_for_timeout(600)
    telas.append({
        'titulo': 'Caixa — conta da mesa e fechamento',
        'descricao': 'Modal aberto ao clicar numa mesa ocupada: acompanha a cozinha, registra entregas e fecha a conta.',
        'img': shot(page),
        'marcas': caixas(page, [
            ('#modalMesaCorpo .list-group-item', 'Item na cozinha', 'Itens com status na_cozinha. O selo fica vermelho quando a espera passa de 15 min. Mostra a observação do cliente.'),
            ('#modalMesaCorpo .list-group-item .btn-success', 'Marcar entregue', 'Atualiza <b>itens_pedido</b> (U): status entregue e timestamp_entrega. Só vale para item na cozinha de conta aberta.'),
            ('#modalMesaCorpo table', 'Itens entregues', 'Horário do pedido, da entrega e subtotal de cada item.'),
            ('#modalMesaCorpo .alert-secondary', 'Total da conta', 'Recalculado no servidor a partir dos itens no momento do fechamento.'),
            ('#selectPagamento', 'Forma de pagamento', 'Dinheiro, Cartão Crédito, Cartão Débito ou PIX. Valor fora da lista é recusado.'),
            ('#modalMesaRodape .btn-primary', 'Fechar conta', 'Pede confirmação. Em transação: <b>pedidos</b> (U → fechada, total, forma, data_fechamento) e <b>mesas</b> (U → livre).'),
        ]),
    })
    br.close()

# ---------- HTML
ESC = 1000 / W
secoes = []
for n, t in enumerate(telas, 1):
    marcas_html, lista_html = '', ''
    for k, m in enumerate(t['marcas'], 1):
        b = m['box']
        x, y, w, h = (b['x'] * ESC, b['y'] * ESC, b['width'] * ESC, b['height'] * ESC)
        marcas_html += (f'<div class="area" style="left:{x:.0f}px;top:{y:.0f}px;width:{w:.0f}px;height:{h:.0f}px"></div>'
                        f'<div class="num" style="left:{max(x - 13, 0):.0f}px;top:{max(y - 13, 0):.0f}px">{k}</div>')
        lista_html += f'<li><span class="n">{k}</span><div><b>{m["titulo"]}</b><br>{m["regra"]}</div></li>'
    secoes.append(f'''
<section>
  <header><span class="tag">Protótipo {n}</span><h2>{html.escape(t["titulo"])}</h2><p>{html.escape(t["descricao"])}</p></header>
  <div class="corpo">
    <div class="tela" style="width:1000px;height:{H*ESC:.0f}px"><img src="data:image/png;base64,{t["img"]}" width="1000">{marcas_html}</div>
    <ol class="regras">{lista_html}</ol>
  </div>
</section>''')

doc = f'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Protótipos — PDV Restaurante</title>
<style>
@page {{ size: 1500px 960px; margin: 0; }}
* {{ box-sizing: border-box; }}
body {{ margin: 0; font-family: "Segoe UI", Roboto, Arial, sans-serif; color: #212529; background: #eef1f5; }}
.capa {{ height: 960px; display: flex; flex-direction: column; justify-content: center; padding: 0 120px; background: #1f2937; color: #fff; page-break-after: always; }}
.capa h1 {{ font-size: 46px; margin: 0 0 12px; }} .capa p {{ font-size: 20px; color: #cbd5e1; max-width: 1000px; line-height: 1.5; }}
section {{ width: 1500px; height: 960px; padding: 28px 36px; page-break-after: always; background: #eef1f5; }}
header h2 {{ margin: 6px 0 4px; font-size: 26px; }} header p {{ margin: 0 0 16px; color: #555; }}
.tag {{ background: #0d6efd; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: 13px; font-weight: 600; }}
.corpo {{ display: flex; gap: 24px; align-items: flex-start; }}
.tela {{ position: relative; flex: none; box-shadow: 0 6px 24px rgba(0,0,0,.18); border-radius: 6px; overflow: hidden; background: #fff; }}
.tela img {{ display: block; }}
.area {{ position: absolute; border: 2px solid #e8590c; background: rgba(232,89,12,.08); border-radius: 4px; }}
.num, .n {{ width: 26px; height: 26px; border-radius: 50%; background: #e8590c; color: #fff; font-weight: 700; font-size: 14px;
  display: flex; align-items: center; justify-content: center; flex: none; }}
.num {{ position: absolute; box-shadow: 0 0 0 2px #fff; }}
.regras {{ list-style: none; margin: 0; padding: 0; font-size: 13.5px; line-height: 1.4; }}
.regras li {{ display: flex; gap: 10px; background: #fff; border-radius: 8px; padding: 9px 11px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }}
code {{ background: #f1f3f5; padding: 0 4px; border-radius: 3px; font-size: 12.5px; }}
</style></head><body>
<div class="capa"><h1>Protótipos de alta fidelidade</h1>
<p>Sistema PDV Restaurante — telas essenciais do Totem de autoatendimento e do Caixa.<br>
Cada tela traz marcações numeradas com a regra de negócio aplicada e as tabelas do banco envolvidas
(C = criar, R = ler, U = atualizar, D = excluir).</p></div>
{''.join(secoes)}
</body></html>'''
open('prototipos.html', 'w', encoding='utf-8').write(doc)

with sync_playwright() as p:
    br = p.chromium.launch(executable_path='/home/ubuntu/.local/bin/google-chrome')
    pg = br.new_page(viewport={'width': 1500, 'height': 960})
    pg.goto('file://' + __import__('os').path.abspath('prototipos.html'))
    pg.pdf(path='out/prototipos.pdf', width='1500px', height='960px', print_background=True)
    for i, s in enumerate(pg.locator('section').all(), 1):
        s.screenshot(path=f'out/prototipo{i}.png')
    br.close()
print('ok', len(telas))
