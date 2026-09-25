---
title: Gerir produtos e encomendas no eBay
summary: Lista os nossos produtos no eBay ou liga-os a anúncios que já tens, controla preços e stock, e percebe como as encomendas eBay chegam até nós e são marcadas como enviadas.
date: 2026-09-25
source_date: 2026-09-25
tags: ebay, produtos, anúncios, associar, sku, preços, stock, encomendas, erros
category: products
series: ebay
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Adiciona produtos ao teu canal eBay, depois abre <b>My Products</b> e clica em <b>Create new product</b> para os listares no eBay, ou <b>Match with this product</b> para ligar um anúncio que já tens com o mesmo SKU. Mantemos o stock e, se quiseres, os preços atualizados no eBay. Quando um comprador encomenda um produto ligado, a encomenda chega até nós, é paga a partir do teu saldo, e enviamos o rastreio para o eBay quando é despachada.
</aside>

## Adicionar produtos ao teu canal

Abre o teu canal eBay e vai a <b>My Products</b>. Clica em <b>Add products</b> e escolhe os produtos que queres vender. Aparecem na lista, mas ainda não estão no eBay.

No topo da lista vês "You have … products not synced yet" ("Tens … produtos ainda não sincronizados") enquanto alguns produtos não estiverem ligados a um anúncio eBay.

## Listar produtos no eBay

Para um produto, clica em <b>Create new product</b> na sua linha. Para vários, marca-os e clica em <b>Create New (…)</b>. Uma janela mostra o progresso do carregamento. Podes fechá-la; o carregamento continua.

O que enviamos para o eBay:

- O nome do produto como título. O eBay permite 80 caracteres, por isso nomes mais longos são cortados.
- A descrição, as imagens, o SKU e o peso e tamanho.
- Uma categoria que o eBay sugere para o produto, e os dados específicos do artigo que essa categoria exige, preenchidos a partir dos dados do produto.
- O preço a partir da tua política de preços e o stock que temos.

Se <b>Upload as draft</b> estiver ativo em <b>Manage Sales Channel</b>, o produto é criado no eBay mas não publicado. A sua linha mostra "Draft: uploaded to eBay but not published yet" ("Rascunho: carregado para o eBay mas ainda não publicado") e um botão <b>Publish on eBay</b>. Para publicar todos os rascunhos de uma vez, clica em <b>Publish … drafts</b> no topo da lista.

Um visto verde na coluna de estado significa que o produto está ativo no eBay e ligado.

<!-- screenshot: My Products num canal eBay, uma linha com Create new product e uma com o visto verde -->

## Ligar anúncios que já tens (associar por SKU)

Se já vendes os nossos produtos no eBay, liga-os em vez de criar um segundo anúncio.

- Quando um anúncio eBay tem o mesmo SKU que o produto, a sua linha mostra esse anúncio. Clica em <b>Match with this product</b>.
- Para ligar um anúncio diferente, clica em <b>Choose another product from your shop</b> ou <b>Match it with an existing product in your shop</b>, procura nos teus anúncios eBay e clica em <b>Link … to selected item on your platform</b>.
- Para ligar vários de uma vez, marca-os e clica em <b>Match (…)</b>, ou clica em <b>Match all with default product</b> na mensagem "not synced yet". Ambos associam por SKU.
- Num produto já ligado, <b>Change linked listing</b> liga-o a outro anúncio eBay.

Se a associação falhar, a linha diz "Your product is not in the listing yet" ("O teu produto ainda não está no anúncio"): não conseguimos encontrar um anúncio eBay publicado para ele. A associação só encontra anúncios que o eBay guarda no seu sistema de inventário com um SKU, por exemplo anúncios criados por nós ou por outra ferramenta de listagem. Um anúncio escrito à mão no eBay pode não ser encontrado. Nesse caso usa <b>Create new product</b>, e termina o anúncio antigo no eBay.

## Preços

A tua <b>Pricing Policy</b> em <b>Manage Sales Channel</b> define o preço no eBay de todos os produtos a partir do RRP em tempo real:

- <b>± % over live RRP</b> ou <b>± £ over live RRP</b> (€ nas lojas Europe e España): definimos o preço e mantemo-lo em sincronia quando o RRP muda. Quando gravas uma regra nova, perguntamos-te <b>Reprice every product?</b>. Clica em <b>Save and reprice</b> para atualizares todos os preços no eBay. Os produtos onde definiste o teu próprio preço não são tocados, a menos que marques também a caixa para os repores.
- <b>Do not follow RRP</b>: defines os preços tu mesmo no eBay. Nunca os carregamos nem substituímos.

Para dares a alguns produtos o seu próprio preço, marca-os e clica em <b>Edit Price (…)</b>. Para mudar o título, a descrição ou o preço de um produto, clica no botão de editar na sua linha. A janela <b>Edit Product</b> tem <b>Title</b>, <b>Price Mapping</b> e <b>Description</b>. Clica em <b>Save & Publish</b> para enviares as alterações ao eBay agora, ou <b>Save as Draft</b>. O preço tem de continuar acima de zero.

## Stock

Com <b>Stock Update</b> ativo, enviamos o nosso stock para o eBay automaticamente. Em <b>Manage Sales Channel</b> podes definir:

- <b>Max Quantity To Advertise</b>: a quantidade mais alta mostrada no eBay, mesmo que tenhamos mais.
- <b>Stock Threshold</b>: quando o nosso stock desce até este número, o eBay mostra o produto como esgotado.

Quando um produto está esgotado, não à venda ou descontinuado, enviamos uma quantidade de 0. O botão <b>Update Stock</b> no topo de <b>My Products</b> envia agora o stock atual de todos os produtos do canal.

<b>Sugestão:</b> o eBay pode terminar um anúncio cuja quantidade chega a 0. Para manteres o anúncio e apenas o esconderes até o stock voltar, ativa a opção de esgotado nas tuas preferências de venda do eBay.

## Remover produtos

- O botão do caixote do lixo numa linha remove o produto do teu canal e termina o seu anúncio no eBay.
- <b>Unlink (…)</b> mantém o produto na tua lista e o teu anúncio no eBay, mas deixa de os ligar. Deixamos de atualizar esse anúncio, e as suas encomendas deixam de chegar até nós.
- <b>Unlink & Delete (…)</b> remove os produtos selecionados da tua lista. Se um anúncio continuar ativo no eBay depois disso, termina-o no eBay.

## Descarregar dados e imagens de produtos

Em <b>My Products</b> abre as opções de exportação. Podes exportar um CSV dos teus produtos com as colunas que escolheres, e clicar em <b>Download images</b> para obteres as imagens dos produtos.

## Como as encomendas do eBay chegam até nós

- Verificamos a tua conta eBay regularmente à procura de encomendas novas que ainda não foram enviadas nem canceladas. Para verificar agora, clica em <b>Fetch orders</b> na página do canal.
- Só os produtos ligados em <b>My Products</b> são importados. Outros artigos na mesma encomenda eBay ficam de fora, e envias esses tu mesmo. Uma encomenda sem nenhum produto ligado não é importada.
- A encomenda é paga primeiro a partir do teu saldo, depois dos teus cartões guardados. Se o pagamento falhar, enviamos-te um e-mail e a encomenda fica em espera até ser paga.
- Vês as encomendas na página <b>Orders</b> do canal.
- Quando despachamos a encomenda, enviamos o número de rastreio e a transportadora para o eBay e o eBay marca-a como enviada.

Uma devolução ou um cancelamento do comprador no eBay não muda a encomenda connosco automaticamente.

- Não há botão de cancelar. Para cancelar, pede-nos no chat do nosso site assim que puderes. Isto só é possível antes de a encomenda ser despachada. Depois de embalada pode já ser tarde de mais.
- O teu comprador não deve enviar nada de volta até a devolução ter sido combinada connosco no chat do nosso site.
- Os reembolsos vão para o teu saldo.

## Faturas e prova de fornecimento

As nossas faturas são emitidas em teu nome, não no do teu comprador. Encontras-as em <b>Invoices</b> assim que a encomenda é despachada. Se o eBay pedir prova de fornecimento, podes mostrar-lhe estas faturas.

Tu faturas o teu próprio comprador. Nós nunca faturamos o teu comprador.

Se o eBay pedir prova de que podes revender os nossos produtos, pede-nos no chat do nosso site uma carta de autorização.

## Quando algo corre mal

O motivo de um carregamento falhado aparece na linha do produto, e o histórico completo está na aba <b>Logs</b> (o ícone de relógio) de <b>My Products</b>.

- <b>"This listing would cause you to exceed the amount you can list … this month"</b> ("Este anúncio faria com que excedesses o valor que podes listar … este mês") ou <b>"… the number of items you can list"</b> ("… o número de artigos que podes listar"): são os teus limites de venda no eBay. Carrega menos produtos ou mais baratos, pede ao eBay para aumentar os teus limites, ou espera pelo mês seguinte. Só o eBay os pode mudar.
- <b>"invalid data in the associated fulfilment policy … add at least one valid postage service"</b> ("dados inválidos na política de expedição associada … adiciona pelo menos um serviço de envio válido"): a tua política de envio no eBay não tem nenhum serviço de envio válido. Adiciona um a essa política no eBay, depois carrega de novo.
- <b>"eBay will not list anything until your seller account is finished"</b> ("O eBay não vai listar nada até a tua conta de vendedor estar concluída") ou <b>eBay has not finished setting up your seller account</b> ("O eBay ainda não terminou de configurar a tua conta de vendedor"): faz login no eBay, termina o teu registo de vendedor, depois carrega de novo.
- <b>"not allowed to revise an ended item"</b> ("não é permitido reveres um artigo terminado"): o anúncio terminou no eBay. O produto volta a aparecer como não ligado. Clica em <b>Create new product</b> para o listares de novo.
- <b>"This Offer is not available"</b> ("Esta oferta não está disponível"): o anúncio já não existe no eBay. Clica em <b>Create new product</b> para o listares de novo.
- <b>Os meus artigos no eBay terminaram todos de repente</b>: os anúncios terminam no eBay quando a quantidade chega a 0 sem a opção de esgotado, quando os apagas no eBay, ou quando o eBay os remove. Ativa a opção de esgotado, depois clica em <b>Create new product</b> nos produtos que queres de volta.
- <b>"The title or description may contain improper words, or the listing or seller may be in violation of eBay policy"</b> ("O título ou a descrição podem conter palavras impróprias, ou o anúncio ou o vendedor podem estar a violar a política do eBay"): o eBay recusou o anúncio segundo as suas próprias regras. Lê as mensagens do eBay na tua conta eBay. Só o eBay pode rever isto.
- <b>"The item specific Brand is missing"</b> ("Falta o dado específico Brand") (ou Type, Item Length, Item Width), ou <b>"custom values for Size are no longer supported"</b> ("valores personalizados para Size já não são suportados"): a categoria eBay precisa de um valor que não conseguimos preencher a partir do produto. Pergunta-nos no chat do nosso site, com o código do produto.
- <b>Anúncios bloqueados pela Overseas Warehouse Block Policy</b>: o eBay bloqueia anúncios de produtos guardados noutro país para vendedores registados em alguns países. Contacta o apoio ao vendedor do eBay a pedir aprovação.
- <b>"A system error has occurred"</b> ("Ocorreu um erro de sistema"), <b>"Unable to process your request"</b> ("Não é possível processar o teu pedido"), <b>"Too many requests"</b> ("Demasiados pedidos"), ou um tempo esgotado: o eBay teve um problema do seu lado. Não há nada de errado com o teu produto. Tenta de novo mais tarde.
- <b>Os produtos não sincronizam com o eBay</b>: verifica que o canal está ligado (sem botão <b>Reconnect</b>), que <b>Stock Update</b> está ativo, e que o produto mostra o visto verde. Um produto sem o visto não está ligado.
- <b>Uma encomenda do eBay não chegou até nós</b>: verifica que os seus produtos estão ligados em <b>My Products</b>, depois clica em <b>Fetch orders</b>.
