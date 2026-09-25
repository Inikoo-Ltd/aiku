---
title: Managing clients
summary: Add the buyers you ship to as clients of a Manual/API channel, one by one or from a spreadsheet, and edit or deactivate them later.
date: 2026-09-25
tags: manual, api, clients, customers, delivery address, import
category: orders
series: manual
order: 3
help_routes: retina.dropshipping.customer_sales_channels.client.index, retina.dropshipping.customer_sales_channels.client.create, retina.dropshipping.customer_sales_channels.client.show, retina.dropshipping.customer_sales_channels.client.edit, retina.dropshipping.customer_sales_channels.client.upload_templates
shops: awd, dssk, dse
---

<aside class="tldr">
A client is the person you sell to: we send the parcel to the client's address. In a Manual/API channel, open <b>Clients</b> and press <b>Create Customer Client</b> to add one, or <b>Upload File</b> to add many from a spreadsheet. Every order in a Manual/API channel is created from a client page.
</aside>

## Where clients live

Clients belong to one channel. Open your Manual/API channel in the menu and then <b>Clients</b>, or press <b>View all</b> on the <b>Clients</b> box of the channel page.

The list shows <b>Name</b>, <b>Email</b>, <b>phone</b>, <b>location</b> and <b>since</b> (when you added them). It has two tabs:

- <b>Active</b>: your current clients.
- <b>Inactive</b>: clients you switched off.

Only Manual/API channels have a <b>Clients</b> page. On connected channels the buyer's details come in with each order from your store.

## Add a client

1. On the <b>Clients</b> page press <b>Create Customer Client</b>.
2. The <b>New client</b> form opens. Fill in:
   - <b>Company</b>: if your buyer is a business.
   - <b>Contact name</b>: the name for the shipping label.
   - <b>Email</b>
   - <b>phone</b>: at least 6 characters if you fill it in.
   - <b>Address</b>: the delivery address. The country starts as the country of our shop. Change it if your buyer lives elsewhere.
3. Press <b>Save</b>.

<!-- screenshot: the New client form with Company, Contact name, Email, phone and Address -->

The client page opens. From here you can press <b>Create Order</b>. See [Placing orders manually](/docs/placing-orders-manually).

The address must be complete for the country you choose. The form tells you what is missing, for example <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> or <b>The province is required</b>. Some countries have no postal code or no town, and then the form does not ask for them.

## Add many clients from a spreadsheet

1. On the <b>Clients</b> page press <b>Upload File</b>.
2. In the <b>Import your clients</b> window, download the template.
3. Fill in one client per row, with these columns: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Every column except address_line_2 must be filled in, and email must be a valid email address.
4. For country_code use the two-letter country code, for example GB, ES, DE or FR.
5. Upload the file.

## Change a client

Open the client and press <b>Edit</b>. The <b>Edit client</b> page lets you change the <b>Company</b>, <b>Contact name</b>, <b>Email</b>, <b>phone</b> and <b>Delivery Address</b>.

A new address is used for new orders. For an order that is still in the basket, you can also change the delivery address on the basket page with <b>Edit</b> under the address.

## Deactivate a client

On the <b>Edit client</b> page, switch off <b>status</b>. The client moves to the <b>Inactive</b> tab. Their past orders stay. Switch <b>status</b> on again to use them.

## Clients through the API

Your own system can list, create, change and deactivate clients through the API, and create orders for them. See [The Manual/API channel](/docs/manual-and-api-channel).

## When something goes wrong

- <b>I cannot find Create Customer Client.</b> The button is only on Manual/API channels, and only while the channel is open. Other channels have no <b>Clients</b> page.
- <b>The form says the town, postal code or province is required.</b> The address is not complete for that country. Fill in the field it names. Check that the country is right.
- <b>The form says the email is not valid.</b> Check for spaces and a missing @ or dot. You can also leave the email empty.
- <b>My client is not in the list.</b> Look in the <b>Inactive</b> tab. Also check you are in the right channel: clients of one channel do not show in another.
- <b>My spreadsheet upload failed for some rows.</b> Check that every required column is filled in (only address_line_2 may be empty), the email is valid, the country_code is a two-letter code and the address has the fields the country needs.
