---
title: Cartões e opções de pagamento
summary: Como pagas as encomendas, que métodos de pagamento o checkout oferece, e como guardar um cartão para que as encomendas das tuas lojas ligadas sejam pagas automaticamente.
date: 2026-09-25
source_date: 2026-09-25
tags: pagamento, cartão, cartões guardados, checkout, paypal, apple pay, google pay, pagamento automático
category: payments
shops: awd, dssk, dse
---

<aside class="tldr">
O teu saldo é sempre usado primeiro. O que o saldo não cobrir, pagas no checkout em <b>Online payments</b>: cartão, Apple Pay, Google Pay, PayPal e outros métodos, consoante o teu país e dispositivo. As encomendas que chegam das tuas lojas ligadas são pagas sem a tua intervenção: primeiro a partir do teu saldo, depois de um cartão que guardaste em <b>Saved Cards</b>. Guarda um cartão, ou mantém o teu saldo carregado, para que estas encomendas não fiquem por pagar.
</aside>

## Duas formas de as encomendas serem pagas

- <b>Encomendas que crias tu próprio</b> (encomendas manuais): pagas no checkout enquanto assistes.
- <b>Encomendas das tuas lojas ligadas</b> (Shopify, WooCommerce, eBay, TikTok e as outras, ou pela API): não está ninguém no checkout, por isso cobramos nós o dinheiro. Usamos primeiro o teu saldo, depois os teus cartões guardados. Consulta [Carregar e pagar com saldo](/docs/topping-up-and-paying-with-balance).

## Pagar no checkout

1. No <b>Dashboard</b>, em <b>Quick links (Shortcuts)</b>, prime <b>Create manual Order</b>.
2. Em <b>Select Customer Client</b>, escolhe a pessoa para quem estás a enviar, ou prime <b>Create new client here</b>. Prime <b>Create Order</b>.
3. Adiciona produtos ao cesto, verifica a morada, e prime <b>Continue to Checkout</b>. Se o teu saldo cobrir toda a encomenda, o cesto mostra <b>Place order</b> em vez disso: prime-o e a encomenda é paga a partir do teu saldo.
4. O checkout mostra o teu <b>Order number</b> e o resumo. Se tiveres dinheiro no teu saldo, é usado primeiro: vês quanto vai ser pago com o saldo e <b>Please paid the rest with your preferred method below:</b>.
5. Em <b>Online payments</b>, escolhe como pagar o resto e segue os passos. O teu banco pode pedir-te para confirmares o pagamento na sua aplicação ou com um código.
6. Depois de pagares, vês <b>Payment done. Waiting for confirmation...</b>. Quando o pagamento for confirmado, a encomenda é enviada para o nosso armazém.

Se o teu saldo cobrir toda a encomenda, não há formulário de pagamento: só vês <b>Place order</b>.

<!-- captura de ecrã: a página de checkout com o resumo da encomenda e o formulário Online payments a mostrar cartão, Apple Pay e PayPal -->

## Que métodos de pagamento podes usar

O formulário <b>Online payments</b> mostra os métodos que funcionam para o teu país, moeda e dispositivo. Os clientes usam:

- Cartões de débito e crédito
- Apple Pay (em dispositivos Apple) e Google Pay
- PayPal
- Klarna
- Nalguns países europeus: iDEAL, Przelewy24 e Bancontact

Se não vires um método que esperavas, não está disponível para o teu país, moeda ou dispositivo. A transferência bancária e o pagamento na entrega não são oferecidos no checkout de dropshipping.

## Guardar um cartão para pagamentos automáticos

Os cartões guardados são usados para pagar encomendas das tuas lojas ligadas quando o teu saldo não é suficiente.

O item <b>Saved Cards</b> aparece no menu esquerdo assim que ligares uma loja, criares um token de API ou guardares um cartão. Um pequeno ponto no item significa que ainda não tens nenhum cartão guardado.

Para guardar um cartão:

1. Prime <b>Saved Cards</b> no menu esquerdo. A página chama-se <b>Credit Card Dashboard</b>.
2. Prime <b>Save Credit Card</b> no topo (ou <b>Add credit card</b> por cima da tua lista de cartões).
3. Introduz os dados do teu cartão. O teu banco vai pedir-te para confirmares. Isto é necessário para podermos cobrar o cartão mais tarde sem a tua presença.
4. O cartão aparece na lista, que mostra o seu <b>Card type</b>, o estado <b>Expired</b>, os <b>Last 4 digits</b> e a <b>Added date</b>.

Só podem ser guardados cartões aqui. O Apple Pay, o Google Pay e o PayPal não podem ser guardados para pagamentos automáticos.

<!-- captura de ecrã: o Credit Card Dashboard com um cartão guardado marcado como predefinido e os botões Set as default e Unlink -->

## Mais do que um cartão

- O cartão predefinido tem um visto verde. Prime <b>Set as default</b> noutro cartão para o usar primeiro.
- Quando precisamos de pagar uma encomenda, tentamos primeiro o cartão predefinido, depois os teus outros cartões, um a um, até um funcionar.
- Para remover um cartão, prime <b>Unlink</b> e confirma.

Verifica a data de validade dos teus cartões. Quando um cartão expira, guarda o novo e desliga o antigo.

## Quando algo corre mal

- <b>Something went wrong</b> / <b>Failed to communicate with the payment service.</b>: o pagamento não começou. Atualiza a página do checkout e tenta de novo, ou escolhe outro método.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b>: o teu banco ainda não confirmou. Não pagues outra vez. Verifica a encomenda dentro de alguns minutos.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b>: a encomenda já está paga. És levado para a página da encomenda.
- <b>Online payments are temporarily unavailable</b>: o serviço de pagamento não está a responder. Tenta mais tarde, ou carrega o teu saldo e paga com ele.
- <b>Insert file missing</b>: um encarte na tua encomenda não tem ficheiro. Volta ao cesto e carrega o ficheiro antes do checkout.
- <b>We cannot deliver to …</b> ou <b>Your current billing address (…) is marked as forbidden</b>: não podemos cobrar o pagamento para esta morada. Muda a morada, ou pergunta-nos no chat do nosso site.
- Uma encomenda da tua loja mostra <b>Unpaid</b> e recebeste um email a dizer que está suspensa: o teu saldo não foi suficiente e nenhum cartão guardado funcionou. Carrega o teu saldo e prime <b>Pay … with balance</b> na encomenda. Consulta [Carregar e pagar com saldo](/docs/topping-up-and-paying-with-balance).
- O teu cartão foi recusado num pagamento automático: o teu banco recusou a cobrança. Verifica-o em <b>Saved Cards</b> — pode estar recusado ou expirado — depois carrega o teu saldo e paga com ele, ou guarda outro cartão e define-o como predefinido.
