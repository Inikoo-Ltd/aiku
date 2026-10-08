---
title: Añadir personal a tu organización de agente
summary: Para responsables de agente — cómo dar a tus compañeros su propio acceso a aiku, elegir qué pueden hacer, y cerrar una cuenta cuando alguien se marcha.
date: 2026-09-30
source_date: 2026-10-08
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 2
---

<aside class="tldr">
Para el responsable (Manager) de una organización de agente. Una vez que puedes entrar, ya no necesitas que la empresa compradora añada a nadie: creas tú mismo a tus compañeros en <b>HR → Employees</b>, les das un puesto acorde a su trabajo, y un usuario y contraseña. La parte de la empresa compradora, crear tu propia primera cuenta, está en <a href="/docs/giving-an-agent-their-first-login-es">dar a un agente su primer acceso</a>.
</aside>

## Qué verán tus compañeros

Todos en tu organización ven solo tu organización y el trabajo que haces para la empresa compradora:

- **Procurement**: las **Purchase Orders** que te envían las organizaciones de la empresa compradora, una por cada uno de tus proveedores, con los depósitos que pagas por ellas, las **Stock Deliveries** que envías de vuelta, tus **Suppliers** y tu **Inbox** de proveedores. Los Managers también ven los **Settings** de procurement.
- **HR**, solo para Managers: tu propia gente.
- **Tickets**, para pedir ayuda al servicio de ayuda de la empresa compradora.

Nadie en tu organización puede ver las tiendas, los clientes o las cuentas de la empresa compradora, ni a otros agentes.

## Añadir a un compañero

Abre **HR → Employees** y pulsa **Create Employee**. El formulario es una sola página; las partes que te importan son:

- **Employment**: un **worker number** y un **alias**, ambos únicos dentro de tu organización (los nombres de pila valen), y el estado **Working**.
- **Job → Position**: en **Agent**, elige qué puede hacer la persona. **Clerk** es para alguien que trabaja con órdenes de compra, depósitos y entregas. **Manager** puede hacer todo eso y además añadir y quitar compañeros en **HR**. El puesto **Organisation Administrator** no se te ofrece: sigue en manos de la empresa compradora.
- **User credentials**: déjalo vacío para alguien que no necesita entrar. Rellena un **username** y una **password** y podrá entrar de inmediato; aiku le pide elegir su propia contraseña la primera vez.

Guarda, y pásale el usuario y la contraseña inicial.

## Cambiar lo que alguien puede hacer

Abre el empleado desde **HR → Employees**, pulsa **Edit** y cambia su **Position**. El cambio se aplica la próxima vez que cargue una página.

## Cuando alguien se marcha

Abre su registro de empleado, pulsa **Edit** y cambia el estado a **Left**. aiku anota el día en que se marchó y le quita el acceso en ese mismo momento.

Si un compañero olvida su contraseña, no puedes restablecerla tú mismo: crea un ticket desde **Tickets → New ticket** (mira <a href="/docs/asking-the-help-desk-for-help-es">pedir ayuda al servicio de ayuda</a>) y el servicio de ayuda de la empresa compradora le pone una nueva.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Añadir a un compañero:</b> <b>HR → Employees</b> → <b>Create Employee</b>.</li>
<li><b>Cambiar lo que alguien puede hacer:</b> abre el empleado → <b>Edit</b> → <b>Position</b>.</li>
<li><b>Alguien se marcha:</b> abre el empleado → <b>Edit</b> → State <b>Left</b>.</li>
<li><b>Contraseña olvidada:</b> <b>Tickets</b> → <b>New ticket</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>El puesto <b>Agent → Manager</b> lleva permisos de edición de HR en tu organización, que es todo lo anterior. Los Clerks no pueden añadir ni editar personas.</li>
</ul>
</aside>
