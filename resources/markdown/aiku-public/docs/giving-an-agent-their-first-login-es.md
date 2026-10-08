---
title: Dar a un agente su primer acceso
summary: Para la empresa compradora — cómo crear la única cuenta que necesita un agente de compras para empezar en aiku, tras lo cual gestiona a su propia gente.
date: 2026-09-30
source_date: 2026-10-08
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 1
---

<aside class="tldr">
Los agentes son organizaciones en aiku, igual que una tienda, y su gente entra exactamente igual que tu propio personal. Creas <b>una</b> persona en la organización del agente con el puesto <b>Agent → Manager</b> y un usuario y contraseña. A partir de ahí, esa persona añade a sus compañeros por sí misma; su parte está en <a href="/docs/adding-staff-to-your-agent-organisation-es">añadir personal a tu organización de agente</a>.
</aside>

## Cómo funcionan los accesos de los agentes

Todo agente de compras es una organización de tipo *agent*. Cuando alguien de esa organización entra, solo ve su propia organización y solo su trabajo:

- el menú **Procurement**: las **Purchase Orders** que le envían tus organizaciones, una por cada uno de sus proveedores, con los depósitos que paga por ellas, las **Stock Deliveries** que te envía, sus propios **Suppliers** y su **Inbox** de proveedores;
- el menú **HR**, para su propia gente;
- **Tickets**, para pedir ayuda a tu servicio de ayuda.

Nunca ven los menús del grupo, tus tiendas, tus clientes, tus cuentas ni a los otros agentes. Si abren una de esas páginas por su dirección, ven una página **Forbidden** (prohibido).

Nadie tiene acceso hasta que alguien se lo da, y el primero tiene que venir de ti. Después, la propiedad pasa al agente.

## Crear el primer usuario del agente

Necesitas permisos de edición de HR en la organización del agente; los administradores del grupo los tienen en todas las organizaciones.

1. Cambia a la organización del agente con el selector de organización en la parte superior de la página.
2. Ve a **HR → Employees** y pulsa **Create Employee**.
3. En **Employment**, rellena los campos obligatorios. El **worker number** y el **alias** solo necesitan ser únicos dentro de esa organización de agente, así que el nombre de pila de la persona sirve para ambos. Pon el estado en **Working**.
4. En **Job**, en **Position**, elige **Agent → Manager**. Este es el paso que convierte a un empleado normal en alguien que puede gestionar el trabajo del agente, incluyendo añadir y quitar a otras personas. Si te lo saltas, entrarán a una pantalla vacía. No des **Organisation Administrator** al personal de agentes: abre la contabilidad, los almacenes y los ajustes de la organización, que los agentes no usan.
5. En **User credentials**, escribe el **username** con el que entrará y una **password** inicial. aiku le obliga a elegir una contraseña nueva la primera vez que entra, así que esta solo necesita sobrevivir hasta que se la hayas pasado.
6. Guarda.

Envíale la dirección de la aplicación, el usuario y la contraseña inicial por el canal que ya uses con ese agente. Eso es todo lo que necesita.

## Agentes que ya tenían acceso en Aurora

Los agentes que ya tenían un acceso en el sistema antiguo mantienen su usuario y contraseña, y su primer inicio de sesión en aiku convierte la contraseña antigua de forma transparente. Su cuenta se trasladó con el puesto **Organisation Administrator**: abre su empleado, pulsa **Edit**, marca **Agent → Manager** y desmarca **Organisation Administrator**, para que vean la vista del agente.

## Si el agente se queda fuera de su cuenta

Mantienes permisos de edición de HR en la organización del agente, así que siempre puedes abrir el empleado del agente desde **HR → Employees**, ir a su usuario y ponerle una contraseña nueva, o crear un segundo administrador de la misma forma que el primero. Los administradores de agente no pueden cambiar un acceso por sí mismos, así que los restablecimientos de contraseña de cualquiera de su gente te llegan a ti.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Crear el primer usuario del agente:</b> selector de organización → la organización del agente → <b>HR → Employees</b> → <b>Create Employee</b> → Position <b>Agent → Manager</b> → rellena <b>User credentials</b>.</li>
<li><b>Restablecer a un agente bloqueado:</b> la organización del agente → <b>HR → Employees</b> → la persona → su usuario → <b>Edit</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Crear un usuario en una organización de agente requiere permisos de <b>HR edit</b> en esa organización. Los administradores del grupo los tienen en todas partes.</li>
<li>Poner una contraseña nueva a un agente requiere permisos de administración del sistema, que los agentes nunca tienen.</li>
</ul>
</aside>
