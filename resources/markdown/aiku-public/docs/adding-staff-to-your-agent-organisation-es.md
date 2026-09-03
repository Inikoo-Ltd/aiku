---
title: Añadir personal a tu organización de agente
summary: Para administradores de agente — cómo dar a tus compañeros su propio acceso a aiku, elegir qué pueden hacer, y cerrar una cuenta cuando alguien se marcha.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 2
---

<aside class="tldr">
Para el administrador de una organización de agente. Una vez que puedes entrar, ya no necesitas que la empresa compradora añada a nadie: creas tú mismo a tus compañeros en <b>HR → Employees</b>, les das un puesto acorde a su trabajo, y un usuario y contraseña. La parte de la empresa compradora, crear tu propia primera cuenta, está en <a href="/docs/giving-an-agent-their-first-login-es">dar a un agente su primer acceso</a>.
</aside>

## Qué verán tus compañeros

Todos en tu organización ven el mismo aiku que tú, ajustado a su puesto: el menú **Procurement** con las órdenes de compra a proveedores, las entregas de stock y el tablero de la lista de compras, y, para los administradores, el menú **HR**. Nadie en tu organización puede ver las tiendas o los clientes de la empresa compradora, ni a otros agentes.

## Añadir a un compañero

Abre **HR → Employees** y pulsa **Create Employee**. El formulario es una sola página; las partes que te importan son:

- **Employment**: un **worker number** y un **alias**, ambos únicos dentro de tu organización (los nombres de pila valen), y el estado **Working**.
- **Job → Position**: elige qué puede hacer la persona. **Buyer** es suficiente para alguien que trabaja con órdenes de compra y entregas. Da **Organisation Administrator** solo a quien deba poder añadir y quitar compañeros, porque concede todo en la organización.
- **User credentials**: déjalo vacío para alguien que no necesita entrar. Rellena un **username** y una **password** y podrá entrar de inmediato; aiku le pide elegir su propia contraseña la primera vez.

Guarda, y pásale el usuario y la contraseña inicial.

## Cambiar lo que alguien puede hacer

Abre el empleado desde **HR → Employees**, pulsa **Edit** y cambia su **Position**. El cambio se aplica la próxima vez que cargue una página.

## Cuando alguien se marcha

Abre su registro de empleado, pulsa **Edit** y cambia el estado a **Left**. Luego abre su usuario desde la página del empleado, pulsa **Edit** y desactiva **Can login**. Cambiar solo el estado deja la puerta abierta.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Añadir a un compañero:</b> <b>HR → Employees</b> → <b>Create Employee</b>.</li>
<li><b>Cambiar lo que alguien puede hacer:</b> abre el empleado → <b>Edit</b> → <b>Position</b>.</li>
<li><b>Alguien se marcha:</b> abre el empleado → <b>Edit</b> → State <b>Left</b>, luego el usuario del empleado → <b>Edit</b> → <b>Can login</b> desactivado.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>El puesto <b>Organisation Administrator</b> lleva permisos de edición de HR en tu organización, que es todo lo anterior. Los Buyers no pueden añadir ni editar personas.</li>
</ul>
</aside>
