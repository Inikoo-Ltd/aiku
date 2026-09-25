---
title: Connecting your WooCommerce store
summary: Link your WooCommerce store to your dropshipping account, fix the messages you may see while connecting, and reconnect a store that stopped answering.
date: 2026-09-25
tags: woocommerce, wordpress, sales channel, connect, api keys
category: sales-channels
series: woocommerce
order: 1
help_routes: retina.dropshipping.customer_sales_channels.index, retina.dropshipping.customer_sales_channels.create, retina.dropshipping.customer_sales_channels.show
shops: awd, dssk, dse
---

<aside class="tldr">
Go to <b>Channels</b>, press <b>Add Sales Channel</b>, then <b>Connect</b> on the Woocommerce card. Type a name for your store and press <b>Next</b>. Type your store address, press <b>Auth Store</b>, approve our app in WooCommerce, come back and press <b>Next</b>. If your hosting blocks the automatic keys, you can create the keys in WooCommerce and paste them yourself.
</aside>

## Before you start

Check these on your WordPress site first. Most failed connections come from one of them.

- WooCommerce is installed and active.
- Your store address starts with <b>https://</b>. We do not connect to stores without a valid SSL certificate.
- In WordPress, <b>Settings</b>, <b>Permalinks</b> is not set to <b>Plain</b>. With Plain permalinks the WooCommerce API cannot be found.
- Your security plugin, firewall or Cloudflare does not block requests to <b>/wp-json/</b>. We talk to your store through this address.
- You can log in to your WordPress admin as an administrator. You need this to approve the connection.
- Optional but useful: set the weight unit you want in WooCommerce (<b>Settings</b>, <b>Products</b>) before you connect. We read it when you connect and send product weights in that unit.

WooCommerce is available on all our dropshipping websites. Connect it from the website where you have your dropshipping account.

## Connect your store

1. Open <b>Channels</b> in the menu. The <b>Sales Channels</b> page lists the channels you already have.
2. Press <b>Add Sales Channel</b>.
3. Find the <b>Woocommerce</b> card and press <b>Connect</b>. A window opens.
4. In <b>Woocommerce Account Name</b>, type a name for your store, for example your shop name. The name is required, and it is the name you will see in your channel list. Press <b>Next</b>.
5. Under <b>Authentication Settings</b>, type the full address of your store, for example <b>https://mystore.com</b>. Press <b>Auth Store</b>.
6. We check that your store answers first. If it does, a new tab opens on your WordPress site. Log in if WordPress asks you to.
7. WooCommerce shows that <b>AW Connect</b> asks for <b>Read/Write</b> access. Check that you are logged in to the right store, then press <b>Approve</b>.
8. The tab shows a short message and closes by itself. Go back to the window in your dropshipping account and press <b>Next</b>.
9. You see <b>Connected!</b> Press <b>OK</b>.

<!-- screenshot: the Woocommerce connect window, Authentication Settings step with the store address box and the Auth Store button -->

<!-- screenshot: the WooCommerce approval page with AW Connect asking for Read/Write access and the Approve button -->

Finish all the steps within one hour. After that we forget the name and keys you started with, and you have to start again from <b>Connect</b>.

If your browser blocks the new tab, the approval page opens in the same tab instead and you leave the connect window. Allow pop-ups for our website, then start again from <b>Connect</b>.

## If your store could not send us the keys

When you approve, WooCommerce sends the new keys from your hosting to our servers. Some hosting companies block this. You then see <b>Your store approved the connection but could not send us the keys</b>, and <b>Next</b> tells you <b>You are not connected yet</b>.

You can still connect by pasting the keys yourself:

1. In WordPress, go to <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Add a key. Give it any description, choose your administrator user and set <b>Permissions</b> to <b>Read/Write</b>. Generate the key.
3. Copy the <b>Consumer key</b> (it starts with ck_) and the <b>Consumer secret</b> (it starts with cs_). WooCommerce shows the secret only once.
4. In the Woocommerce window in your dropshipping account, make sure your store address is still in the address box.
5. Press <b>My store could not send the keys, let me paste them</b>.
6. Paste the key and the secret, and press <b>Use these keys</b>.

<!-- screenshot: the Authentication Settings step with the manual keys section open, showing the ck_ and cs_ boxes and the Use these keys button -->

## After you connect

Your store now appears on the <b>Sales Channels</b> page. Click its name to open the channel dashboard. There you see <b>Orders</b>, <b>Clients</b> and <b>Products</b>. Press <b>View all</b> under <b>Products</b> to add products. See [Managing products on WooCommerce](managing-products-on-woocommerce).

When you connect, we also add two webhooks to your store: one for new orders and one for deleted products. Do not delete them in WooCommerce, <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Without them new orders do not reach us straight away.

We import orders that are paid, have the status <b>Processing</b> in WooCommerce and have a shipping country. If an order is missing, press <b>Fetch orders</b> on the channel dashboard. It checks your store for orders from the last 14 days that have not reached us yet.

With <b>Manage Sales Channel</b> you can change the store name, your stock settings and your pricing rule for new products.

## Connecting the same store again

If you delete your WooCommerce channel and later connect the same store address again, we bring back the same channel, with its products and orders. You do not start from zero.

## When your store stops answering

We check your connected store regularly. If your store stops answering, or the keys stop working, the channel shows <b>Your channel is not connected yet to the platform</b>. Above it you may see the error message your store sent us. While this shows, your product list is hidden and you cannot upload products to your store.

To fix it:

1. Make sure your website is online and you can open it in your browser.
2. On the channel page, press <b>Try to reconnect</b>. Your WordPress site opens. Log in as an administrator and press <b>Approve</b> again. This creates new keys.
3. If it still does not work, press <b>Test Connection</b> to check the connection again.
4. As a last step, press <b>Delete</b> and connect the store again. Your products and orders come back when you use the same store address.

If your store keeps failing for a long time, we stop checking it. It starts working again when you reconnect it.

<!-- screenshot: the not connected warning on a WooCommerce channel with the Try to reconnect, Test Connection and Delete buttons -->

## When something goes wrong

These are the messages you may see when you press <b>Auth Store</b>, and what to do.

- <b>We could not resolve your store domain</b>: the address is misspelled or the domain is not live. Copy the address from your browser when your store is open.
- <b>Your store SSL certificate could not be verified</b>: your certificate is expired, self signed or incomplete. Ask your hosting company to renew or fix it.
- <b>Your store refused our connection</b> or <b>Your store did not answer within 2 minutes</b>: your hosting or firewall blocks our servers. The message lists our IP addresses. Send them to your hosting company and ask them to allow them.
- <b>Your store redirects to ...</b>: your store lives at a different address, for example with or without www. Enter the address named in the message.
- <b>Your store url redirects in a loop</b>: enter the final address of your store, the one you see in the browser after the page loads.
- <b>We could not find the WooCommerce API on this store</b>: WooCommerce is not active, its REST API is turned off, or your permalinks are set to <b>Plain</b>. Change the permalinks in WordPress, <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> or <b>403</b> <b>and blocked our request</b>: a security plugin, a firewall or Cloudflare blocks us. Allow requests to <b>/wp-json/</b> in that tool.
- <b>Your WooCommerce store returned an error 500</b> (or another number starting with 5): your website has an error. Check your hosting error log, or ask your hosting company, then try again.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b>: the address does not point to your WordPress site. Check you entered the store itself, not a landing page or another site.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b>: you pressed <b>Next</b> before approving in WooCommerce, the approval did not reach us, or more than one hour has passed. Press <b>Auth Store</b> again, or paste the keys yourself as shown above.
- <b>We can't access your store, make sure you already put correct store url</b>: we got the keys, but could not use them on that address. Check the address, and that the keys have <b>Read/Write</b> permission.

Problems on your own website, such as your store being down, slow, or blocking us, can only be fixed by you or your hosting company. We cannot change settings on your website.
