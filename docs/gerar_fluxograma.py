"""Gera docs/fluxograma_pdv.drawio (abrir no draw.io / diagrams.net)."""
from xml.sax.saxutils import quoteattr

S = {
    'tela': 'rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;arcSize=12;',
    'modal': 'rounded=1;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;arcSize=12;',
    'plan': 'rounded=1;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#6c8ebf;dashed=1;arcSize=12;',
    'api': 'rounded=0;whiteSpace=wrap;html=1;fillColor=#f5f5f5;strokeColor=#666666;',
    'dec': 'rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;',
    'erro': 'rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;arcSize=20;',
    'alt': 'rounded=1;whiteSpace=wrap;html=1;fillColor=#ffe6cc;strokeColor=#d79b00;arcSize=20;',
    'db': 'shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;size=10;fillColor=#d5e8d4;strokeColor=#82b366;fontStyle=1;',
    'dbplan': 'shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;size=10;fillColor=#ffffff;strokeColor=#82b366;dashed=1;fontStyle=1;',
    'ini': 'ellipse;whiteSpace=wrap;html=1;fillColor=#333333;fontColor=#ffffff;strokeColor=#000000;',
    'nota': 'shape=note;whiteSpace=wrap;html=1;size=14;fillColor=#fffde7;strokeColor=#bbbbbb;align=left;spacingLeft=6;',
    'titulo': 'text;html=1;fontSize=20;fontStyle=1;align=left;verticalAlign=middle;',
}
E = {
    'n': 'edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow=block;endFill=1;strokeColor=#333333;fontSize=11;labelBackgroundColor=#ffffff;',
    'erro': 'edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow=block;endFill=1;strokeColor=#b85450;fontColor=#b85450;fontSize=11;labelBackgroundColor=#ffffff;',
    'alt': 'edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow=block;endFill=1;strokeColor=#d79b00;fontColor=#9c6500;dashed=1;fontSize=11;labelBackgroundColor=#ffffff;',
    'crud': 'edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow=open;dashed=1;strokeColor=#82b366;fontColor=#2d7600;fontStyle=1;fontSize=12;labelBackgroundColor=#ffffff;',
}


class Pagina:
    def __init__(self, nome, w=1900, h=1500):
        self.nome, self.w, self.h = nome, w, h
        self.cells, self.n = [], 0

    def _id(self):
        self.n += 1
        return f'{self.nome[:2]}{self.n}'.replace(' ', '')

    def no(self, tipo, x, y, w, h, label, extra=''):
        i = self._id()
        self.cells.append(f'<mxCell id="{i}" value={quoteattr(label)} style={quoteattr(S[tipo] + extra)} vertex="1" parent="1">'
                          f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>')
        return i

    def seta(self, a, b, label='', tipo='n', extra='', pts=None):
        i = self._id()
        geo = '<mxGeometry relative="1" as="geometry">'
        if pts:
            geo += '<Array as="points">' + ''.join(f'<mxPoint x="{x}" y="{y}"/>' for x, y in pts) + '</Array>'
        geo += '</mxGeometry>'
        self.cells.append(f'<mxCell id="{i}" value={quoteattr(label)} style={quoteattr(E[tipo] + extra)} edge="1" parent="1" source="{a}" target="{b}">{geo}</mxCell>')
        return i

    def legenda(self, x, y):
        self.no('nota', x, y, 300, 230, '', 'fillColor=#ffffff;')
        itens = [('tela', 'Tela'), ('modal', 'Modal / janela'), ('plan', 'Tela planejada (não implementada)'),
                 ('api', 'Processamento / API'), ('dec', 'Decisão / validação'), ('erro', 'Fluxo de exceção (erro)'),
                 ('alt', 'Fluxo alternativo'), ('db', 'Tabela do banco')]
        self.no('titulo', x + 10, y + 4, 200, 22, 'Legenda', 'fontSize=13;')
        for k, (t, txt) in enumerate(itens):
            yy = y + 30 + k * 24
            self.no(t, x + 12, yy, 46 if t != 'dec' else 46, 18, '')
            self.no('titulo', x + 68, yy - 2, 230, 22, txt, 'fontSize=11;fontStyle=0;')
        self.no('titulo', x + 10, y + 222 - 10, 290, 20,
                '<font color="#2d7600"><b>C</b>reate · <b>R</b>ead · <b>U</b>pdate · <b>D</b>elete</font> (seta verde tracejada)',
                'fontSize=10;fontStyle=0;')

    def xml(self, idx):
        return (f'<diagram id="p{idx}" name={quoteattr(self.nome)}><mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" '
                f'tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="{self.w}" pageHeight="{self.h}" math="0" shadow="0">'
                '<root><mxCell id="0"/><mxCell id="1" parent="0"/>' + ''.join(self.cells) + '</root></mxGraphModel></diagram>')


def L(titulo, *linhas):
    return f'<b>{titulo}</b>' + ''.join(f'<br><font style="font-size:10px">{l}</font>' for l in linhas)


paginas = []

# ---------------------------------------------------------------- 1. Visão geral
p = Pagina('1 - Visão geral da navegação', 1700, 960)
paginas.append(p)
p.no('titulo', 20, 10, 1200, 40, 'Sistema PDV Restaurante — Visão geral da navegação')
ini = p.no('ini', 30, 250, 90, 60, 'Início')
dper = p.no('dec', 170, 230, 150, 100, 'Quem está<br>usando?')
totem = p.no('tela', 420, 80, 250, 80, L('Totem de autoatendimento', 'totem/index.php', 'R: categorias, produtos · C: pedidos, itens_pedido'))
login = p.no('plan', 420, 240, 250, 80, L('Login do caixa (planejado)', 'caixa/login.php', 'R: usuarios (tabela planejada)'))
dcred = p.no('dec', 760, 230, 160, 100, 'Usuário e senha<br>preenchidos e<br>válidos?')
elog = p.no('erro', 740, 400, 200, 80, L('Exceção', 'Campo obrigatório vazio ou<br>"Usuário ou senha inválidos"'))
mapa = p.no('tela', 1010, 240, 250, 80, L('Mapa de Mesas (caixa)', 'caixa/index.php', 'R/U: mesas · C/R/U: pedidos · R/U: itens_pedido'))
tablet = p.no('plan', 1010, 80, 250, 80, L('Tablet da mesa (não implementado)', 'tablet/index.php?mesa=N'))
prod = p.no('tela', 1370, 130, 280, 80, L('Produtos &amp; Cardápio', 'caixa/produtos.php', 'CRUD: produtos, categorias · R: itens_pedido'))
dash = p.no('tela', 1370, 360, 280, 80, L('Dashboard BI', 'caixa/dashboard.php', 'R: pedidos, itens_pedido, produtos'))
p.seta(ini, dper)
p.seta(dper, totem, 'Cliente', pts=[(245, 120)])
p.seta(dper, login, 'Operador')
p.seta(login, dcred, 'Entrar')
p.seta(dcred, elog, 'Não', 'erro')
p.seta(elog, login, 'Corrigir', 'erro', pts=[(545, 440)])
p.seta(dcred, mapa, 'Sim')
p.seta(mapa, totem, 'Abrir Totem (nova aba)', 'alt', 'exitX=0.25;exitY=0;entryX=1;entryY=0.75;', pts=[(1072, 200), (700, 200), (700, 140)])
p.seta(mapa, tablet, 'Simular Tablet', 'alt', 'exitX=0.75;exitY=0;entryX=0.75;entryY=1;')
p.seta(mapa, prod, 'menu', 'n', 'exitX=1;exitY=0.25;entryX=0;entryY=0.5;')
p.seta(mapa, dash, 'menu', 'n', 'exitX=1;exitY=0.75;entryX=0;entryY=0.5;')
p.seta(prod, dash, 'Dashboard BI', 'n', 'exitX=0.5;exitY=1;entryX=0.5;entryY=0;')
p.seta(dash, mapa, 'Voltar ao Caixa', 'n', 'exitX=0.25;exitY=1;entryX=0.5;entryY=1;', pts=[(1440, 500), (1135, 500)])
p.no('nota', 30, 520, 640, 150,
     '<b>Observações</b><br>• Detalhamento de cada fluxo nas páginas 2 a 5.<br>'
     '• Matriz completa de acesso às tabelas (CRUD) na página 6.<br>'
     '• O login e a tabela <i>usuarios</i> estão planejados: hoje as telas do caixa abrem sem autenticação.<br>'
     '• O tablet da mesa ainda não foi desenvolvido; a tabela <i>feedbacks</i> não tem tela.')
dbs = ['mesas', 'categorias', 'produtos', 'pedidos', 'itens_pedido', 'feedbacks']
for k, t in enumerate(dbs):
    p.no('db', 740 + k * 120, 600, 100, 70, t)
p.no('dbplan', 740 + 6 * 120, 600, 100, 70, 'usuarios<br><font style="font-size:9px;font-weight:normal">(planejada)</font>')
p.no('titulo', 740, 560, 600, 30, 'Banco de dados (MySQL)', 'fontSize=14;')
p.legenda(30, 700)

# ---------------------------------------------------------------- 2. Totem
p = Pagina('2 - Totem: pedido e pagamento', 1700, 1560)
paginas.append(p)
p.no('titulo', 20, 10, 1200, 40, 'Totem — pedido, consumo local / para viagem e pagamento')
ini = p.no('ini', 470, 60, 100, 50, 'Cliente toca<br>na tela')
t1 = p.no('tela', 400, 140, 240, 70, L('T1 · Boas-vindas', 'Escolha: "Comer aqui" ou "Para viagem"'))
d1 = p.no('dec', 440, 240, 160, 90, 'Opção de<br>consumo?')
t2 = p.no('tela', 400, 380, 240, 80, L('T2 · Cardápio + Carrinho', 'abas de categoria, produtos ativos,', 'carrinho com +/− e remover'))
m1 = p.no('modal', 400, 510, 240, 70, L('M1 · Produto', 'quantidade 1–50 · observação até 255'))
d2 = p.no('dec', 440, 620, 160, 90, 'Carrinho<br>vazio?')
t3 = p.no('tela', 400, 760, 240, 80, L('T3 · Fechamento / Pagamento', 'resumo, total, Crédito · Débito · PIX · Dinheiro'))
d3 = p.no('dec', 430, 880, 180, 100, 'Forma de<br>pagamento<br>escolhida?')
a1 = p.no('api', 400, 1020, 240, 60, L('POST api/totem_actions.php', 'acao=finalizar_pedido'))
d4 = p.no('dec', 410, 1110, 220, 120, 'Validação no<br>servidor OK?')
a2 = p.no('api', 400, 1270, 240, 70, L('Transação no banco', "pedido origem='totem', status_conta='fechada'", "itens status_item='na_cozinha'"))
t4 = p.no('tela', 400, 1390, 240, 70, L('T4 · Confirmação', 'senha do pedido, consumo, pagamento, total'))
fim = p.no('ini', 470, 1490, 100, 50, 'Fim')

p.seta(ini, t1)
p.seta(t1, d1)
p.seta(d1, t2, "Comer aqui<br>tipo_consumo='local'", 'n', 'exitX=0;exitY=0.5;entryX=0.25;entryY=0;', pts=[(400, 285), (400, 350), (460, 350)])
p.seta(d1, t2, "Para viagem<br>tipo_consumo='viagem'", 'n', 'exitX=1;exitY=0.5;entryX=0.75;entryY=0;', pts=[(640, 285), (640, 350), (580, 350)])
p.seta(t2, m1, 'toca no produto')
p.seta(m1, t2, 'Adicionar ao carrinho', 'n', 'exitX=1;exitY=0.5;entryX=1;entryY=0.75;', pts=[(700, 545), (700, 440)])
p.seta(t2, d2, '"Ir para pagamento"', 'n', 'exitX=0;exitY=0.75;entryX=0;entryY=0.5;', pts=[(340, 440), (340, 665)])
p.seta(d2, t3, 'Não')
e1 = p.no('erro', 760, 625, 230, 80, L('Exceção', 'botão "Ir para pagamento" fica', 'desabilitado até ter 1 item'))
p.seta(d2, e1, 'Sim', 'erro')
p.seta(e1, t2, 'continua no cardápio', 'erro', 'exitX=0.5;exitY=0;entryX=1;entryY=0.95;', pts=[(875, 456)])
p.seta(t3, d3, 'escolhe forma')
e2 = p.no('erro', 760, 890, 230, 80, L('Exceção', 'botão "Confirmar pagamento"', 'desabilitado'))
p.seta(d3, e2, 'Não', 'erro')
p.seta(e2, t3, '', 'erro', 'exitX=0.5;exitY=0;entryX=1;entryY=0.5;', pts=[(875, 800)])
p.seta(d3, a1, 'Sim · "Confirmar"')
p.seta(a1, d4)
e3 = p.no('erro', 760, 1120, 260, 100, L('Exceção — mensagem em T3', 'consumo ≠ local/viagem · forma inválida', 'carrinho vazio ou &gt; 50 itens · qtd fora de 1–50',
                                        'produto inexistente / indisponível'))
p.seta(d4, e3, 'Não', 'erro')
p.seta(e3, t3, 'tenta de novo', 'erro', 'exitX=1;exitY=0.5;entryX=1;entryY=0.25;', pts=[(1060, 1170), (1060, 780)])
p.seta(d4, a2, 'Sim')
e4 = p.no('erro', 760, 1270, 260, 70, L('Exceção', 'erro de banco → ROLLBACK', '"Não foi possível concluir o pedido"'))
p.seta(a2, e4, 'falha', 'erro')
p.seta(e4, e3, '', 'erro', 'exitX=0.5;exitY=0;entryX=0.5;entryY=1;')
p.seta(a2, t4, 'commit')
p.seta(t4, fim)

# fluxos alternativos (esquerda)
alt1 = p.no('alt', 40, 380, 220, 60, L('Alternativo', '"Trocar" consumo no cabeçalho'))
alt2 = p.no('alt', 40, 470, 220, 70, L('Alternativo', '"Cancelar" → confirm() se houver', 'itens → limpa carrinho'))
alt3 = p.no('alt', 40, 760, 220, 60, L('Alternativo', '"Voltar ao cardápio" (mantém carrinho)'))
alt4 = p.no('alt', 40, 1390, 220, 70, L('Alternativo', '15 s ou "Novo pedido"', '→ reinicia o totem'))
alt5 = p.no('alt', 40, 150, 220, 70, L('Alternativo', '120 s sem toque em qualquer etapa', '→ volta a T1 e limpa carrinho'))
p.seta(t2, alt1, '', 'alt', 'exitX=0;exitY=0.25;entryX=1;entryY=0.5;', pts=[(330, 400), (330, 410)])
p.seta(alt1, t1, 'T1', 'alt', 'exitX=0.25;exitY=0;entryX=0;entryY=0.75;', pts=[(95, 330), (300, 330), (300, 192)])
p.seta(t2, alt2, '', 'alt', 'exitX=0;exitY=0.5;entryX=1;entryY=0.5;', pts=[(310, 420), (310, 505)])
p.seta(alt2, t1, 'T1', 'alt', 'exitX=0;exitY=0.5;entryX=0;entryY=0.25;', pts=[(20, 505), (20, 120), (370, 120), (370, 157)])
p.seta(t3, alt3, '', 'alt', 'exitX=0;exitY=0.5;entryX=1;entryY=0.5;')
p.seta(alt3, t2, 'T2', 'alt', 'exitX=0.5;exitY=0;entryX=0;entryY=1;', pts=[(150, 600), (400, 600)])
p.seta(t4, alt4, '', 'alt', 'exitX=0;exitY=0.5;entryX=1;entryY=0.5;')
p.seta(alt4, t1, 'T1', 'alt', 'exitX=0;exitY=0.5;entryX=0;entryY=0.5;', pts=[(10, 1425), (10, 100), (380, 100), (380, 175)])
p.seta(alt5, t1, '', 'alt', 'exitX=1;exitY=0.5;entryX=0;entryY=0.5;')

# tabelas
cat = p.no('db', 1180, 330, 110, 70, 'categorias')
pro = p.no('db', 1180, 450, 110, 70, 'produtos')
ped = p.no('db', 1180, 1250, 110, 70, 'pedidos')
itp = p.no('db', 1340, 1250, 110, 70, 'itens_pedido')
p.seta(t2, cat, 'R (com produto ativo)', 'crud', 'exitX=1;exitY=0.15;entryX=0;entryY=0.5;', pts=[(1100, 392), (1100, 365)])
p.seta(t2, pro, 'R (ativo = 1)', 'crud', 'exitX=1;exitY=0.4;entryX=0;entryY=0.5;', pts=[(1120, 412), (1120, 485)])
p.seta(a1, pro, 'R (preço vem do banco)', 'crud', 'exitX=1;exitY=0.5;entryX=0.5;entryY=1;', pts=[(1150, 1050), (1150, 560), (1235, 560)])
p.seta(a2, ped, 'C', 'crud', 'exitX=0.5;exitY=1;entryX=0.5;entryY=1;', pts=[(520, 1360), (1235, 1360)])
p.seta(a2, itp, 'C', 'crud', 'exitX=0.75;exitY=1;entryX=0.5;entryY=1;', pts=[(580, 1375), (1395, 1375)])
p.legenda(1350, 60)
p.no('nota', 1180, 620, 330, 150, '<b>Regras de negócio</b><br>• Só produtos com <i>ativo = 1</i> aparecem e são aceitos.<br>'
     '• Preço é sempre lido do banco (o navegador envia só id e qtd).<br>• Pedido nasce pago: status_conta = fechada.<br>'
     '• Itens vão para a cozinha (na_cozinha).<br>• Senha = nº do pedido (3 dígitos).')

# ---------------------------------------------------------------- 3. Caixa – mesas
p = Pagina('3 - Caixa: mapa de mesas e conta', 1800, 1380)
paginas.append(p)
p.no('titulo', 20, 10, 1200, 40, 'Caixa — mapa de mesas, entrega de itens e fechamento de conta')
ini = p.no('ini', 540, 60, 120, 50, 'Operador<br>no caixa')
c1 = p.no('tela', 480, 140, 240, 80, L('C1 · Mapa de Mesas', 'cards livre/ocupada, itens na cozinha,', 'maior espera, valor parcial'))
tc1 = p.no('db', 800, 135, 190, 90, 'mesas — R<br>pedidos — R<br>itens_pedido — R', 'fontStyle=0;')
a0 = p.no('api', 480, 260, 240, 60, L('GET caixa_actions.php', 'acao=detalhes_mesa'))
ta0 = p.no('db', 800, 255, 190, 70, 'pedidos, itens_pedido,<br>produtos — R', 'fontStyle=0;')
d0 = p.no('dec', 520, 350, 160, 90, 'Mesa válida<br>e existe?')
e0 = p.no('erro', 800, 360, 230, 70, L('Exceção', '"Erro ao carregar mesa" no modal'))
d1 = p.no('dec', 520, 480, 160, 90, 'Tem conta<br>aberta?')
m1 = p.no('modal', 140, 620, 240, 70, L('M1 · Mesa livre', 'botão "Abrir mesa"'))
a1 = p.no('api', 140, 730, 240, 60, L('POST caixa_actions.php', 'acao=abrir_mesa'))
d2 = p.no('dec', 180, 830, 160, 100, 'Mesa existe<br>e está livre?')
e1 = p.no('erro', 140, 980, 240, 70, L('Exceção (alert)', '"Mesa não encontrada" /', '"A mesa já está aberta"'))
pr1 = p.no('api', 400, 845, 200, 70, L('Abre a mesa', 'mesa → ocupada', "novo pedido origem='web', aberta"))
tp1 = p.no('db', 410, 960, 180, 70, 'mesas — U<br>pedidos — C', 'fontStyle=0;')
m2 = p.no('modal', 920, 620, 260, 80, L('M2 · Conta da mesa', 'itens, status cozinha/entregue,', 'total parcial, forma de pagamento'))
a2 = p.no('api', 860, 760, 220, 60, L('POST caixa_actions.php', 'acao=marcar_entregue'))
d3 = p.no('dec', 890, 855, 160, 100, 'Item na cozinha<br>e conta aberta?')
e2 = p.no('erro', 860, 1000, 220, 70, L('Exceção (alert)', '"Item não encontrado ou já entregue"'))
pr2 = p.no('api', 650, 870, 200, 70, L('Entrega o item', "status_item='entregue'", 'timestamp_entrega=NOW()'))
tp2 = p.no('db', 660, 980, 180, 60, 'itens_pedido — U', 'fontStyle=0;')
d4 = p.no('dec', 1210, 750, 170, 100, 'Operador<br>confirma?<br>(confirm)')
a3 = p.no('api', 1185, 890, 220, 60, L('POST caixa_actions.php', 'acao=fechar_conta'))
d5 = p.no('dec', 1210, 980, 170, 100, 'Forma de<br>pagamento<br>válida?')
e3 = p.no('erro', 1450, 995, 220, 70, L('Exceção (alert)', '"Forma de pagamento inválida"'))
pr3 = p.no('api', 1185, 1130, 220, 90, L('Transação', 'pedido → fechada, total recalculado,', 'forma, data_fechamento', 'mesa → livre'))
tp3 = p.no('db', 1200, 1260, 190, 70, 'pedidos — U<br>mesas — U', 'fontStyle=0;')
alt = p.no('alt', 140, 140, 240, 70, L('Alternativo', 'fechar modal (X) → volta ao mapa', 'sem alterar dados'))

p.seta(ini, c1)
p.seta(alt, c1, '', 'alt')
p.seta(c1, tc1, 'R', 'crud')
p.seta(c1, a0, 'clica numa mesa')
p.seta(a0, ta0, 'R', 'crud')
p.seta(a0, d0)
p.seta(d0, e0, 'Não', 'erro')
p.seta(d0, d1, 'Sim')
p.seta(d1, m1, 'Não (livre)', 'n', 'exitX=0;exitY=0.5;entryX=0.5;entryY=0;', pts=[(260, 525)])
p.seta(d1, m2, 'Sim (ocupada)', 'n', 'exitX=1;exitY=0.5;entryX=0.5;entryY=0;', pts=[(1050, 525)])
p.seta(m1, a1, '"Abrir mesa"')
p.seta(a1, d2)
p.seta(d2, e1, 'Não', 'erro')
p.seta(d2, pr1, 'Sim')
p.seta(pr1, tp1, 'U / C', 'crud')
p.seta(pr1, c1, 'recarrega mapa', 'n', 'exitX=0.5;exitY=0;entryX=0;entryY=0.75;', pts=[(500, 760), (440, 760), (440, 200)])
p.seta(m2, a2, '"Entregue"', 'n', 'exitX=0.25;exitY=1;entryX=0.5;entryY=0;', pts=[(985, 740), (970, 740)])
p.seta(a2, d3)
p.seta(d3, e2, 'Não', 'erro')
p.seta(d3, pr2, 'Sim')
p.seta(pr2, tp2, 'U', 'crud')
p.seta(pr2, m2, 'atualiza modal', 'n', 'exitX=0.5;exitY=0;entryX=0;entryY=0.5;', pts=[(750, 660)])
p.seta(m2, d4, '"Fechar conta"', 'n', 'exitX=1;exitY=0.5;entryX=0.5;entryY=0;', pts=[(1295, 660)])
p.seta(d4, m2, 'Não', 'alt', 'exitX=0;exitY=0.5;entryX=0.75;entryY=1;', pts=[(1115, 800)])
p.seta(d4, a3, 'Sim')
p.seta(a3, d5)
p.seta(d5, e3, 'Não', 'erro')
p.seta(d5, pr3, 'Sim')
p.seta(pr3, tp3, 'U', 'crud')
p.seta(pr3, c1, '"Conta fechada e<br>mesa liberada!"<br>→ recarrega mapa', 'n', 'exitX=1;exitY=0.5;entryX=0.85;entryY=0;',
       pts=[(1740, 1175), (1740, 122), (684, 122)])
p.legenda(1100, 140)

# ---------------------------------------------------------------- 4. Produtos e categorias
p = Pagina('4 - Caixa: produtos e categorias', 1300, 1260)
paginas.append(p)
p.no('titulo', 20, 10, 1200, 40, 'Caixa — cadastro de produtos e categorias (caixa/produtos.php)')
p0 = p.no('tela', 420, 70, 280, 80, L('P1 · Produtos &amp; Cardápio (aba Produtos)', 'busca, filtro por categoria e status,', 'lista com miniatura'))
tp0 = p.no('db', 60, 75, 180, 70, 'produtos — R<br>categorias — R', 'fontStyle=0;')
m1 = p.no('modal', 60, 220, 250, 80, L('M1 · Formulário de produto', 'nome*, categoria*, preço, descrição,', 'URL da imagem, disponível'))
d1 = p.no('dec', 95, 350, 180, 110, 'Campos<br>válidos?')
e1 = p.no('erro', 60, 510, 250, 100, L('Exceção (alerta vermelho)', 'nome obrigatório · categoria inválida', 'ou inexistente · preço negativo', 'URL de imagem inválida'))
s1 = p.no('api', 340, 375, 200, 60, L('Salva produto', 'sucesso → volta a P1'))
ts1 = p.no('db', 350, 480, 180, 60, 'produtos — C / U', 'fontStyle=0;')
s2 = p.no('api', 480, 220, 180, 60, L('Alternar status', 'ativo 1 ↔ 0 · volta a P1'))
ts2 = p.no('db', 480, 300, 180, 55, 'produtos — U', 'fontStyle=0;')
d2 = p.no('dec', 780, 200, 160, 90, 'Confirma<br>exclusão?')
d3 = p.no('dec', 780, 340, 160, 100, 'Produto já<br>vendido?')
td3 = p.no('db', 590, 365, 150, 55, 'itens_pedido — R', 'fontStyle=0;')
e2 = p.no('erro', 980, 350, 260, 80, L('Exceção', '"Este produto já foi vendido e não pode', 'ser excluído. Use Alternar Status"'))
s3 = p.no('api', 760, 490, 200, 60, L('Exclui produto', 'sucesso → volta a P1'))
ts3 = p.no('db', 1000, 492, 160, 55, 'produtos — D', 'fontStyle=0;')

p.seta(p0, tp0, 'R', 'crud')
p.seta(p0, m1, '"Novo produto" / "Editar"', 'n', 'exitX=0.15;exitY=1;entryX=0.5;entryY=0;', pts=[(462, 185), (185, 185)])
p.seta(m1, d1, '"Salvar"')
p.seta(d1, e1, 'Não', 'erro')
p.seta(e1, m1, 'corrige', 'erro', 'exitX=0;exitY=0.5;entryX=0;entryY=0.5;', pts=[(30, 560), (30, 260)])
p.seta(d1, s1, 'Sim')
p.seta(s1, ts1, 'C / U', 'crud')
p.seta(p0, s2, '"Alternar status"', 'n', 'exitX=0.5;exitY=1;entryX=0.5;entryY=0;', pts=[(560, 185), (570, 185)])
p.seta(s2, ts2, 'U', 'crud')
p.seta(p0, d2, '"Excluir"', 'n', 'exitX=1;exitY=0.5;entryX=0.5;entryY=0;', pts=[(860, 110)])
p.seta(d2, d3, 'Sim')
p.seta(d2, p0, 'Não', 'alt', 'exitX=1;exitY=0.5;entryX=1;entryY=0.2;', pts=[(990, 245), (990, 86)])
p.seta(d3, td3, 'R', 'crud')
p.seta(d3, e2, 'Sim', 'erro')
p.seta(d3, s3, 'Não')
p.seta(s3, ts3, 'D', 'crud')

con = p.no('alt', 60, 680, 280, 45, L('Em P1: clicar na aba "Categorias"'))
c0 = p.no('tela', 60, 770, 280, 70, L('P2 · Aba Categorias', 'lista por ordem, nº de produtos'))
tc0 = p.no('db', 400, 775, 200, 60, 'categorias — R<br>produtos — R (contagem)', 'fontStyle=0;')
m2 = p.no('modal', 60, 890, 250, 70, L('M2 · Formulário de categoria', 'nome*, ordem'))
d4 = p.no('dec', 95, 1000, 180, 100, 'Nome<br>preenchido?')
e3 = p.no('erro', 340, 1020, 240, 60, L('Exceção', '"O nome da categoria é obrigatório"'))
s4 = p.no('api', 95, 1150, 180, 60, L('Salva categoria', 'sucesso → volta a P2'))
ts4 = p.no('db', 320, 1152, 170, 55, 'categorias — C / U', 'fontStyle=0;')
d5 = p.no('dec', 700, 880, 180, 110, 'Categoria tem<br>produtos?')
e4 = p.no('erro', 930, 895, 270, 80, L('Exceção', '"Não é possível excluir uma categoria', 'que possui produtos vinculados"'))
s5 = p.no('api', 700, 1040, 180, 60, L('Exclui categoria', 'sucesso → volta a P2'))
ts5 = p.no('db', 930, 1042, 170, 55, 'categorias — D', 'fontStyle=0;')
p.seta(con, c0)
p.seta(c0, tc0, 'R', 'crud')
p.seta(c0, m2, '"Nova" / "Editar"', 'n', 'exitX=0.4;exitY=1;entryX=0.45;entryY=0;')
p.seta(m2, d4, '"Salvar"')
p.seta(d4, e3, 'Não', 'erro')
p.seta(e3, m2, 'corrige', 'erro', 'exitX=0.5;exitY=0;entryX=1;entryY=0.5;', pts=[(460, 925)])
p.seta(d4, s4, 'Sim')
p.seta(s4, ts4, 'C / U', 'crud')
p.seta(c0, d5, '"Excluir" + confirm()', 'n', 'exitX=0.9;exitY=1;entryX=0.5;entryY=0;', pts=[(312, 862), (790, 862)])
p.seta(d5, e4, 'Sim', 'erro')
p.seta(d5, s5, 'Não')
p.seta(s5, ts5, 'D', 'crud')
p.legenda(960, 600)

# ---------------------------------------------------------------- 5. Dashboard
p = Pagina('5 - Caixa: dashboard BI', 1300, 760)
paginas.append(p)
p.no('titulo', 20, 10, 1200, 40, 'Caixa — Dashboard BI (caixa/dashboard.php)')
db0 = p.no('tela', 380, 80, 280, 80, L('D1 · Dashboard BI', 'KPIs, faturamento por dia, ranking de', 'produtos, tempo médio de entrega'))
dd = p.no('dec', 430, 210, 180, 100, 'Período?')
cu = p.no('api', 700, 225, 200, 70, L('Personalizado', 'data início e data fim'))
dd2 = p.no('dec', 700, 340, 200, 100, 'Início &gt; fim?')
inv = p.no('alt', 700, 480, 200, 60, L('Alternativo', 'datas trocadas automaticamente'))
cal = p.no('api', 380, 470, 280, 80, L('Calcula indicadores', 'somente contas fechadas, por data_fechamento;', 'tempo de entrega = pedido → entrega do item'))
tcal = p.no('db', 410, 610, 220, 90, 'pedidos — R<br>itens_pedido — R<br>produtos — R', 'fontStyle=0;')
nav1 = p.no('alt', 40, 80, 220, 40, L('menu → Produtos (P1)'))
nav2 = p.no('alt', 40, 140, 220, 40, L('menu → Mapa de Mesas (C1)'))
p.seta(db0, dd, 'filtro')
p.seta(dd, cu, 'Personalizado')
p.seta(cu, dd2)
p.seta(dd2, inv, 'Sim', 'alt')
p.seta(dd2, cal, 'Não', 'n', 'exitX=0;exitY=0.5;entryX=1;entryY=0.25;', pts=[(680, 390), (680, 490)])
p.seta(inv, cal, '', 'alt', 'exitX=0;exitY=0.5;entryX=1;entryY=0.75;')
p.seta(dd, cal, 'Hoje / 7 dias / 30 dias')
p.seta(cal, tcal, 'R', 'crud')
p.seta(cal, db0, 'atualiza gráficos', 'n', 'exitX=0;exitY=0.5;entryX=0;entryY=0.75;', pts=[(320, 510), (320, 140)])
p.seta(db0, nav1, '', 'alt', 'exitX=0;exitY=0.25;entryX=1;entryY=0.5;')
p.seta(db0, nav2, '', 'alt', 'exitX=0;exitY=0.5;entryX=1;entryY=0.5;', pts=[(300, 120), (300, 160)])
p.legenda(960, 80)

# ---------------------------------------------------------------- 5. Matriz CRUD
p = Pagina('6 - Matriz CRUD (telas x tabelas)', 1500, 600)
paginas.append(p)
p.no('titulo', 20, 10, 1200, 40, 'Matriz CRUD — quais telas acessam quais tabelas')
cols = ['mesas', 'categorias', 'produtos', 'pedidos', 'itens_pedido', 'feedbacks', 'usuarios*']
linhas = [
    ('Totem (totem/index.php + api/totem_actions.php)', ['', 'R', 'R', 'C', 'C', '', '']),
    ('Mapa de Mesas (caixa/index.php + api/caixa_actions.php)', ['R U', '', 'R', 'C R U', 'R U', '', '']),
    ('Produtos &amp; Cardápio (caixa/produtos.php)', ['', 'C R U D', 'C R U D', '', 'R', '', '']),
    ('Dashboard BI (caixa/dashboard.php)', ['', '', 'R', 'R', 'R', '', '']),
    ('Login do caixa* (planejado)', ['', '', '', '', '', '', 'R']),
    ('Tablet da mesa* (planejado)', ['R', 'R', 'R', 'C R', 'C R', 'C', '']),
]
th = 'style="background:#dae8fc;border:1px solid #6c8ebf;padding:8px;"'
td = 'style="border:1px solid #999;padding:8px;text-align:center;font-weight:bold;color:#2d7600;"'
tl = 'style="border:1px solid #999;padding:8px;text-align:left;"'
html = '<table style="border-collapse:collapse;font-size:13px;width:100%;"><tr><th ' + th + '>Tela</th>' + ''.join(f'<th {th}>{c}</th>' for c in cols) + '</tr>'
for nome, vals in linhas:
    html += f'<tr><td {tl}>{nome}</td>' + ''.join(f'<td {td}>{v or "—"}</td>' for v in vals) + '</tr>'
html += '</table>'
p.no('titulo', 20, 70, 1440, 330, html, 'verticalAlign=top;fontStyle=0;whiteSpace=wrap;overflow=fill;')
p.no('nota', 20, 380, 900, 170,
     '<b>Detalhe das operações</b><br>'
     '• <b>C</b> pedidos/itens_pedido no totem: pedido já fechado (pago) e itens na cozinha.<br>'
     '• <b>C</b> pedidos no caixa: abrir mesa. <b>U</b> pedidos: fechar conta (total, forma, data_fechamento).<br>'
     '• <b>U</b> itens_pedido: marcar como entregue. <b>U</b> mesas: ocupada ao abrir, livre ao fechar.<br>'
     '• <b>R</b> itens_pedido em Produtos: impede excluir produto já vendido.<br>'
     '• * Planejado: tabela <i>usuarios</i> ainda não existe no database.sql; tablet e feedbacks sem tela.')

xml = '<mxfile host="app.diagrams.net" type="device">' + ''.join(pg.xml(i) for i, pg in enumerate(paginas)) + '</mxfile>'
open(__file__.replace('gerar_fluxograma.py', 'fluxograma_pdv.drawio'), 'w', encoding='utf-8').write(xml)
print('ok', len(paginas), 'páginas')
