---
title: Criar clientes com a API
summary: Como o teu sistema cria, muda e encontra clientes através da nossa API, com um exemplo completo de pedido e resposta, os campos que podes enviar e o que significa cada erro.
date: 2026-10-05
source_date: 2026-10-05
tags: api, clientes, criar cliente, morada, código do país, integração, programador
category: sales-channels
series: manual
order: 5
shops: awd, dssk, dse
---

<aside class="tldr">
Envia <b>POST</b> para <b>https://api.aiku.io/dropshipping/clients</b> com o teu token da API e um corpo JSON. A única coisa de que precisamos é a morada de entrega com o país como código ISO, por exemplo <b>"country_code": "GB"</b>. Respondemos <b>201</b> com o novo cliente; guarda o seu <b>id</b> para criares encomendas para esse cliente. No staging usa <b>https://api.aiku-sandbox.uk</b>.
</aside>

## O que é um cliente

Um cliente é a pessoa ou empresa a quem envias uma encomenda: o teu comprador. Cada encomenda que crias através da API pertence a um cliente, por isso o teu sistema cria primeiro o cliente e depois cria encomendas para ele. Os clientes criados através da API aparecem em <b>Clients</b> no teu canal, tal como os que adicionas à mão.

Precisas primeiro de um token da API. Vê [O canal Manual/API](/docs/manual-and-api-channel) para saberes como obter um. O token decide o canal: os clientes são criados no canal a que o token pertence. O token não pode ser só de leitura.

## O pedido

| | |
|---|---|
| Método | <b>POST</b> |
| Endereço (real) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Endereço (staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| Cabeçalhos | <b>Authorization: Bearer</b> seguido do teu token<br><b>Content-Type: application/json</b> |

A resposta é sempre JSON. Não precisas de enviar um cabeçalho <b>Accept</b>.

### Um exemplo completo

```bash
curl -X POST https://api.aiku.io/dropshipping/clients \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{
    "reference": "WEB-10045",
    "contact_name": "Jane Smith",
    "company_name": "Smith Gifts Ltd",
    "email": "jane@example.com",
    "phone": "+44 7700 900123",
    "address": {
      "address_line_1": "12 High Street",
      "address_line_2": "Flat 3",
      "locality": "Sheffield",
      "administrative_area": "South Yorkshire",
      "postal_code": "S1 2AB",
      "country_code": "GB"
    }
  }'
```

O menor pedido que aceitamos é apenas o país:

```json
{
  "address": { "country_code": "GB" }
}
```

Um cliente assim não pode receber encomendas, por isso envia sempre a morada de entrega completa quando a tiveres.

### Os campos

| Campo | Obrigatório | O que enviar |
|---|---|---|
| <b>address</b> | Sim | A morada de entrega, como um objeto com os campos abaixo. |
| <b>address.country_code</b> | Sim | O país como código ISO: duas letras (<b>GB</b>, <b>ES</b>, <b>DE</b>) ou três letras (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). Maiúsculas ou minúsculas. |
| <b>address.address_line_1</b> | Para entrega | Rua e número. |
| <b>address.address_line_2</b> | Não | Andar, piso, edifício. |
| <b>address.locality</b> | Para entrega | Localidade ou cidade. |
| <b>address.administrative_area</b> | Depende do país | Condado, estado ou província, nos países que usam um. |
| <b>address.postal_code</b> | Para entrega | Código postal. |
| <b>address.dependent_locality</b> | Não | Distrito ou bairro, nos países que usam um. |
| <b>address.sorting_code</b> | Não | Só para países que usam um código de triagem. |
| <b>reference</b> | Não | O teu próprio código para o cliente, por exemplo o número de cliente na tua loja. Tem de ser único no teu canal. |
| <b>contact_name</b> | Não | O nome da pessoa. |
| <b>company_name</b> | Não | O nome da empresa. |
| <b>email</b> | Não | Um endereço de e-mail válido. |
| <b>phone</b> | Não | Pelo menos 6 caracteres. Usa o formato internacional, por exemplo <b>+44 7700 900123</b>, para que a transportadora possa ligar. |

Os campos de morada que não estão nesta lista, como <b>city</b>, <b>state</b>, <b>county</b> ou <b>country</b>, são ignorados. Usa os nomes acima. Não existem os campos <b>first_name</b> nem <b>last_name</b>: envia o nome completo em <b>contact_name</b>.

Integrações mais antigas podem enviar <b>address.country_id</b>, o nosso número próprio para o país. Ainda funciona, mas usa <b>country_code</b>: se enviares os dois, <b>country_code</b> prevalece.

### A resposta quando funciona

Respondemos <b>201 Created</b> com o novo cliente:

```json
{
  "data": {
    "id": 52817,
    "ulid": "01M4639WS5Z7QT08CNW0M0DEJQ",
    "reference": "WEB-10045",
    "active": null,
    "name": "Smith Gifts Ltd",
    "contact_name": "Jane Smith",
    "company_name": "Smith Gifts Ltd",
    "location": ["GB", "United Kingdom", "Sheffield"],
    "email": "jane@example.com",
    "phone": "+44 7700 900123",
    "created_at": "2026-10-05T13:15:59.000000Z",
    "updated_at": "2026-10-05T13:15:59.000000Z",
    "address": {
      "id": 9123456,
      "address_line_1": "12 High Street",
      "address_line_2": "Flat 3",
      "sorting_code": null,
      "postal_code": "S1 2AB",
      "locality": "Sheffield",
      "dependent_locality": null,
      "administrative_area": "South Yorkshire",
      "country_code": "GB",
      "country_id": 48,
      "checksum": "74d55290109e98f64b7d908a3e66b9d8",
      "created_at": "2026-10-05T13:15:59.000000Z",
      "updated_at": "2026-10-05T13:15:59.000000Z",
      "country": { "code": "GB", "iso3": "GBR", "name": "United Kingdom" },
      "formatted_address": "<p translate=\"no\">...12 High Street<br>Flat 3<br>Sheffield<br>S1 2AB<br>United Kingdom</p>",
      "can_edit": null,
      "can_delete": null
    }
  },
  "message": "Client created successfully"
}
```

<b>name</b> é o nome da empresa quando envias um, caso contrário é o nome do contacto. <b>formatted_address</b> é a morada em HTML, pronta a mostrar.

Guarda <b>data.id</b>. Precisas dele para criar encomendas para este cliente: <b>POST /dropshipping/order/client/{id}/store</b>.

## Mudar um cliente

Envia <b>PATCH</b> para <b>/dropshipping/clients/{id}</b> só com os campos que queres mudar. Quando mudas a morada, envia a morada inteira, incluindo <b>country_code</b>.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

Respondemos <b>200</b> com o cliente, na mesma forma que acima, e <b>"message": "Client updated successfully"</b>.

## Encontrar os teus clientes

- <b>GET /dropshipping/clients</b> lista os clientes do teu canal, página a página.
- <b>GET /dropshipping/clients/{id}</b> mostra um cliente.
- <b>DELETE /dropshipping/clients/{id}</b> desativa um cliente. As encomendas anteriores mantêm-se.

Para não criares o mesmo comprador duas vezes, envia o teu próprio número de cliente como <b>reference</b>. Um segundo cliente com o mesmo <b>reference</b> no mesmo canal é recusado.

## Quando algo corre mal

Quando recusamos um pedido por causa dos dados, respondemos <b>422</b> com uma <b>message</b> e o campo errado em <b>errors</b>:

```json
{
  "message": "Unknown country code \"UK\". Send the ISO code of the country, two letters (GB) or three letters (GBR).",
  "errors": {
    "address.country_code": [
      "Unknown country code \"UK\". Send the ISO code of the country, two letters (GB) or three letters (GBR)."
    ]
  }
}
```

- <b>"The client's delivery address is required"</b>. O corpo não tem um objeto <b>address</b>. Os campos da morada têm de estar dentro de <b>address</b>, não ao nível superior do corpo.
- <b>"The country is required"</b>. A morada não tem <b>country_code</b>. Adiciona-o.
- <b>"Unknown country code"</b>. O código não é um código ISO de país. O Reino Unido é <b>GB</b> ou <b>GBR</b>, não <b>UK</b>.
- <b>"The reference has already been taken"</b>. Outro cliente deste canal tem esse <b>reference</b>. Usa um diferente, ou muda o cliente existente com <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Envia o número de telefone completo, ou deixa <b>phone</b> de fora.
- <b>401</b> com uma mensagem sobre o cabeçalho <b>Authorization</b>. O token falta, está incompleto ou foi apagado. Copia o token inteiro, incluindo o número e o <b>|</b> do início. O staging precisa do seu próprio token.
- <b>403 "This API token is read only."</b>. Gera um token sem <b>Read only</b> marcado.
- <b>429</b>. Cada token pode fazer até 120 pedidos por minuto. Abranda e tenta de novo passado um minuto.

Cada pedido que o teu sistema faz aparece na aba <b>API calls</b> da página <b>API</b> do teu canal, com a resposta que demos. Olha primeiro aí quando um pedido não faz o que esperas.
