---
title: 为您的代理组织添加员工
summary: 面向代理经理 —— 如何为您的同事开通他们自己的 aiku 登录账号、选择他们可以做什么，以及在有人离职时关闭账号。
date: 2026-09-30
source_date: 2026-10-08
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 2
---

<aside class="tldr">
面向代理组织的经理。一旦您可以登录，就不再需要采购方公司为您添加人员：您自己在 <b>HR → Employees</b>（人力资源 → 员工）中创建同事，为他们分配与其工作相符的职位（Position），并设置用户名和密码。采购方公司这一侧、创建您自己的第一个账号的内容，见<a href="/docs/giving-an-agent-their-first-login">giving an agent their first login</a>（英文）。
</aside>

## 您的同事会看到什么

您组织中的每个人只能看到您的组织，以及您为采购方公司所做的工作：

- **Procurement**（采购）：采购方公司的各个组织发给您的 **Purchase Orders**（采购订单），每家供应商一份，以及您为其支付的定金、您发回的 **Stock Deliveries**（库存交付）、您的 **Suppliers**（供应商）以及您的供应商 **Inbox**（收件箱）。经理还能看到采购的 **Settings**（设置）。
- **HR**（人力资源），仅限经理：您自己的人员。
- **Tickets**（工单），用于向采购方公司的服务台寻求帮助。

您组织中的任何人都无法看到采购方公司的店铺、客户或账户，也看不到其他代理。

## 添加一名同事

打开 **HR → Employees**（人力资源 → 员工），点击 **Create Employee**（创建员工）。表单只有一页，对您重要的部分是：

- **Employment**（雇佣信息）：一个 **worker number**（工号）和一个 **alias**（别名），二者在您的组织内唯一即可（用名字即可），以及状态 **Working**（在职）。
- **Job → Position**（职务 → 职位）：在 **Agent** 下选择此人可以做什么。**Clerk**（职员）适用于处理采购订单、定金和交付的人。**Manager**（经理）可以做以上所有事情，还能在 **HR** 中添加和移除同事。系统不会向您提供 **Organisation Administrator**（组织管理员）职位：该职位由采购公司保留。
- **User credentials**（用户凭据）：如果此人不需要登录，留空即可。填写 **username**（用户名）和 **password**（密码），他们就能立即登录；aiku 会要求他们首次登录时自行设置密码。

保存，然后把用户名和初始密码告诉他们。

## 更改某人可以做什么

从 **HR → Employees**（人力资源 → 员工）打开该员工，点击 **Edit**（编辑），修改其 **Position**（职位）。更改会在其下次加载页面时生效。

## 有人离职时

打开该员工的记录，点击 **Edit**（编辑），把状态改为 **Left**（已离职）。aiku 会记录其离职日期，并同时取消其登录权限。

如果某位同事忘记了密码，您无法自行重置：请通过 **Tickets → New ticket**（工单 → 新建工单）提交一个工单（见 <a href="/docs/asking-the-help-desk-for-help">asking the help desk for help</a>，英文），由采购方公司的服务台为其设置新密码。

<aside class="wayfinder"><strong>在 aiku 中的操作位置</strong>
<ul>
<li><b>添加同事：</b> <b>HR → Employees</b> → <b>Create Employee</b>。</li>
<li><b>更改某人可以做什么：</b> 打开该员工 → <b>Edit</b> → <b>Position</b>。</li>
<li><b>有人离职：</b> 打开该员工 → <b>Edit</b> → State（状态）改为 <b>Left</b>。</li>
<li><b>忘记密码：</b> <b>Tickets</b> → <b>New ticket</b>。</li>
</ul>
</aside>

<aside class="wayfinder"><strong>所需权限</strong>
<ul>
<li><b>Agent → Manager</b> 职位在您的组织内拥有人力资源编辑权限，即上述全部操作。Clerk（职员）无法添加或编辑人员。</li>
</ul>
</aside>
