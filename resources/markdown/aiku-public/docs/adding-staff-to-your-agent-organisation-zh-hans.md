---
title: 为您的代理组织添加员工
summary: 面向代理管理员 —— 如何为您的同事开通他们自己的 aiku 登录账号、选择他们可以做什么，以及在有人离职时关闭账号。
date: 2026-09-02
source_date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 2
---

<aside class="tldr">
面向代理组织的管理员。一旦您可以登录，就不再需要采购方公司为您添加人员：您自己在 <b>HR → Employees</b>（人力资源 → 员工）中创建同事，为他们分配与其工作相符的职位（Position），并设置用户名和密码。采购方公司这一侧、创建您自己的第一个账号的内容，见<a href="/docs/giving-an-agent-their-first-login">giving an agent their first login</a>（英文）。
</aside>

## 您的同事会看到什么

您组织中的每个人看到的都是与您相同的 aiku，只是按其职位（Position）做了限定：**Procurement**（采购）菜单，包含面向供应商的采购订单、库存交付和购物清单看板；管理员还能看到 **HR**（人力资源）菜单。您组织中的任何人都无法看到采购方公司的店铺或客户，也看不到其他代理。

## 添加一名同事

打开 **HR → Employees**（人力资源 → 员工），点击 **Create Employee**（创建员工）。表单只有一页，对您重要的部分是：

- **Employment**（雇佣信息）：一个 **worker number**（工号）和一个 **alias**（别名），二者在您的组织内唯一即可（用名字即可），以及状态 **Working**（在职）。
- **Job → Position**（职务 → 职位）：选择此人可以做什么。**Buyer**（采购员）对于处理采购订单和交付的人来说已经足够。只有应当能够添加和移除同事的人，才给予 **Organisation Administrator**（组织管理员），因为该职位授予组织内的一切权限。
- **User credentials**（用户凭据）：如果此人不需要登录，留空即可。填写 **username**（用户名）和 **password**（密码），他们就能立即登录；aiku 会要求他们首次登录时自行设置密码。

保存，然后把用户名和初始密码告诉他们。

## 更改某人可以做什么

从 **HR → Employees**（人力资源 → 员工）打开该员工，点击 **Edit**（编辑），修改其 **Position**（职位）。更改会在其下次加载页面时生效。

## 有人离职时

打开该员工的记录，点击 **Edit**（编辑），把状态改为 **Left**（已离职）。然后从该员工的页面打开其用户账号，点击 **Edit**（编辑），关闭 **Can login**（可登录）。仅更改状态而不关闭登录权限，等于把门开着。

<aside class="wayfinder"><strong>在 aiku 中的操作位置</strong>
<ul>
<li><b>添加同事：</b> <b>HR → Employees</b> → <b>Create Employee</b>。</li>
<li><b>更改某人可以做什么：</b> 打开该员工 → <b>Edit</b> → <b>Position</b>。</li>
<li><b>有人离职：</b> 打开该员工 → <b>Edit</b> → 状态改为 <b>Left</b>，然后打开该员工的用户账号 → <b>Edit</b> → 关闭 <b>Can login</b>。</li>
</ul>
</aside>

<aside class="wayfinder"><strong>所需权限</strong>
<ul>
<li><b>Organisation Administrator</b>（组织管理员）职位在您的组织内拥有人力资源编辑权限，即上述全部操作。**Buyer**（采购员）无法添加或编辑人员。</li>
</ul>
</aside>
