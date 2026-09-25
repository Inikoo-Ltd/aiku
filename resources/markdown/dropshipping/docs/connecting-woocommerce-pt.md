---
title: Ligar a tua loja WooCommerce
summary: Liga a tua loja WooCommerce à tua conta de dropshipping, resolve as mensagens que podes ver ao ligar, e reconecta uma loja que parou de responder.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, wordpress, canal de vendas, ligar, chaves api
category: sales-channels
series: woocommerce
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Vai a <b>Channels</b>, clica em <b>Add Sales Channel</b>, depois em <b>Connect</b> no cartão do Woocommerce. Escreve um nome para a tua loja e clica em <b>Next</b>. Escreve o endereço da tua loja, clica em <b>Auth Store</b>, aprova a nossa aplicação no WooCommerce, volta e clica em <b>Next</b>. Se o teu alojamento bloquear as chaves automáticas, podes criar as chaves no WooCommerce e colá-las tu mesmo.
</aside>

## Antes de começares

Verifica isto primeiro no teu site WordPress. A maioria das ligações falhadas vem de um destes pontos.

- O WooCommerce está instalado e ativo.
- O endereço da tua loja começa por <b>https://</b>. Não ligamos a lojas sem um certificado SSL válido.
- No WordPress, <b>Settings</b>, <b>Permalinks</b> não está definido como <b>Plain</b>. Com permalinks Plain, a API do WooCommerce não é encontrada.
- O teu plugin de segurança, firewall ou Cloudflare não bloqueia pedidos a <b>/wp-json/</b>. Falamos com a tua loja através deste endereço.
- Consegues fazer login na tua administração WordPress como administrador. Precisas disto para aprovar a ligação.
- Opcional mas útil: define a unidade de peso que queres no WooCommerce (<b>Settings</b>, <b>Products</b>) antes de ligares. Lemo-la quando ligas e enviamos os pesos dos produtos nessa unidade.

O WooCommerce está disponível em todos os nossos sites de dropshipping. Liga-o a partir do site onde tens a tua conta de dropshipping.

## Ligar a tua loja

1. Abre <b>Channels</b> no menu. A página <b>Sales Channels</b> lista os canais que já tens.
2. Clica em <b>Add Sales Channel</b>.
3. Encontra o cartão <b>Woocommerce</b> e clica em <b>Connect</b>. Abre-se uma janela.
4. Em <b>Woocommerce Account Name</b>, escreve um nome para a tua loja, por exemplo o nome da tua loja. O nome é obrigatório, e é o nome que vais ver na tua lista de canais. Clica em <b>Next</b>.
5. Em <b>Authentication Settings</b>, escreve o endereço completo da tua loja, por exemplo <b>https://mystore.com</b>. Clica em <b>Auth Store</b>.
6. Verificamos primeiro que a tua loja responde. Se responder, abre-se um novo separador no teu site WordPress. Faz login se o WordPress pedir.
7. O WooCommerce mostra que a <b>AW Connect</b> pede acesso <b>Read/Write</b>. Verifica que estás autenticado na loja certa, depois clica em <b>Approve</b>.
8. O separador mostra uma mensagem breve e fecha-se sozinho. Volta à janela na tua conta de dropshipping e clica em <b>Next</b>.
9. Vês <b>Connected!</b> Clica em <b>OK</b>.

<!-- screenshot: a janela de ligação do Woocommerce, passo Authentication Settings com a caixa do endereço da loja e o botão Auth Store -->

<!-- screenshot: a página de aprovação do WooCommerce com a AW Connect a pedir acesso Read/Write e o botão Approve -->

Termina todos os passos dentro de uma hora. Depois disso esquecemos o nome e as chaves com que começaste, e tens de recomeçar a partir de <b>Connect</b>.

Se o teu navegador bloquear o novo separador, a página de aprovação abre no mesmo separador e sais da janela de ligação. Permite pop-ups para o nosso site, depois recomeça a partir de <b>Connect</b>.

## Se a tua loja não conseguir enviar-nos as chaves

Quando aprovas, o WooCommerce envia as novas chaves do teu alojamento para os nossos servidores. Algumas empresas de alojamento bloqueiam isto. Vês então <b>Your store approved the connection but could not send us the keys</b>, e <b>Next</b> diz-te <b>You are not connected yet</b>.

Ainda podes ligar colando as chaves tu mesmo:

1. No WordPress, vai a <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Adiciona uma chave. Dá-lhe qualquer descrição, escolhe o teu utilizador administrador e define <b>Permissions</b> como <b>Read/Write</b>. Gera a chave.
3. Copia a <b>Consumer key</b> (começa por ck_) e a <b>Consumer secret</b> (começa por cs_). O WooCommerce só mostra o segredo uma vez.
4. Na janela do Woocommerce na tua conta de dropshipping, confirma que o endereço da tua loja ainda está na caixa do endereço.
5. Clica em <b>My store could not send the keys, let me paste them</b>.
6. Cola a chave e o segredo, e clica em <b>Use these keys</b>.

<!-- screenshot: o passo Authentication Settings com a secção de chaves manuais aberta, mostrando as caixas ck_ e cs_ e o botão Use these keys -->

## Depois de ligares

A tua loja aparece agora na página <b>Sales Channels</b>. Clica no seu nome para abrir o painel do canal. Aí vês <b>Orders</b>, <b>Clients</b> e <b>Products</b>. Clica em <b>View all</b> em <b>Products</b> para adicionar produtos. Vê [Gerir produtos no WooCommerce](managing-products-on-woocommerce).

Quando ligas, também adicionamos dois webhooks à tua loja: um para encomendas novas e um para produtos eliminados. Não os apagues no WooCommerce, <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Sem eles as encomendas novas não chegam até nós de imediato.

Importamos encomendas que estejam pagas, tenham o estado <b>Processing</b> no WooCommerce e tenham um país de envio. Se faltar uma encomenda, clica em <b>Fetch orders</b> no painel do canal. Verifica na tua loja encomendas dos últimos 14 dias que ainda não chegaram até nós.

Com <b>Manage Sales Channel</b> podes mudar o nome da loja, as tuas definições de stock e a tua regra de preços para produtos novos.

## Ligar a mesma loja outra vez

Se eliminares o teu canal WooCommerce e mais tarde ligares o mesmo endereço de loja outra vez, trazemos de volta o mesmo canal, com os seus produtos e encomendas. Não começas do zero.

## Quando a tua loja para de responder

Verificamos a tua loja ligada regularmente. Se a tua loja parar de responder, ou as chaves deixarem de funcionar, o canal mostra <b>Your channel is not connected yet to the platform</b>. Por cima podes ver a mensagem de erro que a tua loja nos enviou. Enquanto isto aparece, a tua lista de produtos fica escondida e não consegues carregar produtos para a tua loja.

Para resolver:

1. Confirma que o teu site está online e que o consegues abrir no navegador.
2. Na página do canal, clica em <b>Try to reconnect</b>. O teu site WordPress abre. Faz login como administrador e clica em <b>Approve</b> outra vez. Isto cria chaves novas.
3. Se continuar a não funcionar, clica em <b>Test Connection</b> para verificar a ligação de novo.
4. Como último passo, clica em <b>Delete</b> e liga a loja de novo. Os teus produtos e encomendas voltam quando usas o mesmo endereço de loja.

Se a tua loja continuar a falhar durante muito tempo, deixamos de a verificar. Volta a funcionar quando a reconectas.

<!-- screenshot: o aviso de não ligado num canal WooCommerce com os botões Try to reconnect, Test Connection e Delete -->

## Quando algo corre mal

Estas são as mensagens que podes ver ao clicares em <b>Auth Store</b>, e o que fazer.

- <b>We could not resolve your store domain</b>: o endereço está mal escrito ou o domínio não está ativo. Copia o endereço do teu navegador quando a loja estiver aberta.
- <b>Your store SSL certificate could not be verified</b>: o teu certificado expirou, é autoassinado ou está incompleto. Pede à tua empresa de alojamento para o renovar ou corrigir.
- <b>Your store refused our connection</b> ou <b>Your store did not answer within 2 minutes</b>: o teu alojamento ou firewall bloqueia os nossos servidores. A mensagem lista os nossos endereços IP. Envia-os à tua empresa de alojamento e pede que os permitam.
- <b>Your store redirects to ...</b>: a tua loja está noutro endereço, por exemplo com ou sem www. Introduz o endereço indicado na mensagem.
- <b>Your store url redirects in a loop</b>: introduz o endereço final da tua loja, o que vês no navegador depois de a página carregar.
- <b>We could not find the WooCommerce API on this store</b>: o WooCommerce não está ativo, a sua REST API está desligada, ou os teus permalinks estão definidos como <b>Plain</b>. Muda os permalinks no WordPress, <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> ou <b>403</b> <b>and blocked our request</b>: um plugin de segurança, uma firewall ou o Cloudflare bloqueia-nos. Permite pedidos a <b>/wp-json/</b> nessa ferramenta.
- <b>Your WooCommerce store returned an error 500</b> (ou outro número começado por 5): o teu site tem um erro. Verifica o registo de erros do teu alojamento, ou pergunta à tua empresa de alojamento, depois tenta de novo.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b>: o endereço não aponta para o teu site WordPress. Confirma que introduziste a própria loja, não uma página de destino ou outro site.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b>: clicaste em <b>Next</b> antes de aprovar no WooCommerce, a aprovação não nos chegou, ou já passou mais de uma hora. Clica em <b>Auth Store</b> outra vez, ou cola as chaves tu mesmo como mostrado acima.
- <b>We can't access your store, make sure you already put correct store url</b>: recebemos as chaves, mas não conseguimos usá-las nesse endereço. Verifica o endereço, e que as chaves têm permissão <b>Read/Write</b>.

Problemas no teu próprio site, como estar em baixo, lento, ou a bloquear-nos, só podem ser corrigidos por ti ou pela tua empresa de alojamento. Não podemos mudar definições no teu site.
