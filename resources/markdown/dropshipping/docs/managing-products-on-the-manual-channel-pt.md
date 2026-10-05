---
title: Gerir produtos no canal Manual/API
summary: Adiciona os produtos que vendes a My Products num canal Manual/API, importa-os de uma folha de cálculo ou de outro canal, descarrega os teus dados e imagens de produtos, e remove produtos que já não vendes.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, my products, portfólio, adicionar produtos, importar, csv, imagens
category: products
series: manual
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> é a lista dos produtos que vendes num canal. Abre-a no teu canal Manual/API e clica em <b>Add products</b> para escolheres produtos do nosso catálogo. Num canal Manual/API nada é carregado para lado nenhum: a lista é para o teu próprio uso e para a API. Não precisas dela para colocar encomendas à mão. Para deixares de vender um produto, clica no botão <b>X</b> na sua linha.
</aside>

## Abrir My Products

Vai ao teu canal Manual/API no menu e abre <b>My Products</b>. Também podes clicar em <b>View all</b> na caixa <b>Products</b> da página do canal.

Se a lista estiver vazia, a página diz <b>You don't have any items in your portfolio</b> e mostra um botão <b>Add Product</b>.

Cada linha de produto mostra a foto, o nome e o código, o stock que temos (<b>Stocks:</b>), o peso, o teu preço (<b>Price:</b>) e o preço de venda recomendado (<b>RRP:</b>).

## Adicionar produtos

1. Clica em <b>Add products</b>.
2. Abre-se uma janela <b>Add products to your product</b>.
3. Escolhe por que procurar: <b>Product</b> procura nomes e códigos de produto, <b>Department</b>, <b>Sub-department</b> e <b>Family</b> encontram os produtos de um grupo com esse nome.
4. Escreve na caixa de procura e marca os produtos que queres.
5. Clica em <b>Add … products and close</b>. O número é quantos marcaste.

<!-- screenshot: a janela Add products com o filtro Product / Department / Sub-department / Family e o botão Add products and close -->

Vês <b>Successfully added portfolios</b> e os produtos aparecem na lista.

## Adicionar vários produtos de uma vez

### A partir de uma folha de cálculo

1. Clica no botão de carregamento junto a <b>Add products</b> (dica <b>Import from xlsx file</b>).
2. Na janela <b>Bulk Import Portfolios</b>, clica em <b>Download template (.xlsx)</b>.
3. Preenche a coluna <b>sku</b> com os nossos códigos de produto, um por linha. A coluna <b>title</b> é opcional.
4. Carrega o ficheiro.

As linhas são ignoradas quando o código não existe na nossa loja ou o produto não está à venda. O histórico de carregamento mostra o que foi adicionado e o que falhou.

### A partir de outro canal

Se já tens produtos noutro canal, podes copiá-los. Clica no botão de três pontos junto a <b>Add products</b>. Em <b>Clone portfolio from channel:</b> escolhe o canal a partir do qual copiar. O número entre parênteses é quantos produtos tem. A cópia corre em segundo plano e a página recarrega quando termina.

## Encontrar produtos na tua lista

Usa a caixa de procura, ou os botões de filtro acima da lista:

- <b>Only For Sale</b>: produtos que podes encomendar agora.
- <b>Not For Sale</b>: produtos que não estamos a vender neste momento.
- <b>Discontinued</b>: produtos que não voltaremos a vender.
- <b>Out of stock</b>: produtos sem stock neste momento.

Um ícone de caixa riscada significa que o produto está descontinuado. A sua dica diz <b>This product line has been discontinued. Please remove this item</b>. Um ícone de dinheiro riscado significa <b>This product line is currently not for sale</b>. Tira estes produtos do teu próprio site para que os teus compradores não os possam encomendar.

## Obter dados e imagens de produtos para o teu site

Num canal Manual/API não carregamos produtos para o teu site. Tira os dados daqui:

- <b>CSV</b>: descarrega a tua lista de produtos com preços, stock e descrições.
- O botão de três pontos junto a <b>CSV</b> abre <b>Export Options</b>. Escolhe as colunas, o <b>Product State</b> e o <b>Product Sale Status</b> que queres, depois clica em <b>Export Extended Properties</b>. Marca <b>Include bundles</b> para adicionares os teus conjuntos.
- <b>Images</b>: prepara um download das fotos dos teus produtos. Quando estiver pronto, clica em <b>Download images</b>. A ligação só funciona por um tempo limitado, mostrado na dica do botão.
- Através da API, o teu sistema pode ler a mesma lista, e descarregá-la como um feed CSV ou JSON. Vê [O canal Manual/API](/docs/manual-and-api-channel).

O stock e os preços mudam. Descarrega a lista de novo, ou lê-a através da API, com frequência suficiente para manter o teu site correto.

## Remover um produto

Clica no botão <b>X</b> na linha do produto (dica <b>Remove product from list</b>). O produto sai da tua lista. As encomendas que já fizeste com ele não são alteradas. Podes adicioná-lo de novo mais tarde com <b>Add products</b>.

## Quando algo corre mal

- <b>Não encontro um produto na janela Add products.</b> Verifica se estás a procurar na aba certa: <b>Product</b> procura nomes e códigos de produto, <b>Family</b> e <b>Department</b> procuram nomes de grupo. A janela não mostra produtos que já estão na tua lista, produtos que não estão à venda nem produtos descontinuados.
- <b>O carregamento da minha folha de cálculo ignorou linhas com "SKU not found in this shop".</b> O código na coluna <b>sku</b> não é um dos nossos códigos de produto neste site. Copia o código exatamente como aparece no produto.
- <b>O carregamento da minha folha de cálculo ignorou linhas com "Product is not for sale".</b> Não vendemos esse produto neste momento. Deixa-o de fora.
- <b>Um produto aparece como descontinuado ou não à venda.</b> Não o podes encomendar. Remove-o do teu próprio site e de <b>My Products</b>.
- <b>Um produto está sem stock.</b> Mantém-se na tua lista. Usa <b>Out of stock</b> para encontrares estes produtos e escondê-los no teu site até voltarem.
- <b>A ligação de download das imagens já não funciona.</b> A ligação expira. Clica em <b>Images</b> outra vez para criares uma nova.
- <b>Os meus produtos não estão no meu site.</b> Nunca carregamos a partir de um canal Manual/API. Carrega-os tu mesmo com o download em CSV ou a API. Se vendes numa plataforma mostrada na página <b>Add Sales Channel</b>, como Shopify, WooCommerce, eBay ou TikTok Shop, liga essa plataforma como o seu próprio canal e os produtos são carregados por ti.
