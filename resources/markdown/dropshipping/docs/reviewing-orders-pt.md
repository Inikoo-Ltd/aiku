---
title: Rever as tuas encomendas
summary: Encontra as encomendas de cada canal de vendas, lê o seu estado, vê o que foi enviado e o que pagaste, e compreende porque é que uma encomenda está por pagar, cancelada ou não aparece de todo.
date: 2026-09-25
source_date: 2026-09-25
tags: encomendas, estado da encomenda, por pagar, cancelada, lista de encomendas
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
As encomendas ficam guardadas por canal de vendas. No menu esquerdo, abre o teu canal e clica em <b>Orders</b>. Cada encomenda mostra o seu estado, e uma etiqueta vermelha <b>Unpaid</b> quando ainda não conseguimos cobrar o dinheiro. Uma encomenda por pagar fica em espera e não é enviada ao armazém enquanto não for paga. Clica na referência da encomenda para veres os produtos, a morada de entrega, o número de rastreio e a fatura.
</aside>

## Onde estão as tuas encomendas

Cada canal que ligaste (Shopify, eBay, TikTok, WooCommerce e os outros, e o teu canal manual) tem a sua própria lista de encomendas.

1. No menu esquerdo, encontra o teu canal em <b>Channels</b>.
2. Clica em <b>Orders</b> por baixo dele.

A lista tem estas colunas: <b>Status</b>, <b>Reference</b>, <b>Client</b>, <b>Date</b>, <b>Items</b> e <b>Total</b>. As encomendas mais recentes ficam no topo. Usa a caixa de pesquisa para encontrar uma encomenda pela sua referência.

A <b>Reference</b> é o nosso número de encomenda. Não é o número de encomenda da tua loja nem um número de rastreio. Para encontrares o número de rastreio, consulta [Encontrar o número de rastreio de uma encomenda](/docs/tracking-numbers).

Num canal Manual/API, as encomendas que ainda estás a preparar não estão nesta lista. Estão em <b>Baskets</b>, no mesmo canal, até as submeteres.

Para descarregar a lista, usa o botão de exportar no topo da página e escolhe <b>Excel</b> ou <b>CSV</b>.

<!-- captura de ecrã: a lista de Orders de um canal, com a coluna Status, uma referência com a etiqueta vermelha Unpaid e o botão de exportar -->

## O que significa cada estado

- <b>Submitted</b>: temos a encomenda. Se não estiver paga, fica aqui até ser paga.
- <b>In Warehouse</b>: a encomenda está paga e à espera de ser separada.
- <b>Picking</b>: o armazém está a separar os produtos.
- <b>Waiting</b>: a separação está em pausa, por exemplo enquanto o armazém verifica um produto.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: a encomenda está a ser preparada.
- <b>Finalized</b>: a encomenda está faturada e pronta a sair.
- <b>Dispatched</b>: a encomenda saiu do nosso armazém. O número de rastreio está na encomenda.
- <b>Cancelled</b>: a encomenda não vai ser enviada.

Junto à referência também podes ver pequenos ícones para <b>Premium dispatch</b>, <b>Extra packing</b> e <b>Insurance</b> quando os escolheste nessa encomenda.

## Encomendas por pagar

Uma etiqueta vermelha <b>Unpaid</b> significa que ainda não conseguimos cobrar o valor total. A encomenda fica <b>Submitted</b> e não é enviada ao armazém.

Quando uma encomenda chega da tua loja, pagamo-la assim:

1. Primeiro com o teu saldo.
2. Se o saldo não for suficiente, com os teus cartões guardados, começando pelo teu cartão predefinido.

Se nenhum funcionar, a encomenda fica em espera e enviamos-te um email a dizer que está suspensa. Na maioria das vezes isto acontece porque não há nenhum cartão guardado para o canal. Para evitar que volte a acontecer, guarda um cartão: consulta [Pagar as tuas encomendas](/docs/topping-up-and-paying-with-balance).

Para pagar uma encomenda que está em espera:

1. Carrega o teu saldo com pelo menos o valor em dívida. Consulta [Pagar as tuas encomendas](/docs/topping-up-and-paying-with-balance).
2. Abre a encomenda novamente. Uma caixa amarela diz <b>Order ... is not paid yet</b> e mostra <b>Your balance</b>.
3. Clica no botão <b>Pay ... with balance</b>. Mostra o valor em dívida.

A encomenda segue então para o armazém. O botão só aparece quando o teu saldo cobre a totalidade do valor em dívida, enquanto a encomenda está <b>Submitted</b> ou <b>Picking</b>.

<b>Nota:</b> não voltamos a tentar o teu cartão por nossa conta. Uma encomenda em espera fica em espera até a pagares.

## Dentro de uma encomenda

Clica na referência da encomenda para a abrir. Vês:

- No topo, uma linha temporal com os passos que a encomenda já passou, e uma etiqueta <b>Paid</b> ou <b>Unpaid</b>.
- O teu cliente: nome, email, telefone e morada de entrega.
- <b>Weight</b>: o peso estimado de todos os produtos.
- <b>Delivery Notes</b>: as encomendas físicas (parcels), o seu estado, e em <b>Shipments</b> a transportadora e o número de rastreio. O ícone de PDF (<b>Download Picking List</b>) descarrega a lista de produtos da encomenda física.
- <b>Invoices</b>: a nossa fatura da encomenda, para abrir ou descarregar em PDF. Consulta [As tuas faturas](/docs/invoices).
- O resumo de preços: <b>Items</b>, custos, <b>Net</b>, imposto e <b>Total</b>.

Estes são os custos extra neste site:

{order_charges}

- A aba <b>Transactions</b>: cada produto com a sua <b>Quantity</b>. Quando foram enviadas menos unidades do que as encomendadas, a quantidade enviada é mostrada a vermelho por cima da quantidade encomendada, que fica riscada.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> e <b>Other Instructions</b>. As instruções de entrega são impressas na etiqueta de envio.

<!-- captura de ecrã: uma página de encomenda a mostrar a linha temporal, a caixa Delivery Notes com um número de rastreio e a caixa Invoices -->

## Quando nem tudo foi enviado

Por vezes não conseguimos enviar todos os produtos, por exemplo quando um esgota enquanto separamos a encomenda. A página da encomenda mostra então <b>Dispatched | Modified</b>, e na lista aparece um ícone de aviso amarelo junto ao estado. A aba <b>Transactions</b> mostra quais os produtos que não foram enviados. O dinheiro dos produtos que não enviámos volta ao teu saldo automaticamente quando a encomenda é faturada.

## Encomendas canceladas

Uma encomenda cancelada mostra o estado <b>Cancelled</b> no topo, e uma caixa vermelha <b>Order cancelled</b> quando foi registada uma razão. O dinheiro que já pagaste por ela volta ao teu saldo.

Numa encomenda do Shopify há também um botão de sincronização (dica <b>Sync order state</b>) no topo. Clica nele para avisar o Shopify de que a encomenda foi cancelada. Se vires <b>The order state on Shopify is up-to-date</b>, o Shopify já sabe.

Não há botão de cancelar. Para cancelar uma encomenda, pergunta-nos no chat do nosso site com a referência da encomenda. Só podemos cancelá-la antes de ser despachada. Depois de embalada pode já ser tarde.

## Deixar uma avaliação

Em alguns dos nossos sites, algum tempo depois de uma encomenda ser despachada, aparece um botão <b>Review</b> no topo da encomenda. Usa-o para avaliares a encomenda e os produtos.

## Quando algo corre mal

**Uma encomenda da minha loja não está na lista.** Verifica estes pontos, por esta ordem:

- Só chegam os produtos que estão em <b>My Products</b> desse canal. Se nenhum produto da encomenda estiver em <b>My Products</b>, a encomenda não aparece em <b>Orders</b>, porque não há nada que possamos enviar.
- Verifica se estás a olhar para o canal certo. Cada canal tem a sua própria lista.
- O canal tem de continuar ligado. Se a página do canal disser que não está ligado, volta a ligá-lo primeiro.
- **Shopify**: só chegam encomendas que o Shopify envia para a nossa localização de fulfilment, e chegam como um pedido de fulfilment. Se um produto da encomenda não estiver em <b>My Products</b>, essa parte do pedido é recusada no Shopify e o resto chega. Quando todo o pedido é recusado (nenhum dos produtos está em <b>My Products</b>, ou a encomenda não tem morada de entrega), a encomenda aparece em <b>Orders</b> como <b>Cancelled</b>, com a razão em <b>Notes from Staff</b>. Corrige a encomenda no Shopify e pede o fulfilment de novo. Uma encomenda que é entregue pela tua própria loja, já entregue, ou cujo pedido foi cancelado no Shopify, não chega. Esta é a razão mais comum para uma encomenda de teste não chegar: verifica no Shopify se os seus produtos têm stock na nossa localização.

**O meu pedido de fulfilment do Shopify foi aceite mas não consigo ver a encomenda para pagar.** Procura em <b>Orders</b> do canal Shopify pelo seu estado e por uma etiqueta vermelha <b>Unpaid</b>. Se não estiver lá, pergunta-nos no chat do nosso site com o número de encomenda do Shopify.

**A encomenda está Submitted há muito tempo.** Está quase de certeza por pagar. Segue os passos em "Encomendas por pagar" acima.

**A encomenda diz "We cannot deliver to ...".** Não enviamos para esse país a partir deste site. Consulta [Países para onde não entregamos](/docs/delivery-restrictions).

**Um produto chegou partido, ou o meu comprador quer devolver algo.** Pergunta-nos no chat do nosso site com a referência da encomenda e fotos. O teu comprador não deve devolver nada antes de isso ser combinado connosco no chat. Os reembolsos vão para o teu saldo.
