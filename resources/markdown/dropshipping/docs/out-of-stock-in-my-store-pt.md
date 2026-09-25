---
title: Porque é que um produto aparece esgotado na minha loja
summary: Descobre porque é que a tua loja mostra um produto como esgotado quando ele está em stock connosco, e como resolver isso no Shopify, WooCommerce, eBay, TikTok Shop e Wix.
date: 2026-09-25
source_date: 2026-09-25
tags: stock, esgotado, inventário, shopify, wix, woocommerce, ebay, tiktok, localização
category: troubleshooting
shops: awd, dssk, dse
---

<aside class="tldr">
Só enviamos stock para a tua loja de produtos que estão <b>ligados</b> em <b>Meus Produtos</b>, e só enquanto o teu canal estiver ligado. As razões mais comuns para "esgotado" são: o produto não está ligado, está mesmo sem stock ou não está à venda connosco, as definições do teu canal escondem stock baixo, ou (no Shopify) o stock está numa localização diferente. Verifica cada uma pela ordem abaixo e depois prime <b>Update Stock</b>.
</aside>

## Como o stock chega à tua loja

- Só enviamos stock de produtos ligados a um anúncio na tua loja: verde na coluna <b>Status</b> de <b>Meus Produtos</b>.
- Quando o nosso stock muda, atualizamos a tua loja automaticamente. Não precisas de fazer nada.
- Enviamos 0 para produtos que não estão à venda ou foram descontinuados, mesmo que ainda existam algumas unidades.
- As definições do teu canal podem reduzir o número que enviamos. Vê o passo 4.

## Verifica um a um

### 1. O canal está ligado?

Abre <b>Canais</b> no menu, clica no teu canal e abre <b>Meus Produtos</b>. Se uma caixa vermelha disser <b>Your channel is not connected yet to the platform</b> ("O teu canal ainda não está ligado à plataforma"), não conseguimos enviar nada. Volta a ligar o canal primeiro. No Shopify, confirma que premiste <b>Install</b> ("Instalar") no Shopify para terminares a ligação.

### 2. O produto está ligado?

Encontra o produto em <b>Meus Produtos</b>. No Shopify o estado tem de ser o aperto de mão verde (<b>Product connected to shopify</b>, "Produto conectado ao Shopify"). Nas outras plataformas os três vistos têm de estar verdes.

Se estiver vermelho, o anúncio na tua loja não é, tanto quanto sabemos, o nosso, por isso nunca atualizamos o seu stock. Isto acontece muitas vezes quando criaste o produto tu próprio, ou o importaste de outra aplicação. Liga-o com <b>Combine com este produto</b>, ou faz a ligação de todos de uma vez com <b>Match all with default product</b> ("Combinar todos com o produto padrão"). Vê [Usar o Meus Produtos](/docs/my-products).

### 3. Está em stock connosco?

Vê <b>Stocks</b> (no Shopify, <b>Stock</b>) na linha. Exceto no Shopify, podes usar o filtro <b>Out of stock</b> ("Esgotado") para listar todos os produtos sem stock. Um cifrão riscado significa <b>This product line is currently not for sale</b> ("Esta linha de produto não está atualmente à venda"), e uma caixa riscada significa que foi descontinuado. Em todos estes casos a tua loja está certa ao mostrar "esgotado".

Para seres avisado quando um produto voltar, usa o botão do envelope no produto no nosso site. Os teus avisos estão em <b>Lembretes de reposição de estoque</b> no menu.

### 4. Verifica as definições de stock do teu canal

Na página do canal prime <b>Gerir o canal de vendas</b> (ou <b>Editar</b>). Em <b>Gerir stock</b>:

- <b>Stock Update</b> ("Atualização de stock"): quando está desligado, deixamos de atualizar o stock automaticamente. Mantém ligado.
- <b>Stock Threshold</b> ("Limite de stock"): quando o nosso stock desce a este número ou abaixo dele, enviamos 0. Por exemplo, com um limite de 10, um produto com 8 unidades aparece esgotado. Deixa em branco para enviar o stock real.
- <b>Max Quantity To Advertise</b> ("Quantidade máxima para anunciar"): o máximo que mostramos, mesmo que tenhamos mais. Deixa em branco para não haver limite.

<!-- screenshot: secção Gerir stock das definições do canal, com Stock Update, Max Quantity To Advertise e Stock Threshold -->

### 5. Envia o stock agora

Em <b>Meus Produtos</b>, prime <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop e Wix). Vês <b>Stock update started</b> ("Atualização de stock iniciada"). Pode demorar alguns minutos. Depois abre o separador <b>Registros</b>: as linhas do tipo <b>Update Stock</b> mostram <b>Feito</b> ou <b>Fracassado</b>, com a resposta da tua plataforma.

Se disser <b>Nothing to update</b> ("Nada para atualizar"), nenhum dos teus produtos está ligado ainda. Volta ao passo 2.

## Shopify

No Shopify o nosso stock está na nossa própria localização de expedição, chamada <b>aiku-</b> seguida do código da nossa loja e do código do teu canal entre parênteses, por exemplo <b>aiku-awd (my-store)</b>.

1. No Shopify, abre <b>Produtos</b> e o produto que aparece esgotado.
2. Na secção <b>Inventário</b>, confirma que a nossa localização está listada e tem stock.
3. Se o stock estiver noutra localização (por exemplo a morada da tua própria loja) com 0, é esse o número que o Shopify mostra para essa localização. O nosso stock está sempre apenas na nossa localização.

Se o Shopify disser que o produto não tem stock na nossa localização, adicionamo-lo à nossa localização automaticamente, para que a próxima atualização de stock possa passar. Se o separador <b>Registros</b> disser <b>No variant on Shopify matches this sku</b> ("Nenhuma variante no Shopify corresponde a este SKU"), o SKU da variante no Shopify não é o nosso código de produto. Altera o SKU no Shopify para o nosso código, ou volta a ligar o produto com <b>Conecte-se com outros produtos</b>.

## Wix

Nunca enviamos stock de um produto no Wix que não esteja ligado. Se o Wix disser que todos os teus produtos estão esgotados, é muito provável que os produtos tenham sido adicionados diretamente no Wix ou não tenham sido combinados. Em <b>Meus Produtos</b>, usa <b>Match all with default product</b> para os ligar pelo SKU, ou <b>Criar novo produto</b> para os deixarmos criar. Depois prime <b>Update Stock</b>.

## eBay

Quando enviamos 0, o eBay mostra o anúncio como esgotado. Se a tua conta eBay não usar a opção de esgotado do eBay, o eBay pode terminar o anúncio em vez disso. Ativa essa opção nas tuas preferências de venda do eBay para que os anúncios se mantenham e voltem quando tivermos stock.

## WooCommerce e TikTok Shop

Verifica o separador <b>Registros</b>. No WooCommerce, uma atualização de stock <b>Fracassado</b> com "503", "timed out" ou "The store answered with a web page instead of data" significa que o teu site não nos deixou entrar. Verifica se o teu site está online e se o teu alojamento ou plugin de segurança não nos está a bloquear, e depois prime <b>Update Stock</b> outra vez.

## Quando algo corre mal

- **"Stock update failed. This channel is not connected to the platform, so stock cannot be updated."** ("A atualização de stock falhou. Este canal não está ligado à plataforma, por isso o stock não pode ser atualizado.") Volta a ligar o canal e tenta outra vez.
- **"Nothing to update".** Nenhum dos teus produtos está ligado. Liga-os primeiro (passo 2).
- **O stock está certo em Meus Produtos mas errado na minha loja, e os Registros mostram Feito.** A tua loja pode estar a somar stock das suas próprias localizações ou aplicações. Verifica se nenhuma outra aplicação ou localização altera o stock desse produto.
- **O produto voltou ao stock mas a minha loja continua a mostrar 0.** Prime <b>Update Stock</b> e verifica o separador <b>Registros</b>. Se a atualização mostrar <b>Fracassado</b>, a mensagem aí diz porquê.
