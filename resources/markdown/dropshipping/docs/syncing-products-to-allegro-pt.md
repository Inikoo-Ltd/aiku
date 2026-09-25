---
title: Adicionar e sincronizar produtos com o Allegro
summary: Adiciona os nossos produtos ao teu canal Allegro, cria-os como ofertas Allegro ou liga-os a ofertas que já tens, e entende as mensagens que o Allegro envia de volta.
date: 2026-09-25
source_date: 2026-09-25
tags: allegro, produtos, ofertas, carregamento, associar, preço, moeda
category: products
series: allegro
order: 2
shops: dssk, dse
---

<aside class="tldr">
Abre o teu canal Allegro, vai a <b>My Products</b> e clica em <b>Add products</b>. Escolhe os produtos e clica em <b>Add</b>. Depois clica em <b>Upload all as new product</b> para os criares como ofertas no Allegro, ou <b>Match all with default product</b> para os ligares a ofertas que já tens. Um produto com três vistos verdes está ativo no Allegro. Se um carregamento falhar, passa o rato sobre a mensagem vermelha na sua linha para veres porquê.
</aside>

## Antes de começares

O teu canal Allegro tem de mostrar três vistos verdes no seu painel, e a tua conta Allegro precisa de condições de reclamação (<b>Warunki reklamacji</b>). Vê o guia sobre ligar a tua conta Allegro.

## Adicionar produtos a My Products

1. Abre o teu canal Allegro no menu e vai a <b>My Products</b>.
2. Se a lista estiver vazia, clica em <b>Add Product</b> no meio da página. Caso contrário clica em <b>Add products</b> no canto superior direito. Abre-se uma janela com o nosso catálogo.
3. Procura pelo nome ou código do produto, ou explora por <b>Department</b>, <b>Sub-department</b> ou <b>Family</b>.
4. Marca os produtos que queres. O botão no canto superior direito mostra quantos escolheste, por exemplo <b>Add 5</b>. Clica nele.

<!-- screenshot: a janela Add products com alguns produtos marcados e o botão Add -->

Os produtos estão agora em <b>My Products</b> mas ainda não no Allegro. Uma mensagem amarela diz <b>You have ... products not synced yet</b> ("Tens ... produtos ainda não sincronizados").

## Criar as ofertas no Allegro

- Para criar todas, clica em <b>Upload all as new product</b> na mensagem amarela. Uma janela mostra <b>Uploading Portfolios...</b> e conta os produtos. Quando disser <b>Uploading Complete!</b> a página recarrega sozinha. Se não recarregar, atualiza a página.
- Para criar só algumas, marca-as na lista e clica em <b>Create New (N)</b>, onde N é quantas marcaste.
- Para criar uma, clica em <b>Create new product</b> na sua linha.

<!-- screenshot: My Products com a mensagem amarela "products not synced yet" e o botão Upload all as new product -->

Para cada produto:

- Procuramos o produto no catálogo Allegro pelo seu código de barras para encontrar a sua categoria. Se o Allegro não conhecer o código de barras, o Allegro sugere uma categoria a partir do nome do sub-departamento do produto.
- Propomos o produto ao catálogo Allegro, ou usamos o produto do catálogo que o Allegro já tem.
- Criamos uma oferta <b>Buy Now</b> e publicamo-la como ativa de imediato.
- Usamos a tabela de preços de envio <b>AW-EU-</b> e a política de devolução que criámos quando ligaste.

O que a oferta contém:

- O título, cortado a 75 caracteres. O Allegro não aceita títulos mais longos.
- A tua descrição. O Allegro só aceita texto simples, negrito e parágrafos, por isso removemos outra formatação. As quebras de linha tornam-se espaços.
- O texto é enviado em inglês. O Allegro traduz-o para os compradores nos seus próprios idiomas.
- O teu preço, convertido para a moeda do teu mercado Allegro: PLN para a Polónia, CZK para a República Checa, EUR para a Eslováquia e HUF para a Hungria. Usamos a taxa de câmbio atual a partir da moeda da tua conta de dropshipping. Os preços em HUF são arredondados para cima até ao próximo múltiplo de 5 HUF, porque o Allegro Hungria só aceita esses.
- O nosso stock, limitado por <b>Max Quantity To Advertise</b> se o definires em <b>Manage Sales Channel</b>, <b>Manage Stock</b>.
- Um prazo de expedição de 24 horas.

## Ligar ofertas que já tens no Allegro

Se o produto já está no Allegro como a tua própria oferta, liga-o em vez de criares uma segunda oferta. Ligar não altera a tua oferta no Allegro.

- <b>Match all with default product</b> na mensagem amarela liga todos os produtos cujo ID externo (<b>sygnatura</b>) no Allegro seja igual ao nosso código de produto. Os produtos sem correspondência ficam intocados. Ligar corre em segundo plano, por isso atualiza a página passado um momento.
- Para ligar só alguns produtos, marca-os e clica em <b>Match (N)</b>.
- Para um produto, clica em <b>Match it with an existing product in your shop</b> na sua linha, escolhe a oferta e clica em <b>Link ... to selected item on your platform</b>.

Para associar, coloca primeiro o nosso código de produto no campo de ID externo da tua oferta Allegro.

## Verificar que um produto está ativo

Cada linha tem três pequenos vistos em <b>Status</b>: <b>Has valid platform product id</b>, <b>Exist in platform</b> e <b>Platform status</b>. Três vistos verdes significam que a oferta está no Allegro e ligada. Um círculo verde junto a eles significa que o último carregamento correu bem.

Para veres as tuas ofertas no Allegro, inicia sessão em <b>Moje Allegro</b> e abre a tua lista de ofertas.

## Alterar ou remover produtos

- Não podes editar o título, a descrição ou o preço de uma oferta Allegro a partir de <b>My Products</b>, e nunca alteramos uma oferta depois de a criarmos. Altera-os no Allegro.
- <b>Unlink (N)</b> deixa de ligar os produtos marcados às suas ofertas. As ofertas mantêm-se no Allegro.
- <b>Unlink & Delete (N)</b>, ou o caixote na linha, remove o produto de <b>My Products</b>. Não apagamos nada no Allegro: a oferta mantém-se lá. Termina-a no Allegro se já não a quiseres vender.

## Quando algo corre mal

Passa o rato sobre a mensagem vermelha numa linha para leres o que o Allegro respondeu. As mensagens mais comuns:

<b>You do not have any Complaints Terms.</b> ("Não tens condições de reclamação.")
Cria condições de reclamação (<b>Warunki reklamacji</b>) nas tuas definições de vendas do Allegro. Depois carrega outra vez.

<b>The user with an inactive or unverified account cannot create new product proposals.</b> ("Um utilizador com conta inativa ou não verificada não pode criar novas propostas de produto.")
O Allegro ainda não terminou de verificar a tua conta. Termina a verificação no Allegro, depois carrega outra vez.

<b>No shipping price list set.</b> ("Nenhuma tabela de preços de envio definida.")
A tabela de preços de envio <b>AW-EU-</b> está em falta na tua conta Allegro. Liga a mesma conta Allegro outra vez a partir de <b>Create Channels</b>: criamo-la outra vez. Se a mensagem se mantiver, pergunta-nos no chat do nosso site.

<b>You cannot create a product without providing correct values for all the required parameters: [...]</b> ("Não podes criar um produto sem fornecer valores corretos para todos os parâmetros obrigatórios: [...]") ou <b>Missing mandatory parameters: ...</b> ("Faltam parâmetros obrigatórios: ...")
A categoria que o Allegro escolheu precisa de detalhes que não temos para este produto, por exemplo um comprimento ou um código de barras (EAN). Escolhe outro produto, ou cria a oferta tu próprio no Allegro e liga-a com <b>Match</b>.

<b>Allegro has no matching category for "..."</b> ("O Allegro não tem categoria correspondente para "...")
O Allegro não encontrou uma categoria para o produto. Cria a oferta tu próprio no Allegro e liga-a com <b>Match</b>, ou escolhe outro produto.

<b>Unable to get the ... exchange rate.</b> ("Não foi possível obter a taxa de câmbio de ...")
Não conseguimos converter o preço para a tua moeda Allegro nesse momento. Tenta carregar outra vez mais tarde.

<b>Upload all as new product is missing</b> ("Falta o botão Upload all as new product")
O botão, e <b>Add products</b>, ficam escondidos por um curto período depois de a tua conta Allegro não responder. Atualiza a página passado um momento. Se continuar em falta, verifica que o painel do canal ainda tem três vistos verdes. Se não, clica em <b>Reconnect</b>.
