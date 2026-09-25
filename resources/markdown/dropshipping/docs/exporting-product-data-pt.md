---
title: Exportar dados e imagens de produtos
summary: Descarrega os produtos de um canal como um ficheiro CSV, escolhe as tuas próprias colunas e filtros, e descarrega todas as fotos de produtos como um único ficheiro zip.
date: 2026-09-25
source_date: 2026-09-25
tags: produtos, exportar, csv, imagens, download, feed de dados
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
Em <b>My Products</b> de um canal há três botões de download: <b>CSV</b> dá os dados completos de todos os produtos da lista, <b>⋮</b> deixa-te escolher as colunas e filtros para um CSV mais pequeno, e <b>Images</b> junta todas as fotos num único ficheiro zip. Também podes descarregar um produto, família, departamento ou coleção a partir da sua página no <b>Catalogue</b>.
</aside>

## Onde estão os botões

1. Abre <b>Channels</b> no menu, clica no teu canal e abre <b>My Products</b>.
2. No canto superior direito vês um grupo de botões: <b>CSV</b>, <b>⋮</b> e <b>Images</b>.

Os botões só aparecem quando o canal tem produtos e não está fechado, e não na aba <b>My Bundles</b>. Os ficheiros contêm só os produtos deste canal. Para exportar outro canal, abre o seu <b>My Products</b>.

<!-- screenshot: o grupo de botões CSV / ⋮ / Images no topo de My Products -->

## Dados completos do produto (CSV)

Clica em <b>CSV</b>. O ficheiro é descarregado de imediato. Abre-o no Excel, Google Sheets ou noutro programa de folha de cálculo. Cada linha é um produto, cada coluna um detalhe.

As colunas são:

- <b>Status</b>: <b>Active</b>, <b>Discontinuing</b> ou <b>Discontinued</b>.
- <b>Product code</b>, <b>Product user reference</b> (a tua própria referência para o produto, se definiste uma).
- <b>Department code</b>, <b>Department</b>, <b>Subdepartment code</b>, <b>Subdepartment</b>, <b>Family code</b>, <b>Family</b>.
- <b>Barcode</b>, <b>CPNP number</b> (número de cosméticos da UE, quando o produto tem um).
- <b>Price</b>: o teu preço por uma embalagem exterior (o pack que encomendas). <b>Units per outer</b>, <b>Unit label</b>, <b>Unit price</b>.
- <b>Unit Name</b>: o nome do produto.
- <b>Unit RRP</b>: preço de venda recomendado por unidade.
- <b>Unit net weight</b> e <b>Package weight (shipping)</b>, em quilogramas. <b>Unit dimensions</b>.
- <b>Materials/Ingredients</b>.
- <b>Webpage description (html)</b> e <b>Webpage description (plain text)</b>.
- <b>Country of origin</b>, <b>Tariff code</b>, <b>Duty rate</b>, <b>HTS US</b>.
- <b>Stock</b>: um nível de stock, não um número: <b>Normal</b>, <b>Low</b> (menos de 20), <b>VeryLow</b> (menos de 5), <b>OutofStock</b>, <b>Discontinuing</b> ou <b>Discontinued</b>.
- <b>Images</b>: ligações para as fotos em tamanho completo, separadas por vírgulas.
- <b>Data updated</b>, <b>Stock updated</b>, <b>Price updated</b>, <b>Images updated</b>: quando cada parte mudou pela última vez.
- <b>Available Quantity</b>: o número de unidades em stock. É 0 quando o produto não está à venda.
- <b>For sale</b>: <b>Yes</b> ou <b>No</b>.

Os conjuntos (bundles) ficam de fora. Para os incluir, abre <b>⋮</b> e marca <b>Include bundles</b> primeiro.

## As tuas próprias colunas e filtros

Clica em <b>⋮</b> (<b>Other Export Options</b>). Abre-se um painel:

- <b>Bundles</b>: marca <b>Include bundles</b> para adicionares os teus conjuntos. Está desligado por predefinição e aplica-se aos dois downloads em CSV.
- <b>Columns to Export</b>: marca as colunas que queres. <b>Select All</b> e <b>Deselect All</b> estão no topo. As colunas são os códigos e nomes de produto, departamento, subdepartamento e família, código de barras, materiais, dimensões, pesos, códigos de origem e aduaneiros, <b>Stock</b> (um número), <b>Status</b> (<b>In stock</b> ou <b>Out of stock</b>), <b>For sale</b> e <b>Data updated</b>.
- <b>Product State</b>: <b>Active</b>, <b>Discontinuing</b>, <b>Discontinued</b>. Só <b>Active</b> está marcado no início.
- <b>Product Sale Status</b>: <b>Exclude products that are not for sale</b>, <b>Exclude products that are out of stock</b>, <b>Only products that are not for sale</b>.

Clica em <b>Export Extended Properties</b>. O ficheiro abre num novo separador e é descarregado. Este ficheiro não tem preços, descrições nem ligações de imagens: usa o <b>CSV</b> completo para isso.

<!-- screenshot: o painel Export Options com Columns to Export, Product State e Product Sale Status -->

## Todas as fotos de produtos (zip)

1. Clica em <b>Images</b>. Uma janela diz <b>Your download images request is being processed.</b> Reunimos as fotos de todos os produtos da lista.
2. Quando estiver pronto, a janela diz <b>Your images are ready for download.</b> Clica em <b>Download</b> e guarda o ficheiro zip.
3. O botão passa a dizer <b>Download images</b>. Passa o rato sobre ele para veres quanto tempo a ligação ainda funciona. A ligação expira um dia depois de ser criada.

Cada foto tem o nome do código do produto e um número, por exemplo <b>abc-01__12345.jpg</b>, para saberes a que produto pertence.

Sempre que produtos do canal são adicionados ou alterados, o zip antigo é eliminado. Clica em <b>Images</b> outra vez para criares um novo.

Não há vídeos de produtos neste download.

## Um produto, família ou coleção

Em <b>Catalogue</b>, abre um produto, família, subdepartamento, departamento ou coleção. No canto superior direito:

- <b>CSV</b> descarrega os seus produtos com as mesmas colunas do CSV completo.
- Nas páginas de produto, família e coleção, clica em <b>⋮</b> e escolhe <b>images</b> em <b>Select another download file type</b> para descarregar as suas fotos como um ficheiro zip.

Nas páginas de catálogo do nosso site, cada família e produto na lista tem dois ícones de download: <b>Download products (csv)</b> e <b>Download images (zip)</b>.

## Quando algo corre mal

- **Não vejo os botões CSV e Images.** O canal ainda não tem produtos, o canal está fechado, ou estás na aba <b>My Bundles</b>. Adiciona produtos primeiro, ou volta à aba <b>My Products</b>.
- **O CSV tem menos produtos do que My Products.** O <b>CSV</b> completo deixa de fora os conjuntos. O ficheiro <b>Export Extended Properties</b> também usa os filtros <b>Product State</b> e <b>Product Sale Status</b>: marca todos os estados para obteres tudo.
- **"Select at least one column".** Marca pelo menos uma coluna em <b>Columns to Export</b>.
- **A ligação das imagens diz Expired ou não abre.** Clica em <b>Images</b> outra vez para criares um novo zip.
- **O zip só tem um ficheiro chamado error.txt.** Nenhum dos produtos da lista tem foto. Confirma que o canal tem produtos.
- **O Excel mostra letras estranhas.** Abre o ficheiro com <b>Data → From Text/CSV</b> e escolhe UTF-8, ou abre-o no Google Sheets.
- **"The data feed for ... is not available yet, please try again later."** O ficheiro dessa família ou departamento ainda está a ser criado. Tenta de novo dentro de alguns minutos.
