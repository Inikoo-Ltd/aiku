---
title: A localização de expedição AW no Shopify
summary: O que a localização aiku- na tua loja Shopify faz, como é adicionada ao teu perfil de envio por nós, e o que fazer quando os produtos aparecem esgotados ou as encomendas não nos chegam.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, localização de expedição, perfil de envio, stock, esgotado
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Quando instalas a nossa aplicação, adicionamos uma localização de expedição à tua loja Shopify. O seu nome começa por <b>aiku-</b>. Adicionamo-la ao teu perfil de envio predefinido por ti, por isso normalmente não precisas de fazer nada. Se usares mais do que um perfil de envio, verifica que a localização <b>aiku-</b> está no perfil dos nossos produtos, ou o Shopify mostra-os como esgotados e não nos envia as suas encomendas.
</aside>

## Para que serve a localização

O Shopify guarda o stock por localização. Os nossos produtos são enviados a partir do nosso armazém, por isso adicionamos o nosso armazém à tua loja como uma localização de expedição.

- O seu nome é <b>aiku-</b>, depois o código do nosso site, e depois o código do teu canal entre parênteses. Por exemplo <b>aiku-awd (sho-ab12cd-3e)</b>.
- O stock de cada produto que ligas é mantido nesta localização. Nós atualizamo-lo por ti.
- Quando um cliente compra um destes produtos, o Shopify envia-nos um pedido de expedição a partir desta localização. É assim que a encomenda nos chega.

Não apagues esta localização e não movas os nossos produtos para outra localização. Se o fizeres, o stock deixa de atualizar e as encomendas deixam de nos chegar.

## Adicionada ao teu perfil de envio por nós

O Shopify só vende stock de localizações que estejam num perfil de envio. Quando a aplicação é instalada, adicionamos a localização <b>aiku-</b> por ti:

- ao teu perfil de envio predefinido, ou
- se a tua loja ainda tiver uma localização <b>aiku-dse</b> mais antiga de uma ligação anterior, a todos os perfis de envio em que essa localização mais antiga esteja.

Se a localização já estiver num dos teus perfis de envio, não alteramos nada.

Já não precisas de adicionar a localização à mão, como diziam os guias mais antigos.

## Verifica tu próprio

Faz isto se os nossos produtos aparecerem esgotados na tua loja, ou se o checkout não mostrar tarifa de envio para eles.

1. No teu admin Shopify, abre <b>Definições</b>.
2. Abre <b>Envio e entrega</b>.
3. Abre o perfil de envio onde estão os nossos produtos. Na maioria das lojas é o perfil geral.
4. Vê as localizações a partir das quais o perfil envia. A localização <b>aiku-</b> tem de lá estar.
5. Se não estiver, adiciona-a ao perfil e guarda.

<!-- screenshot: Shopify, Envio e entrega, um perfil de envio com a localização aiku-awd na sua lista de localizações -->

O Shopify muda os seus menus de vez em quando, por isso os nomes podem ser um pouco diferentes no teu admin.

Se criaste um perfil de envio personalizado para alguns dos nossos produtos, adiciona também a localização <b>aiku-</b> a esse perfil. Só a adicionamos ao perfil predefinido.

## Quando algo corre mal

**Os nossos produtos aparecem esgotados no Shopify.** Verifica o perfil de envio como acima. Verifica também que o produto está ligado: em <b>Meus Produtos</b> tem de mostrar um aperto de mão verde. Vê [Gerir produtos no Shopify](/docs/managing-products-on-shopify).

**Erro ao carregar "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent"** ("Sem localização Shopify, o serviço de expedição AW não está instalado nesta loja, por isso o stock não pode ser enviado"). Falta a localização <b>aiku-</b>. Abre o canal. Se vires <b>Click here to install</b> ("Clique aqui para instalar"), prime-o e instala a aplicação no Shopify. Se o canal mostrar <b>Reiniciar canal</b>, usa-o para criar a localização outra vez.

**Mensagem no registo "The specified inventory item is not stocked at the location"** ("O artigo de inventário indicado não tem stock nesta localização"). O produto no Shopify não tem stock na localização <b>aiku-</b>, por exemplo porque foi movido para outra localização no Shopify. Volta a ligar o produto com <b>Conecte-se com outros produtos</b> em <b>Meus Produtos</b>.

**As encomendas não nos chegam.** O Shopify só nos envia encomendas de artigos com stock na localização <b>aiku-</b>. Se o produto tinha stock na tua própria localização, o Shopify espera que o envies tu próprio. Vê [As tuas encomendas do Shopify e o seu estado](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Onde clicar</strong>
<ul>
<li><b>Verificar a localização no Shopify:</b> admin Shopify → <b>Definições</b> → <b>Envio e entrega</b> → o teu perfil de envio.</li>
<li><b>Verificar o canal:</b> <b>Canais</b> → a tua loja Shopify → os três ícones junto ao seu nome.</li>
</ul>
</aside>
