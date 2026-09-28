---
title: Gerir clientes
summary: Adiciona os compradores para quem envias como clientes de um canal Manual/API, um a um ou a partir de uma folha de cálculo, e edita-os ou desativa-os mais tarde.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, clientes, endereço de entrega, importar
category: orders
series: manual
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Um cliente é a pessoa a quem vendes: enviamos a encomenda para o endereço do cliente. Num canal Manual/API, abre <b>Clients</b> e clica em <b>Create Customer Client</b> para adicionar um, ou <b>Upload File</b> para adicionar vários a partir de uma folha de cálculo. Toda encomenda num canal Manual/API é criada a partir de uma página de cliente.
</aside>

## Onde vivem os clientes

Os clientes pertencem a um canal. Abre o teu canal Manual/API no menu e depois <b>Clients</b>, ou clica em <b>View all</b> na caixa <b>Clients</b> da página do canal.

A lista mostra <b>Name</b>, <b>Email</b>, <b>phone</b>, <b>location</b> e <b>since</b> (quando os adicionaste). Tem duas abas:

- <b>Active</b>: os teus clientes atuais.
- <b>Inactive</b>: clientes que desativaste.

Só os canais Manual/API têm uma página <b>Clients</b>. Nos canais ligados, os dados do comprador chegam com cada encomenda da tua loja.

## Adicionar um cliente

1. Na página <b>Clients</b> clica em <b>Create Customer Client</b>.
2. Abre-se o formulário <b>New client</b>. Preenche:
   - <b>Company</b>: se o teu comprador for uma empresa.
   - <b>Contact name</b>: o nome para a etiqueta de envio.
   - <b>Email</b>
   - <b>phone</b>: pelo menos 6 caracteres se o preencheres.
   - <b>Address</b>: o endereço de entrega. O país começa por ser o país da nossa loja. Muda-o se o teu comprador viver noutro sítio.
3. Clica em <b>Save</b>.

<!-- screenshot: o formulário New client com Company, Contact name, Email, phone e Address -->

Abre-se a página do cliente. A partir daqui podes clicar em <b>Create Order</b>. Vê [Colocar encomendas manualmente](/docs/placing-orders-manually).

O endereço tem de estar completo para o país que escolheres. O formulário diz-te o que falta, por exemplo <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> ou <b>The province is required</b>. Alguns países não têm código postal nem localidade, e nesse caso o formulário não os pede.

## Adicionar vários clientes a partir de uma folha de cálculo

1. Na página <b>Clients</b> clica em <b>Upload File</b>.
2. Na janela <b>Import your clients</b>, descarrega o modelo.
3. Preenche um cliente por linha, com estas colunas: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Todas as colunas exceto address_line_2 têm de estar preenchidas, e o email tem de ser um endereço de email válido.
4. Para country_code usa o código de país de duas letras, por exemplo GB, ES, DE ou FR.
5. Carrega o ficheiro.

## Alterar um cliente

Abre o cliente e clica em <b>Edit</b>. A página <b>Edit client</b> deixa-te mudar <b>Company</b>, <b>Contact name</b>, <b>Email</b>, <b>phone</b> e <b>Delivery Address</b>.

Um endereço novo é usado nas encomendas novas. Para uma encomenda que ainda está na cesta, também podes mudar o endereço de entrega na página da cesta com <b>Edit</b> por baixo do endereço.

## Desativar um cliente

Na página <b>Edit client</b>, desliga <b>status</b>. O cliente passa para a aba <b>Inactive</b>. As suas encomendas anteriores mantêm-se. Volta a ligar <b>status</b> para o usares de novo.

## Clientes através da API

O teu próprio sistema pode listar, criar, alterar e desativar clientes através da API, e criar encomendas para eles. Vê [O canal Manual/API](/docs/manual-and-api-channel).

## Quando algo corre mal

- <b>Não encontro Create Customer Client.</b> O botão só existe nos canais Manual/API, e só enquanto o canal está aberto. Os outros canais não têm página <b>Clients</b>.
- <b>O formulário diz que a localidade, o código postal ou a província são obrigatórios.</b> O endereço não está completo para esse país. Preenche o campo indicado. Confirma que o país está correto.
- <b>O formulário diz que o email não é válido.</b> Verifica se há espaços e um @ ou ponto em falta. Também podes deixar o email vazio.
- <b>O meu cliente não está na lista.</b> Procura na aba <b>Inactive</b>. Confirma também que estás no canal certo: os clientes de um canal não aparecem noutro.
- <b>O carregamento da minha folha de cálculo falhou em algumas linhas.</b> Verifica se todas as colunas obrigatórias estão preenchidas (só address_line_2 pode ficar vazia), o email é válido, o country_code é um código de duas letras e o endereço tem os campos que o país exige.
