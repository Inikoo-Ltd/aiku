---
title: Fazer encomendas manualmente
summary: Criar uma encomenda para um cliente num canal Manual/API, adicionar produtos, escolher as opções de entrega, pagar no checkout e acompanhar a encomenda até ser despachada.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, encomendas, cesto, checkout, pagamento, saldo, recolha, despacho
category: orders
series: manual
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Abre o cliente no teu canal Manual/API e prime <b>Create Order</b>. Abre-se um cesto: adiciona produtos com <b>Add products</b>, escolhe as opções de entrega e prime <b>Continue to Checkout</b>. Usamos primeiro o saldo da tua conta e pagas o resto com cartão. Quando a encomenda está paga, segue para o armazém e acompanhas-a em <b>Orders</b>.
</aside>

## Antes de começares

- Precisas de um canal Manual/API. Consulta [O canal Manual/API](/docs/manual-and-api-channel).
- A pessoa para quem envias tem de ser cliente desse canal. Consulta [Gerir clientes](/docs/managing-clients).

## Criar a encomenda

1. Abre o teu canal Manual/API e depois <b>Clients</b>.
2. Clica no nome do cliente. Abre-se a página do cliente.
3. Prime <b>Create Order</b>.

Abre-se o cesto da nova encomenda. No topo mostra o cliente, os seus contactos e a morada de entrega. Verifica o nome e a morada antes de continuares.

## Adicionar produtos

- <b>Add products</b> abre uma janela <b>Add products to Order</b>. Pesquisa por nome ou código do produto, escreve a quantidade e adiciona os produtos. Podes escolher qualquer produto que vendemos, não só os que estão em <b>My Products</b>.
- <b>Upload products</b> adiciona muitos produtos a partir de uma folha de cálculo. Descarrega o modelo (.xlsx) na janela, preenche as colunas <b>code</b> e <b>quantity</b>, e carrega-o.

Os produtos aparecem na lista. Podes alterar as quantidades ali, ou remover uma linha. O peso estimado da encomenda é mostrado junto à morada.

<!-- captura de ecrã: um cesto com o cliente e a morada no topo, os produtos na lista, as opções de entrega e o botão Continue to Checkout -->

## Escolher as opções de entrega

- <b>Collection</b>: ativa-a se tu, ou uma transportadora que reserves, forem recolher a encomenda no nosso armazém em vez de a enviarmos. Tem um custo extra. Quando está desativada, enviamos a encomenda para a morada mostrada. Prime <b>Edit</b> junto à morada para a alterar apenas nesta encomenda.
- Despacho mais rápido: no AW Dropship UK a opção chama-se <b>Same Day Dispatch</b>, no AW Dropship Europe <b>Premium Dispatch</b> e no AW Dropship España <b>Envío Premium</b>. Tem um custo extra. Lê o ícone de informação ao lado para conheceres as condições.
- <b>Extra protective packing for fragile items</b> (só no AW Dropship UK): embalagem extra para produtos frágeis. Tem um custo extra.
- <b>Delivery Instructions</b>: uma nota para a transportadora. <b>This message will be printed in shipping label</b>, por isso escreve-a para a transportadora, não para nós.
- <b>Other Instructions</b>: uma nota para a nossa equipa.

Os custos e o total da encomenda atualizam-se quando ativas uma opção.

Estes são os custos extra neste site:

{order_charges}

## Pagar

Se o saldo da tua conta cobrir toda a encomenda, o cesto mostra <b>Place order</b> em vez de <b>Continue to Checkout</b>. Prime-o e a encomenda é paga a partir do teu saldo. A nota diz <b>This is your final confirmation. You can pay totally with your current balance.</b>

Caso contrário:

1. Prime <b>Continue to Checkout</b>.
2. O checkout mostra o número da encomenda. Se tiveres algum saldo, diz-te quanto é pago com o saldo e pede-te para pagares o resto.
3. Em <b>Online payments</b>, introduz os dados do teu cartão e confirma. O teu banco pode pedir-te para aprovares o pagamento na sua aplicação ou com um código.
4. Quando o pagamento estiver concluído, a página diz <b>Payment done. Waiting for confirmation...</b> e depois abre a encomenda.

Prime <b>Back to basket</b> na página de checkout para alterares a encomenda antes de pagares.

## Encomendas por terminar: Baskets

Uma encomenda que criaste mas não pagaste fica em <b>Baskets</b>, no teu canal. O número junto a <b>Baskets</b> no menu mostra quantas tens. Abre uma para a terminar, ou prime <b>Delete</b> na sua linha (dica <b>Delete basket</b>) para a remover. Um cesto não é enviado ao armazém enquanto não for pago.

## Acompanhar as tuas encomendas

Abre <b>Orders</b> no teu canal. A lista mostra o <b>Status</b>, a <b>Reference</b>, o cliente, a <b>Date</b>, os artigos e o total. Clica numa encomenda para veres os seus produtos, notas de entrega, envios com ligações de rastreio e faturas.

O ícone de estado diz-te em que ponto está a encomenda. Passa o rato por cima para veres o nome:

- <b>Submitted</b>: recebemos a encomenda.
- <b>In Warehouse</b>, <b>Picking</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: a nossa equipa está a prepará-la.
- <b>Waiting</b>: está em espera no armazém.
- <b>Finalized</b>: pronta a sair.
- <b>Dispatched</b>: enviada. A ligação de rastreio está na encomenda.
- <b>Cancelled</b>: a encomenda foi cancelada.

Os ícones no topo de uma encomenda mostram as opções que escolheste: uma estrela para <b>Premium dispatch</b>, uma caixa para <b>Extra packing</b>.

Se não conseguirmos enviar alguns artigos, a encomenda mostra que <b>Some items are not being sent</b>. O dinheiro desses artigos é reembolsado automaticamente.

A página da encomenda lista as suas faturas com um botão de descarregar. Todas as tuas faturas também estão em <b>Invoices</b> no menu.

## Quando algo corre mal

- <b>Não vejo Create Order na página do cliente.</b> O botão só existe em clientes de um canal Manual/API. Nos canais ligados, as encomendas chegam da tua loja.
- <b>O cesto diz "We cannot deliver to …".</b> Não entregamos nesse país. Muda a morada de entrega, ou ativa <b>Collection</b> se organizares o transporte por ti.
- <b>O cesto diz que a tua morada de faturação está marcada como proibida.</b> Atualiza a morada de faturação na tua conta, ou pergunta-nos no chat do nosso site.
- <b>Continue to Checkout está cinzento e pede-me para carregar um ficheiro.</b> Escolheste um encarte impresso que precisa da tua arte final. Carrega o ficheiro para ele, ou remove o encarte, antes de fazeres o checkout.
- <b>O pagamento com cartão falhou.</b> O checkout diz <b>Something went wrong</b>. Verifica os dados do cartão e se o teu banco aprovou o pagamento, depois tenta de novo. Também podes carregar o teu saldo e pagar com ele.
- <b>O checkout diz "Payment still processing".</b> Não pagues outra vez. A encomenda é submetida automaticamente assim que o pagamento for confirmado.
- <b>O checkout diz "Order already submitted".</b> A encomenda já está paga. Abre-a em <b>Orders</b>.
- <b>A minha encomenda mostra Unpaid.</b> O pagamento não cobriu a encomenda. Adiciona dinheiro ao teu saldo com <b>Top Up</b>, abre a encomenda e prime <b>Pay … with balance</b>. O botão aparece quando o teu saldo cobre o valor em dívida. A encomenda segue então para o armazém.
- <b>Preciso de alterar ou cancelar uma encomenda que já paguei.</b> Não há botão de cancelar. Pergunta-nos no chat do nosso site assim que possível. Só podemos cancelá-la ou alterá-la antes de ser despachada. Depois de embalada pode já ser tarde.
