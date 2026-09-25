---
title: Gerir produtos no WooCommerce
summary: Adiciona os nossos produtos ao teu canal WooCommerce, cria-os na tua loja ou liga-os a produtos que já vendes, mantém o stock atualizado, e corrige erros de carregamento.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, produtos, carregamento, associar, sku, stock
category: products
series: woocommerce
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Abre o teu canal WooCommerce e vai a <b>My Products</b>. Clica em <b>Add products</b> e escolhe os produtos que queres vender. Depois envia cada um para a tua loja: <b>Create new product</b> cria um produto novo no WooCommerce, e <b>Match with this product</b> liga-o a um produto que já tens na tua loja. Um visto verde significa que o produto está ativo e mantemos o seu stock atualizado.
</aside>

## Abrir a tua lista de produtos

1. Abre <b>Channels</b> no menu e clica no nome da tua loja WooCommerce.
2. No painel do canal, clica em <b>View all</b> em <b>Products</b>. Abre-se a página <b>My Products</b>.

Se a página mostrar <b>Your channel is not connected yet to the platform</b>, corrige primeiro a ligação. Vê [Ligar a tua loja WooCommerce](connecting-woocommerce).

## Adicionar produtos à tua lista

1. Clica em <b>Add products</b>. Abre-se a janela <b>Select products to be added to shop</b>.
2. Procura por nome ou código. Também podes escolher um <b>Department</b>, <b>Sub-department</b> ou <b>Family</b> inteiro em vez de produtos individuais.
3. Marca os produtos que queres. Clica em <b>Add</b>. O botão mostra quantos selecionaste.

Os produtos estão agora na tua lista, mas ainda não estão na tua loja WooCommerce. Precisas de os criar ou associar primeiro.

Também podes adicionar vários produtos de uma vez a partir de uma folha de cálculo com o botão de carregamento junto a <b>Add products</b> (<b>Import from xlsx file</b>). Se tiveres produtos noutro canal, o botão <b>⋮</b> deixa-te <b>Clone portfolio from channel</b>.

<!-- screenshot: a janela Select products to be added to shop com alguns produtos marcados e o botão Add -->

## Enviar produtos para a tua loja

Cada produto tem uma coluna <b>Woo Commerce product</b>. O que vês aí depende do produto:

- <b>Create new product</b>: cria um produto novo na tua loja WooCommerce, com o nome, descrição e preço da tua lista de produtos, e as nossas imagens, SKU, código de barras, peso, dimensões e stock.
- <b>Match with this product</b>: encontrámos um produto na tua loja com o mesmo SKU, ou um nome semelhante. Verifica se é o certo, depois clica para ligar os dois. Usa isto quando já vendes o produto e não queres uma segunda cópia.
- <b>Choose another product from your shop</b> (quando encontrámos uma possível correspondência) ou <b>Match it with an existing product in your shop</b> (quando não encontrámos nenhuma): abre uma lista dos produtos da tua loja. Procura o produto, seleciona-o e clica em <b>Link ... to selected item on your platform</b>.

Quando funciona, o produto mostra um visto verde e o nome do teu produto WooCommerce. A partir daí mantemos o seu stock atualizado. Para o ligar a outro produto WooCommerce mais tarde, clica em <b>Change linked listing</b>.

Quando associas um produto, só o ligamos e atualizamos o seu stock. Não mudamos o nome, descrição, preço ou imagens que já tens no WooCommerce.

<!-- screenshot: linhas de My Products mostrando Create new product, Match with this product, e um visto verde num produto ligado -->

### Vários produtos de uma vez

- Marca vários produtos na lista. Aparecem botões acima da lista: <b>Create New</b> envia-os todos como produtos novos, <b>Match</b> liga-os aos produtos da tua loja com o mesmo SKU.
- Se alguns produtos ainda não estiverem na tua loja, vês <b>You have ... products not synced yet</b>. Clica em <b>Upload all as new product</b> para os criares todos, ou <b>Match all with default product</b> para ligares todos os produtos que têm o mesmo SKU na tua loja.

Carregamentos grandes correm em segundo plano e mostram uma janela de progresso. Podes continuar a trabalhar enquanto decorrem.

A associação procura o nosso SKU, ou o nosso código de produto, na tua loja. Maiúsculas e minúsculas não importam. Se os teus SKUs forem diferentes dos nossos, usa <b>Match it with an existing product in your shop</b> e escolhe o produto tu mesmo.

## O que enviamos para o WooCommerce

- Nome, descrição e preço da tua lista de produtos.
- As nossas imagens, SKU e código de barras (como GTIN, UPC, EAN ou ISBN).
- Peso na unidade que a tua loja usa, e dimensões quando as temos.
- País de origem e ingredientes como atributos do produto, e ligações para documentos do produto na descrição.
- O stock que podes vender. Os produtos à venda são publicados. Os produtos sem stock, a chegar em breve ou ainda não prontos ficam guardados como rascunhos.

Não escolhemos uma categoria por ti. Os produtos novos chegam sem categoria, por isso adiciona as tuas próprias categorias no WooCommerce.

## Stock e preços

Enviamos as alterações de stock para a tua loja automaticamente. Clica em <b>Update Stock</b> no topo de <b>My Products</b> para enviares agora o stock atual de todos os teus produtos para este canal.

Em <b>Manage Sales Channel</b> podes mudar como o stock é mostrado:

- <b>Stock Update</b>: liga ou desliga as atualizações automáticas de stock.
- <b>Max Quantity To Advertise</b>: o número de stock mais alto que mostramos na tua loja, mesmo quando temos mais.
- <b>Stock Threshold</b>: quando o nosso stock desce até este número, o produto aparece sem stock na tua loja.

A tua <b>Pricing Policy</b> em <b>Manage Sales Channel</b> define o preço dos produtos que adicionares a partir de agora. Não muda os produtos que já estão na tua lista. Para mudares os seus preços, marca-os e clica em <b>Edit Price</b>.

## Remover produtos

Há três formas de remover um produto. Escolhe com cuidado, porque o botão de caveira também apaga o produto da tua loja WooCommerce.

- O botão de caveira numa linha de produto pede-te confirmação, depois remove o produto da tua lista e, se estiver ligado, apaga-o permanentemente da tua loja WooCommerce. Não vai para o lixo do WooCommerce.
- <b>Unlink & Delete</b> (depois de marcares produtos) remove os produtos marcados da tua lista, mas mantém-nos na tua loja WooCommerce. Deixam de estar ligados, por isso deixamos de atualizar o seu stock.
- <b>Unlink</b> (depois de marcares produtos) mantém os produtos na tua lista e no WooCommerce, mas quebra a ligação. Deixamos de atualizar o seu stock. Podes associá-los de novo mais tarde.

Se apagares um produto ligado no WooCommerce tu mesmo, também o removemos da tua lista.

Os produtos que já não vendemos mostram um sinal vermelho. <b>This product line has been discontinued. Please remove this item</b> significa que o deves remover da tua loja. <b>This product line is currently not for sale</b> significa que não o podes carregar neste momento.

## Ver o que aconteceu

Abre a aba <b>Logs</b> (o ícone de relógio à direita das abas) para veres cada carregamento, se funcionou, e a mensagem que a tua loja enviou de volta.

A coluna <b>Status</b> mostra três vistos para cada produto: <b>Has valid platform product id</b>, <b>Exist in platform</b> e <b>Platform status</b>. Três vistos verdes significam que o produto está ligado e ativo.

## Quando algo corre mal

Se um carregamento falhar, a linha do produto mostra a mensagem da tua loja e uma dica breve. As mais comuns:

- <b>The store answered with a web page instead of data</b>, um erro 503, um tempo esgotado, ou uma resposta vazia: o teu site está em baixo, demasiado lento, em modo de manutenção, ou a bloquear-nos. É o problema de carregamento mais comum. Verifica que o teu site abre no navegador, pede à tua empresa de alojamento para permitir os nossos servidores, depois carrega de novo.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, ou <b>product with SKU ... already present in the lookup table</b>: a tua loja já tem um produto com este SKU. Quando clicas em <b>Create new product</b> tentamos ligar a esse produto sozinhos. Se a mensagem continuar a aparecer, associa o produto à mão com <b>Match it with an existing product in your shop</b>. Se não encontrares o produto na tua loja, procura no lixo do WooCommerce: um produto apagado ainda guarda o SKU até o apagares definitivamente.
- <b>Invalid or duplicated GTIN</b>: outro produto na tua loja já usa esse código de barras (GTIN, UPC, EAN ou ISBN). Associa a esse produto, ou remove o código de barras do outro produto no WooCommerce, depois carrega de novo.
- <b>Your store could not save the product images</b>: a pasta de uploads do WordPress não tem permissão de escrita. Pede à tua empresa de alojamento para corrigir as permissões da pasta, depois carrega de novo.
- <b>The account connected to your store is not allowed to create products</b> (ou to edit ou read them): as chaves não têm permissão <b>Read/Write</b>. Reconecta o canal com uma conta de administrador.
- <b>Your store rejected the credentials</b>: as chaves foram apagadas ou mudadas no WooCommerce. Clica em <b>Try to reconnect</b> na página do canal.
- <b>This product no longer exists in your store</b>: o produto foi apagado no WooCommerce. Cria-o de novo ou associa-o a outro produto.
- O botão <b>Add products</b> está em falta: a tua loja não respondeu na última vez que tentámos aceder a ela, por isso pausámos o canal. Verifica que o teu site está online. O botão volta depois de voltarmos a aceder à tua loja.

Problemas no teu próprio site, como estar em baixo, lento ou a bloquear-nos, e as regras de produto que definiste no WooCommerce, só podem ser corrigidos por ti ou pela tua empresa de alojamento.
