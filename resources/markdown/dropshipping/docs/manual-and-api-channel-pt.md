---
title: O canal Manual/API
summary: Cria um canal Manual/API para vender a partir do teu próprio site, marketplace ou aplicação, coloca encomendas à mão ou envia-as para nós através da nossa API.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, canal de vendas, site próprio, token da api, integração
category: sales-channels
series: manual
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Usa um canal <b>Manual/API</b> quando a tua loja não está numa das plataformas a que nos ligamos, ou quando queres escrever encomendas tu mesmo. Vai a <b>Channels</b>, clica em <b>Add Sales Channel</b>, depois em <b>Create</b> no cartão <b>Manual/API</b> e dá-lhe um nome. Depois adicionas produtos a <b>My Products</b>, adicionas os teus compradores como <b>Clients</b> e crias encomendas para eles, à mão ou através da API.
</aside>

## Quando usar um canal Manual/API

Um canal Manual/API não está ligado a nenhuma loja. Nada é carregado para um site e nenhuma encomenda chega sozinha. Usa-o quando:

- Vendes no teu próprio site, num marketplace a que não nos ligamos, nas redes sociais ou por telefone, e queres enviar-nos cada encomenda tu mesmo.
- Tens o teu próprio sistema ou programador e queres enviar-nos encomendas através da nossa API.

Se a tua loja está numa plataforma mostrada na página <b>Add Sales Channel</b>, como Shopify, WooCommerce, eBay ou TikTok Shop, liga essa plataforma em vez disso. Depois os produtos são carregados por ti e as encomendas chegam sozinhas.

Podes ter mais do que um canal Manual/API, por exemplo um por site.

## Criar o canal

1. Abre <b>Channels</b> no menu. Vês a lista dos teus <b>Sales Channels</b>.
2. Clica em <b>Add Sales Channel</b>. A página mostra <b>Select channel you want to create</b>.
3. No cartão <b>Manual/API</b> clica em <b>Create</b>.
4. Abre-se uma janela <b>Create platform manual</b>. Escreve um nome para o canal, por exemplo o nome do teu site. O nome pode ter até 28 caracteres.
5. Clica em <b>Create</b>.

<!-- screenshot: a página Add Sales Channel com o cartão Manual/API e o seu botão Create, e a janela Create platform manual -->

Vês a mensagem <b>Your Manual store has been created.</b> e a página do canal abre-se.

Cada um dos teus canais precisa do seu próprio nome. Se o nome já for usado por outro dos teus canais, a janela mostra um erro. Escolhe um nome diferente.

## A página do teu canal

A página do canal tem o nome do teu canal como título e o cabeçalho <b>Manual/API order management</b>. Mostra três caixas, cada uma com uma ligação <b>View all</b>:

- <b>Orders</b>: as encomendas que colocaste neste canal.
- <b>Clients</b>: as pessoas a quem envias encomendas.
- <b>Products</b>: os produtos na tua lista <b>My Products</b>.

No menu, por baixo do nome do canal, encontras:

- <b>Baskets</b>: encomendas que começaste e ainda não pagaste.
- <b>My Products</b>: os produtos que vendes neste canal. Vê [Gerir produtos no canal Manual/API](/docs/managing-products-on-the-manual-channel).
- <b>Clients</b>: os teus compradores. Vê [Gerir clientes](/docs/managing-clients).
- <b>Orders</b>: as tuas encomendas colocadas. Vê [Colocar encomendas manualmente](/docs/placing-orders-manually).
- <b>API</b>: tokens e documentação para ligares o teu próprio sistema.

A forma habitual de trabalhar é:

1. Adiciona os produtos que vendes a <b>My Products</b> com <b>Add products</b>. Isto é necessário para a API. Para encomendas que escreves tu mesmo é opcional: o cesto deixa-te escolher qualquer produto que vendemos.
2. Quando recebes uma encomenda, abre <b>Clients</b>, encontra o teu comprador ou adiciona-o.
3. Na página do cliente clica em <b>Create Order</b>, adiciona os produtos e quantidades e paga.

## Mudar o nome ou fechar o canal

Para renomeares o canal, clica em <b>Edit</b> na página do canal e muda <b>Store name</b>.

Para fechares um canal, vai a <b>Channels</b> e clica no botão de fechar na coluna <b>Action</b> (dica <b>Close channel</b>). A janela pergunta <b>Are you sure you want to close this channel?</b> e avisa <b>This operation is irreversible.</b> Um canal fechado sai do menu. As tuas encomendas e faturas anteriores mantêm-se.

## Ligar o teu próprio sistema com a API

A API deixa o teu site ou aplicação fazer sozinho o que fazes nas páginas do canal: ler o nosso catálogo de produtos com preços em tempo real, adicionar produtos a <b>My Products</b>, criar e mudar clientes, criar encomendas, adicionar-lhes produtos, submetê-las e acompanhá-las. Também podes descarregar a tua lista <b>My Products</b> como um feed CSV ou JSON para carregar produtos para o teu próprio site.

Abre <b>API</b> no teu canal. A página tem estas abas:

- <b>Overview</b>: como ligar, o endereço base da API e o botão <b>API documentation</b>. A documentação lista todos os endpoints com exemplos.
- <b>API tokens</b>: os tokens deste canal.
- <b>API calls</b>: os pedidos que o teu sistema fez.
- <b>History</b>: alterações feitas na tua conta.

### Obter um token

1. Clica em <b>Generate API token</b>.
2. Se o token for só para ler dados, marca <b>Read only (cannot create, change or submit orders)</b>.
3. Clica em <b>Click to Generate</b>.
4. Copia o token com o ícone de copiar e guarda-o em local seguro. A janela diz <b>Put this token in a safe place, you won't be able to see it again.</b> O rótulo curto na lista de tokens é só um nome, não o token.

Envia o token com cada pedido no cabeçalho <b>Authorization: Bearer</b> seguido do teu token. Cada token pertence a um canal: os produtos, clientes e encomendas que o teu sistema criar vão para esse canal. Para deixares um token de funcionar, apaga-o na aba <b>API tokens</b>.

<!-- screenshot: a aba Overview da página API com os botões API documentation e Generate API token -->

### Testa primeiro em staging

A aba <b>Overview</b> também tem <b>Open staging mirror</b>. O staging é uma cópia separada do site onde podes testar sem encomendas nem pagamentos reais. Faz login com o mesmo e-mail e palavra-passe. O staging é reposto regularmente com uma cópia nova, o que apaga o que aí criaste. Os tokens do site real não funcionam no staging: gera um token separado no staging, e um novo depois de cada reposição. O endereço base do staging aparece na aba <b>Overview</b>.

### Como as encomendas da API são pagas

Quando o teu sistema submete uma encomenda, pagamo-la primeiro a partir do saldo da tua conta, depois dos teus cartões guardados. Adiciona um cartão antes de começares. Assim que tiveres um token, o menu mostra <b>Saved Cards</b>. Enquanto nenhum cartão estiver guardado, a página API mostra <b>You have no cards saved yet.</b> com um botão <b>Add card</b>.

Se nem o teu saldo nem os teus cartões cobrirem a encomenda, a encomenda fica marcada <b>Unpaid</b> e não vai para o armazém. Adiciona dinheiro ao teu saldo com <b>Top Up</b>, abre a encomenda e clica em <b>Pay … with balance</b>. O botão aparece quando o teu saldo cobre o valor em falta.

## Quando algo corre mal

- <b>O nome já está em uso quando crio o canal.</b> Outro dos teus canais abertos tem esse nome. Escreve um nome diferente. O nome de um canal fechado pode ser usado de novo.
- <b>As minhas encomendas não estão a chegar sozinhas.</b> Um canal Manual/API nunca recolhe encomendas de um site. Cria-as na página do cliente, ou envia-as do teu sistema através da API. Se vendes numa plataforma mostrada na página <b>Add Sales Channel</b>, liga essa plataforma como o seu próprio canal.
- <b>Os meus produtos não estão no meu site.</b> Não carregamos nada a partir de um canal Manual/API. Carrega-os para o teu site tu mesmo, com a descarga CSV em <b>My Products</b> ou através da API.
- <b>Perdi o meu token da API.</b> Não pode voltar a ser mostrado. Gera um token novo, coloca-o no teu sistema e apaga o antigo.
- <b>A API responde que não posso criar ou mudar encomendas.</b> O token é só de leitura. Gera um token sem <b>Read only</b> marcado.
- <b>A API recusa os meus pedidos durante um pouco.</b> Cada token pode fazer até 120 pedidos por minuto. Abranda o teu sistema e tenta de novo passado um minuto.
- <b>A API diz "This order has no products yet".</b> ("Esta encomenda ainda não tem produtos.") Adiciona pelo menos um produto à encomenda antes de a submeteres.
- <b>A API diz "Unable to find related portfolio item".</b> ("Não foi possível encontrar o artigo de portefólio relacionado.") Através da API adicionas um produto a uma encomenda pelo seu artigo em <b>My Products</b>, não pelo próprio produto. Adiciona primeiro o produto a <b>My Products</b> e usa o id desse artigo.
- <b>A API diz que já existe outra transação com o mesmo produto.</b> O produto já está na encomenda. Muda a quantidade dessa linha em vez de o adicionares outra vez.
- <b>A API diz que a encomenda "is already in the 'submitted' state and cannot be updated".</b> ("já está no estado 'submitted' e não pode ser atualizada.") As encomendas submetidas não podem ser mudadas nem apagadas através da API. Pergunta-nos no chat do nosso site se a encomenda tiver de mudar.
- <b>A minha encomenda da API mostra Unpaid.</b> O teu saldo e os cartões guardados não a cobriram. Recarrega o teu saldo, abre a encomenda e clica em <b>Pay … with balance</b>, e verifica que o teu cartão guardado ainda é válido.
