---
title: Usar My Products
summary: Lê a lista My Products de um canal, envia produtos para a tua loja ou liga-os a anúncios que já tens, mantém o stock atualizado e lê os erros de carregamento.
date: 2026-09-25
source_date: 2026-09-25
tags: produtos, my products, portefólio, upload, associar, sku, stock, registos
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> é a lista dos nossos produtos que vendes num canal. Cada canal tem a sua própria lista. Daqui envias cada produto para a tua loja com <b>Create new product</b>, ou liga-lo a um anúncio que já tens com <b>Match</b>. Quando o produto está ligado, mantemos o seu stock atualizado, e a aba <b>Logs</b> mostra todos os carregamentos, associações e atualizações de stock com a resposta da tua plataforma.
</aside>

## Abrir My Products

1. Abre <b>Channels</b> no menu. Cada canal aparece por baixo com o seu logótipo.
2. Clica no canal, depois em <b>My Products</b>. O número junto a ele é quantos produtos há na lista.

A página tem três abas: <b>My Products</b>, <b>My Bundles</b> (consulta [Criar conjuntos (bundles)](/docs/bundles)) e <b>Logs</b> (o ícone do relógio à direita).

Se uma caixa vermelha disser <b>Your channel is not connected yet to the platform</b>, a ligação à tua loja está quebrada. Nada pode ser carregado e nenhum stock é enviado até voltares a ligar. Segue o guia de ligação da tua plataforma.

<!-- captura de ecrã: a página My Products de um canal Shopify com as abas, os botões no topo e algumas linhas -->

## O que mostra cada linha

- **Product**: o nosso código de produto (clica para abrir o produto), o nome, <b>Stocks</b>, <b>Weight</b> (peso do produto / peso com embalagem), <b>Dimension</b>, o nosso <b>Price</b> (o que nos pagas) e o <b>RRP</b>. Se o teu canal mostrar preços com IVA, vês <b>Price (include VAT)</b> e <b>RRP (include VAT)</b> (não no Shopify).
- **Status**: no Shopify, um aperto de mão verde significa <b>Product connected to shopify</b> e um vermelho significa <b>Not connected</b>. Noutras plataformas há três vistos: <b>Has valid platform product id</b>, <b>Exist in platform</b> e <b>Platform status</b>. Três vistos verdes significam que o produto está publicado e ligado.
- **Message**: um visto verde quando está tudo bem. Uma mensagem vermelha quando a tua plataforma recusou o produto (no Shopify, olha antes para a aba <b>Logs</b>). Clica nela para veres <b>Answer of ...</b> com o texto completo da tua plataforma e, muitas vezes, o que fazer. Uma caixa riscada significa <b>This product line has been discontinued. Please remove this item</b>. Um símbolo de dólar riscado significa <b>This product line is currently not for sale</b>.
- **A coluna de produto da tua plataforma** (por exemplo <b>Shopify product</b> ou <b>eBay product</b>): a que anúncio na tua loja este produto está ligado, ou os botões para o ligar.

## Enviar um produto para a tua loja

Para um produto que ainda não está ligado, tens duas opções.

**Criar um anúncio novo.** Prime <b>Create new product</b>. Criamos o produto na tua loja com o nosso nome, descrição, imagens, preço, SKU e stock.

**Ligar a um anúncio que já tens.** Usa isto quando já vendes o produto e não queres uma segunda cópia.
- Se encontrámos um anúncio na tua loja com o mesmo SKU, aparece na linha. Prime <b>Match with this product</b>.
- Para escolheres um diferente, prime <b>Choose another product from your shop</b>, ou <b>Match it with an existing product in your shop</b> quando não encontrámos nada. Pesquisa na tua loja, escolhe o artigo e prime <b>Link ... to selected item on your platform</b>.
- Para alterares um produto que já está ligado, prime <b>Change linked listing</b> (no Shopify: <b>Connect with other product</b>).

## Fazer vários produtos de uma vez

Quando alguns produtos ainda não estão ligados, uma barra amarela diz <b>You have ... products not synced yet</b>. Tem dois botões:

- <b>Upload all as new product</b>: cria todos na tua loja. Não aparece no eBay.
- <b>Match all with default product</b>: liga cada produto ao anúncio na tua loja com o mesmo SKU. Comparamos o SKU na tua loja com o SKU do produto em <b>My Products</b> e com o nosso código de produto, e maiúsculas ou minúsculas não importam. Os produtos sem anúncio com esse SKU ficam como estão.

Para trabalhares só em alguns produtos, marca-os na lista. Aparecem estes botões:

- <b>Create New (...)</b>: cria os produtos marcados na tua loja.
- <b>Match (...)</b>: liga os produtos marcados pelo SKU.
- <b>Unlink (...)</b> e <b>Unlink & Delete (...)</b>: consulta [Remover produtos](/docs/removing-products).
- <b>Edit Price (...)</b>: define o teu preço de venda para os produtos marcados no eBay, Shopify, WooCommerce e Wix, como percentagem ou valor acima ou abaixo do RRP. Não aparece quando o teu canal está definido para manter os seus próprios preços.

As tarefas grandes correm em segundo plano. Uma janela de progresso mostra quantos já foram feitos, e a página atualiza-se sozinha.

<!-- captura de ecrã: a barra amarela "products not synced yet" com Upload all as new product e Match all with default product -->

## Encontrar produtos na lista

Usa a caixa de pesquisa, ou os botões de filtro: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b> e <b>Out of stock</b>. No Shopify os filtros estão no menu <b>Filter</b>: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> e <b>Not Connected</b>. Não há filtro de sem stock no Shopify.

## Stock

Só enviamos stock de produtos que estão ligados (estado verde). Não precisas de fazer nada: quando o nosso stock muda, atualizamos a tua loja.

Para forçar o envio de stock agora, prime <b>Update Stock</b> no topo da página. Envia o stock atual dos produtos deste canal. Se nenhum dos teus produtos estiver ligado ainda, diz <b>Nothing to update</b>. O botão existe nos canais Shopify, WooCommerce, eBay, TikTok Shop e Wix, não no Allegro nem nos canais manuais.

Podes limitar ou esconder o stock nas definições do canal. Consulta [Porque é que um produto mostra sem stock na minha loja](/docs/out-of-stock-in-my-store).

## Outros botões

- <b>Add products</b>, o botão de carregar e <b>Clone portfolio from channel:</b>: adicionam produtos. Consulta [Encontrar e adicionar produtos](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Other Export Options</b>) e <b>Images</b>: descarregam os dados e fotos dos teus produtos. Consulta [Exportar dados e imagens de produtos](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (só eBay): publica os anúncios que foram carregados para o eBay como rascunhos.
- <b>Update all dimensions</b> (só Shopify): envia as nossas dimensões atuais para todos os teus produtos Shopify.

## A aba Logs

A aba <b>Logs</b> lista todos os carregamentos, associações e atualizações de stock deste canal: <b>Product Code</b>, <b>Type</b> (<b>upload</b>, <b>match</b> ou <b>update-stock</b>), <b>Platform</b>, <b>Status</b> (<b>Done</b>, <b>In progress</b> ou <b>Failed</b>), a <b>Response</b> da tua plataforma e a <b>Date</b>. Olha aqui primeiro quando um produto ou o seu stock não chegar.

## Quando algo corre mal

A mensagem vermelha na linha, e a <b>Response</b> em <b>Logs</b>, é a resposta da tua plataforma. As mais comuns:

- **Throttled / too many calls / request timeout / internal error.** A tua plataforma pediu-nos para abrandarmos, ou não respondeu a tempo. Não há nada de errado com o produto. Tenta de novo dentro de alguns minutos.
- **The store answered with a web page instead of data, or returned 503, timed out or an empty reply** (WooCommerce). O teu próprio site está em baixo, em modo de manutenção, ou o seu plugin de segurança ou alojamento bloqueia-nos. Verifica se o teu site está online. Pede ao teu alojamento para permitir a nossa ligação, depois tenta de novo.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). Já tens um produto com esse SKU. Usa <b>Match</b> em vez de <b>Create new product</b>. Se o produto antigo estiver no lixo do WooCommerce, esvazia o lixo primeiro.
- **Invalid or duplicated GTIN** (WooCommerce). Outro produto na tua loja já usa esse código de barras. Remove o código de barras do outro produto no WooCommerce, ou associa a ele.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). Este é um limite do TikTok para lojas novas, não um problema com o produto. Remove anúncios de que não precisas, ou pede ao TikTok para subir o teu nível.
- **product_weight received 0 / weight cannot be zero** (TikTok). O TikTok precisa de um peso. Não podes alterar o peso do nosso produto tu próprio: pergunta-nos no chat do nosso site, com o código do produto.
- **Image must be at least 300:300** (TikTok). Uma das nossas imagens é demasiado pequena para o TikTok. Pergunta-nos no chat do nosso site, com o código do produto.
- **Price out of range / incorrect price** (TikTok). O TikTok decide o intervalo de preços que a tua loja pode usar. Verifica o intervalo no TikTok Shop Seller Center. Se o preço que enviamos estiver fora dele, pergunta-nos no chat do nosso site, com o código do produto.
- **Category qualification / category is restricted** (TikTok). Candidata-te à categoria no Qualification Center do TikTok Shop Seller Center, depois carrega de novo.
- **Requires an active seller account** (TikTok) ou **create a seller account** (eBay). Termina primeiro a tua conta de vendedor na plataforma.
- **The listing would cause you to exceed the amount you can list this month** (eBay). Atingiste o teu limite de venda no eBay. Pede ao eBay para o aumentar, ou espera pelo mês seguinte.
- **Invalid data in the associated fulfilment policy** (eBay). A tua política de envio (fulfilment) do eBay tem um problema. Corrige-a no eBay, depois verifica as políticas escolhidas nas definições do teu canal.
- **Item specific Type / Brand missing, or custom values for Size no longer supported** (eBay). O eBay quer detalhes extra para essa categoria. Consulta [Gerir produtos no eBay](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). O anúncio terminou no eBay. Desliga o produto e cria-o de novo.
- **Overseas Warehouse Block Policy** (eBay). Se a tua conta estiver registada nalguns países, aparece um <b>Important Notice</b> vermelho no topo. O eBay pode bloquear anúncios guardados no estrangeiro. Contacta o suporte do eBay para pedires aprovação.
- **This product line has been discontinued.** Já não vendemos esse produto. Remove-o da tua lista e da tua loja.
