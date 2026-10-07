---
title: Países para onde não entregamos
summary: O que significa a mensagem "We cannot deliver to" num cesto ou numa encomenda, e como corrigir checkouts do Shopify que recusam enviar os nossos produtos para um país.
date: 2026-09-25
source_date: 2026-09-25
tags: entrega, países, envio, shopify, perfil de envio, morada proibida
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Cada um dos nossos sites tem uma lista de países para onde não entrega. Quando a morada de entrega de uma encomenda está num deles, vês <b>We cannot deliver to ...</b>, não consegues pagar, e a encomenda não segue para o armazém. Muda a morada enquanto a encomenda ainda é um cesto, ou pergunta-nos no chat do nosso site. Um problema diferente é um checkout do Shopify que não envia os nossos produtos para um país: isso é uma definição de envio na tua loja Shopify.
</aside>

## "We cannot deliver to ..." no teu cesto ou encomenda

Se a morada de entrega estiver num país para onde o site não entrega, vês isto a vermelho:

<b>We cannot deliver to (country). Please update the address or contact support.</b>

O que acontece então:

- Num cesto, os botões <b>Continue to Checkout</b> e <b>Place order</b> ficam escondidos.
- Uma encomenda que chega da tua loja não é paga e fica <b>Submitted</b>. Não é enviada ao armazém.
- Na página da encomenda, a caixa amarela a pedir-te para carregares o saldo e o botão <b>Pay ... with balance</b> ficam escondidos, porque a encomenda não pode ser enviada. A encomenda continua a mostrar <b>Unpaid</b>.

O que fazer:

- **Cesto (canal Manual/API)**: clica em <b>Edit</b> junto à morada de entrega e altera-a, se a morada estava errada.
- **Encomenda da tua loja**: não podes alterar a morada na encomenda. Pergunta-nos no chat do nosso site com a referência da encomenda.

Alguns países são bloqueados só em parte, por código postal. Mostra-se a mesma mensagem.

A lista é diferente em cada site. Este site não entrega nestes países:

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

Esta mensagem é sobre a tua própria morada de faturação, não a do teu comprador. Atualiza a morada na tua conta, ou pergunta-nos no chat do nosso site.

## Shopify: "unable to deliver" no checkout da tua loja

Isto acontece na tua loja Shopify, antes de a encomenda chegar até nós. O Shopify bloqueia o checkout quando não tem uma tarifa de envio da localização do produto até ao país do comprador. Produtos feitos por ti podem continuar a funcionar, porque usam uma localização diferente.

Os nossos produtos estão em stock no Shopify na nossa localização de fulfilment. O seu nome é <b>aiku-</b> seguido do código do site, depois o código do teu canal entre parêntesis, por exemplo <b>aiku-awd (my-store)</b>. Consulta [A localização de fulfilment da AW no Shopify](/docs/shopify-fulfilment-location). Verifica estas definições no teu admin do Shopify:

1. **Locations** (Settings → Locations): a nossa localização tem de estar ativa. Remove localizações de dropshipping antigas ou duplicadas que já não uses.
2. **Shipping profile** (Settings → Shipping and delivery): abre o perfil que tem os nossos produtos e verifica se a nossa localização está lá.
3. **Zones and rates**: nesse perfil, o país do comprador tem de estar numa zona de envio, e a zona precisa de pelo menos uma tarifa (paga ou grátis).
4. **Product**: abre o produto que falha e verifica que perfil de envio usa. Move-o para o perfil do passo 2 se for preciso.

<!-- captura de ecrã: perfil de envio do Shopify com a localização aiku- e uma zona que contém o país do comprador -->

Se os quatro pontos estiverem corretos e o checkout continuar a falhar, contacta o suporte do Shopify. As zonas e tarifas de envio são definidas na tua loja, por isso não as podemos alterar por ti.

Mesmo quando o Shopify permite o checkout, só conseguimos enviar a encomenda se o país não estiver na nossa lista acima.

## Quando algo corre mal

**A minha encomenda está Submitted há dias e não há botão de pagar.** Abre a encomenda. Se vires <b>We cannot deliver to ...</b>, o país está bloqueado. Pergunta-nos no chat do nosso site com a referência da encomenda.

**O comprador deu um país errado por engano.** Pergunta-nos no chat do nosso site com a referência da encomenda e a morada correta.
