---
title: How the catalogue is organised
summary: Departments, sub-departments, families, collections and products — what each level is for, how they nest, and how a product moves between them.
date: 2026-09-01
tags: catalogue, departments, families, products
category: shop
help_routes: grp.org.shops.show.catalogue.departments, grp.org.shops.show.catalogue.families, grp.org.shops.show.catalogue.collections, grp.org.shops.show.catalogue.products
---

<aside class="tldr">
Every shop's <b>Catalogue</b> is a tree: a <b>Department</b> can hold <b>Sub-departments</b>, and both hold <b>Families</b>. Every product belongs to exactly one Family. <b>Collections</b> sit alongside that tree as a second way of grouping products — for merchandising rather than filing. Departments, sub-departments and families are built by hand in the shop; products almost always arrive already made and just need placing in the right family.
</aside>

## The shape of the tree

A **Department** is the top level — a broad area of the shop, like "Department A". Inside a department you can go two ways:

- Straight to **Families** — the level a product actually belongs to.
- Via a **Sub-department** first, if the department is big enough to need a middle layer, and families sit inside that instead.

So a family always lives directly under a department, or under a sub-department that itself lives under a department. A product is only ever attached to a family, never directly to a department or sub-department.

## Departments

Departments are where the shop's structure starts. You reach them from **Catalogue → Departments**, which lists every department with its state and its sales. Opening one shows its own families (and sub-departments, if it has any).

To create one, press **Create Department** and fill in:

- **Code** — a short internal reference.
- **Name** — the department's full name.

## Sub-departments

A sub-department is an optional middle shelf inside a department, for when "Department A" needs splitting further before you get down to families. You create one from inside its department, giving it the same **Code** and **Name** as a department. Everything below a sub-department works exactly like a department's own families — the extra layer only affects where things sit, not how they behave.

## Families

A family is the level products actually sit in — the drawer, not the cabinet. You reach the full list from **Catalogue → Families**, or browse to one through its department (and sub-department, if it has one).

To create a family, press **Create Family** and fill in:

- **Code**
- **Name**
- **Description** (optional)

Once a family exists, its own **Products** screen lists everything filed there, and that is where new products are normally added underneath it.

## Products

Products are the individual things you sell — one row per sellable item. A product's own page shows its code, name and other detail; the **Products** list under **Catalogue → Products** shows every product in the shop.

Most products arrive already built — brought in from the wider product library rather than typed in by hand family by family — and simply need to land in the right family. When one hasn't been given a family yet, it shows up as an **Orphan Product**. The Catalogue dashboard has an **Orphan Products** tile with a running count; opening it lists every family-less product with a tick box next to each row. Tick the ones that belong together, press **Add … to Family**, pick the family from the search box that appears, and press **Submit** — every ticked product moves there in one go. That screen is currently the way to move a product into a family from the Catalogue side; there is no separate "move" button on an individual product's own page.

### Product states

Every product carries a **state** that tracks its life in the catalogue:

- **In Process** — still being set up, not yet ready.
- **Active** — normal, sellable.
- **Discontinuing** — being phased out; stock may still be in the warehouse, but it is on its way out.
- **Discontinued** — fully retired; will not be used again.

A product also carries a separate **status** that reflects whether it can actually be bought right now: **For Sale**, **Not For Sale**, **Out of Stock**, **Coming Soon**, or **Discontinued** (alongside **In Process** while it's still being built). State is about the product's lifecycle in the catalogue; status is closer to what a shopper would see.

## Collections

A **Collection** is a separate grouping, alongside the department/family tree rather than inside it — a themed shelf you build for merchandising, that can pull products in from any family. You reach collections from **Catalogue → Collections**, and can also create one from inside a specific department or sub-department if you want it scoped that way.

To create a collection, press **Create Collection** and fill in:

- **Code**
- **Name**
- **Description** (optional)
- **Image** (optional)

A product being in a collection has no bearing on which family it belongs to — a product keeps its one family regardless of how many collections it also appears in.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Browse or create departments:</b> your shop → <b>Catalogue → Departments</b> → <b>Create Department</b>.</li>
<li><b>Add a sub-department:</b> open a department, then create one from inside it.</li>
<li><b>Browse or create families:</b> your shop → <b>Catalogue → Families</b> → <b>Create Family</b>, or from inside a department/sub-department.</li>
<li><b>Browse products:</b> your shop → <b>Catalogue → Products</b>.</li>
<li><b>Move a product into a family:</b> the Catalogue dashboard's <b>Orphan Products</b> tile → tick the products → <b>Add … to Family</b> → pick the family → <b>Submit</b>.</li>
<li><b>Browse or create collections:</b> your shop → <b>Catalogue → Collections</b> → <b>Create Collection</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permissions you need</strong>
You need view access to Products for this shop to browse the catalogue, and edit access to Products for this shop to create departments, sub-departments, families or collections, or to move products between families.
</aside>
