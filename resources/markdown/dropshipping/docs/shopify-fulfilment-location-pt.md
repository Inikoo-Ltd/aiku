---
title: A localização de expedição AW no Shopify
summary: O que fazem a localização aiku- e o perfil de envio AW Connect shipping na tua loja Shopify, que tarifas de envio os teus clientes veem para os nossos produtos, e o que fazer quando os produtos aparecem esgotados ou as encomendas não nos chegam.
date: 2026-10-30
source_date: 2026-10-30
tags: shopify, localização de expedição, perfil de envio, tarifas de envio, mercados, stock, esgotado
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Quando instalas a nossa aplicação, adicionamos à tua loja Shopify uma localização de expedição chamada <b>aiku-</b> e um perfil de envio, <b>AW Connect shipping</b>, com os nossos produtos e os nossos preços de envio. No checkout os teus clientes veem o envio <b>AW Standard</b> para os nossos produtos. Normalmente não precisas de fazer nada.
</aside>

## Para que serve a localização

O Shopify guarda o stock por localização. Os nossos produtos são enviados a partir do nosso armazém, por isso adicionamos o nosso armazém à tua loja como uma localização de expedição.

- O seu nome é <b>aiku-</b>, depois o código do nosso site, e depois o código do teu canal entre parênteses. Por exemplo <b>aiku-awd (sho-ab12cd-3e)</b>.
- O stock de cada produto que ligas é mantido nesta localização. Nós atualizamo-lo por ti.
- Quando um cliente compra um destes produtos, o Shopify envia-nos um pedido de expedição a partir desta localização. É assim que a encomenda nos chega.

Não apagues esta localização e não movas os nossos produtos para outra localização. Se o fizeres, o stock deixa de atualizar e as encomendas deixam de nos chegar.

## Envio configurado por nós

Ao instalar a aplicação adicionamos um perfil de envio à tua loja. O nome é <b>AW Connect shipping</b>, depois o código do teu canal entre parênteses, por exemplo <b>AW Connect shipping (sho-ab12cd-3e)</b>. Funciona da mesma forma quer a tua loja use perfis de envio quer envio por mercados.

- Envia apenas a partir da localização <b>aiku-</b>.
- Contém cada produto que ligas a partir de nós. Adicionamos os produtos novos e retiramos os removidos por ti, normalmente em dez minutos.
- As tarifas são os nossos preços de envio, por país e peso da encomenda, na moeda da tua loja. No checkout aparecem como <b>AW Standard</b>. Quando mudamos os nossos preços, o perfil acompanha num dia.
- Onde calculamos o envio à mão (encomendas muito pesadas, países fora das nossas zonas), o perfil usa o nosso preço mais alto para essa zona, para que os teus clientes possam sempre comprar. O que te cobramos pela encomenda não muda.
- A Shopify não te deixa editar estas tarifas. Podes desativá-las, mas então os teus clientes não veem nenhum envio para os nossos produtos, a não ser que uma das tuas próprias tarifas envie a partir da localização <b>aiku-</b>.

Os teus outros produtos mantêm as tuas definições de envio e as tuas tarifas. Não as alteramos.

## Lojas com envio por mercados

A Shopify está a passar todas as lojas dos perfis de envio para o envio por mercados. Uma loja assim mostra <b>O envio foi movido para Mercados</b> (<b>Shipping has moved to Markets</b>) em <b>Definições</b>, <b>Envio e entrega</b>. O nosso perfil aparece em cada mercado como um serviço da nossa aplicação, ao lado das tuas próprias tarifas.

Se uma das tarifas dos teus mercados enviar a partir da localização <b>aiku-</b>, por exemplo porque a adicionaste à mão como dizia uma versão antiga deste guia, os teus clientes veem essa tarifa além de <b>AW Standard</b>. Para mostrar só a nossa, retira a localização <b>aiku-</b> dessa tarifa:

1. No teu admin Shopify, abre <b>Mercados</b> (<b>Markets</b>) e depois um mercado para onde vendes.
2. Em <b>Envio</b> (<b>Shipping</b>), junto a uma tarifa, carrega em <b>...</b> e escolhe <b>Editar</b>.
3. Em <b>Envia de</b> (<b>Ships from</b>), retira a localização <b>aiku-</b> e guarda.

Os nossos produtos só são vendidos para os países que os teus mercados incluem. Um cliente num país que nenhum dos teus mercados cobre vê-os esgotados.

## O teu próprio stock dos nossos produtos

Os produtos do nosso perfil de envio só saem da localização <b>aiku-</b>, por isso a Shopify não os envia a partir das tuas próprias localizações. Se tiveres alguns dos nossos produtos contigo, por exemplo um artigo por usar que um cliente te devolveu, coloca-os à venda na tua loja como um produto separado e envia tu essas encomendas.

## Verifica tu próprio

Faz isto se os nossos produtos aparecerem esgotados na tua loja, ou se o checkout não mostrar tarifa de envio para eles.

1. No teu admin Shopify, abre <b>Definições</b>.
2. Abre <b>Envio e entrega</b>.
3. Procura <b>AW Connect shipping</b>, debaixo da nossa aplicação. Tem de enviar a partir da localização <b>aiku-</b> e conter os nossos produtos.
4. Se faltar, ou faltar um produto nele, espera um dia: verificamos cada loja todas as noites. Se não voltar, contacta-nos.

<!-- screenshot: Shopify Shipping and delivery, o perfil de envio AW Connect shipping debaixo da aplicação, com a localização aiku-awd -->

A Shopify muda os seus menus de vez em quando, por isso os nomes podem ser um pouco diferentes no teu admin.

## Quando algo corre mal

**Os nossos produtos aparecem esgotados no Shopify.** Verifica o perfil de envio <b>AW Connect shipping</b> como acima, e que o país do cliente está num dos teus mercados. Verifica também que o produto está ligado: em <b>Meus Produtos</b> tem de mostrar um aperto de mão verde. Vê [Gerir produtos no Shopify](/docs/managing-products-on-shopify).

**Erro ao carregar "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent"** ("Sem localização Shopify, o serviço de expedição AW não está instalado nesta loja, por isso o stock não pode ser enviado"). Falta a localização <b>aiku-</b>. Abre o canal. Se vires <b>Click here to install</b> ("Clique aqui para instalar"), prime-o e instala a aplicação no Shopify. Se o canal mostrar <b>Reiniciar canal</b>, usa-o para criar a localização outra vez.

**Mensagem no registo "The specified inventory item is not stocked at the location"** ("O artigo de inventário indicado não tem stock nesta localização"). O produto no Shopify não tem stock na localização <b>aiku-</b>, por exemplo porque foi movido para outra localização no Shopify. Volta a ligar o produto com <b>Conecte-se com outros produtos</b> em <b>Meus Produtos</b>.

**As encomendas não nos chegam.** O Shopify só nos envia encomendas de artigos com stock na localização <b>aiku-</b>. Se o produto tinha stock na tua própria localização, o Shopify espera que o envies tu próprio. Vê [As tuas encomendas do Shopify e o seu estado](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Onde clicar</strong>
<ul>
<li><b>Verificar o nosso perfil de envio:</b> admin Shopify → <b>Definições</b> → <b>Envio e entrega</b> → <b>AW Connect shipping</b>.</li>
<li><b>Envio por mercados:</b> admin Shopify → <b>Mercados</b> → o teu mercado → <b>Envio</b>.</li>
<li><b>Verificar o canal:</b> <b>Canais</b> → a tua loja Shopify → os três ícones junto ao seu nome.</li>
</ul>
</aside>
