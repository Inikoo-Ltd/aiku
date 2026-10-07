---
title: As tuas encomendas do Shopify e o seu estado
summary: Como as encomendas da tua loja Shopify nos chegam, como são pagas, o que cada estado significa, porque é que uma encomenda pode ficar em espera como Submitted ou não chegar de todo, e o que enviamos de volta ao Shopify.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, encomendas, estado, pagamento, submitted, não pago, pedido de expedição
category: orders
series: shopify
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Quando um cliente compra um produto ligado na tua loja Shopify, o Shopify envia-nos um pedido de expedição e a encomenda aparece em <b>Orders</b> do teu canal. Pagamo-la com o teu saldo, depois com o teu cartão guardado. Uma encomenda paga segue para o nosso armazém sozinha. Uma encomenda que não conseguimos pagar fica <b>Submitted</b> e <b>Unpaid</b> até a pagares. Quando a enviamos, marcamo-la como cumprida no Shopify com o número de rastreio.
</aside>

## Como uma encomenda nos chega

1. Um cliente compra um dos teus produtos ligados no Shopify.
2. O Shopify envia um pedido de expedição desses artigos para o local <b>aiku-</b>.
3. Aceitamos o pedido e criamos a encomenda no teu canal. Encontras-a no teu canal, em <b>Orders</b>.

Só os produtos ligados em <b>My Products</b> podem chegar até nós. Se uma encomenda tiver alguns dos nossos produtos e alguns teus próprios, aceitamos os nossos produtos e tu envias o resto tu próprio.

## Como a encomenda é paga

Tentamos pagar cada encomenda nova de imediato:

1. Primeiro com o teu saldo.
2. Se o saldo não for suficiente, com os cartões guardados em <b>Saved Cards</b>, pela tua ordem de prioridade.

Se o pagamento resultar, a encomenda segue para o nosso armazém sozinha. Se não, a encomenda fica em espera e enviamos-te um e-mail a dizer que está em espera. Na maioria das vezes isto acontece porque não há nenhum cartão guardado. Para não acontecer outra vez, guarda um cartão: vê [Cartões de pagamento e opções](/docs/payment-cards-and-options).

## Pagar uma encomenda que está em espera

Uma encomenda que não conseguimos pagar mostra <b>Unpaid</b> junto ao seu número e fica <b>Submitted</b>.

1. Recarrega o teu saldo com pelo menos o valor em falta, em <b>Top Up</b> no menu.
2. Abre o teu canal, <b>Orders</b>, e abre a encomenda.
3. Clica em <b>Pay ... with balance</b>. O botão só aparece quando o teu saldo cobre o valor em falta.

A encomenda segue então para o nosso armazém sozinha.

<!-- screenshot: uma encomenda por pagar com a etiqueta Unpaid e o botão Pay with balance -->

## O que cada estado significa

- <b>Submitted</b>: temos a encomenda. Se também mostrar <b>Unpaid</b>, está à espera do teu pagamento.
- <b>In Warehouse</b>: paga e à espera de ser separada.
- <b>Handling</b>: a ser separada.
- <b>Waiting</b>: o armazém teve de parar a encomenda por um momento antes de poder continuar.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: a encomenda está a ser preparada.
- <b>Finalized</b>: faturada e pronta para sair.
- <b>Dispatched</b>: enviada. Se alguns artigos não puderam ser enviados, vês <b>Modified</b> e o dinheiro deles é reembolsado automaticamente.
- <b>Cancelled</b>: a encomenda não vai ser enviada. O motivo é mostrado no topo da encomenda.

Para mais sobre a página da encomenda, vê [Rever as tuas encomendas](/docs/reviewing-orders).

## O que enviamos de volta ao Shopify

- Quando a encomenda é despachada, marcamo-la como cumprida no Shopify com o número e a ligação de rastreio. O Shopify avisa o teu cliente.
- Quando uma encomenda é cancelada, fechamos o pedido no Shopify. Numa encomenda cancelada podes clicar no botão de sincronizar (<b>Sync order state</b>) para enviares o cancelamento ao Shopify outra vez. Se o Shopify já estiver atualizado vês <b>The order state on Shopify is up-to-date</b>.

## Uma encomenda não está nas minhas Orders

Abre o canal e clica em <b>Fetch orders</b>. Isto <b>Checks Shopify for orders that have not reached us yet</b> ("Verifica no Shopify encomendas que ainda não nos chegaram"): olha para as encomendas recentes não expedidas e traz as do nosso local. Se não houver nada de novo vês <b>No new orders</b>. Podes clicar outra vez passados alguns minutos.

Se a encomenda continuar sem aparecer, verifica isto:

- Os produtos estão ligados (aperto de mão verde) em <b>My Products</b>.
- Os artigos têm stock no local <b>aiku-</b> no Shopify, e o local está no teu perfil de envio. Vê [O local de expedição AW no Shopify](/docs/shopify-fulfilment-location).
- A encomenda não foi já cumprida no Shopify, nem enviada para outro local.

## Quando algo corre mal

**Uma encomenda cancelada diz "Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio."** ("Pedido de expedição recusado: os artigos não podem ser expedidos porque não os tens no teu portefólio.") Nenhum dos produtos da encomenda está ligado em <b>My Products</b>. Adiciona-os e liga-os, depois pede a expedição outra vez no Shopify.

**Uma encomenda cancelada diz "Fulfilment request declined: Order don't have shipping information".** ("Pedido de expedição recusado: a encomenda não tem informação de envio.") A encomenda no Shopify não tem morada de entrega. Adiciona a morada no Shopify e pede a expedição outra vez.

**O pedido de expedição foi aceite no Shopify, mas não consigo ver a encomenda para pagar.** Abre <b>Orders</b> no canal: é lá que as encomendas do Shopify estão listadas. Procura a encomenda com <b>Unpaid</b>, ou clica em <b>Fetch orders</b>.

**A minha encomenda de teste no Shopify não chegou.** Uma encomenda de teste só nos chega se tiver produtos ligados com stock no local <b>aiku-</b>. Tem cuidado: uma encomenda que nos chega é uma encomenda real. Pagamo-la e enviamo-la. Se fizeste uma por engano, pede-nos rapidamente no chat do nosso site para a cancelarmos. Só podemos cancelar antes de ser despachada.

**A encomenda fica Submitted e Unpaid.** Não havia saldo suficiente e nenhum cartão funcionou. Paga-a como mostrado em <b>Pagar uma encomenda que está em espera</b>, e guarda um cartão para as próximas.

**A encomenda diz "We cannot deliver to ...".** ("Não podemos entregar em ...") Não entregamos nesse país a partir deste site. A encomenda não é paga nem enviada. Atualiza a morada de entrega, ou pergunta-nos no chat do nosso site.

**A encomenda está paga mas não se mexe há muito tempo.** Pergunta-nos no chat do nosso site, com o número da encomenda. O número da encomenda é a <b>Reference</b> em <b>Orders</b>.

<aside class="wayfinder"><strong>Onde clicar</strong>
<ul>
<li><b>Ver as tuas encomendas do Shopify:</b> <b>Channels</b> → a tua loja Shopify → <b>Orders</b>.</li>
<li><b>Pagar uma encomenda em espera:</b> abre a encomenda → <b>Pay ... with balance</b>.</li>
<li><b>Trazer uma encomenda em falta:</b> abre o canal → <b>Fetch orders</b>.</li>
<li><b>Guardar um cartão:</b> <b>Saved Cards</b> no menu.</li>
</ul>
</aside>
