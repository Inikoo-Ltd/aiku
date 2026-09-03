---
title: Dar a un agente su primer acceso
summary: Para la empresa compradora — cómo crear la única cuenta que necesita un agente de compras para empezar en aiku, tras lo cual gestiona a su propia gente.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, agents, supply-chain
category: hr
series: Agent access
order: 1
---

<aside class="tldr">
Los agentes son organizaciones en aiku, igual que una tienda, y su gente entra exactamente igual que tu propio personal. Creas <b>una</b> persona en la organización del agente con el puesto <b>Organisation Administrator</b> y un usuario y contraseña. A partir de ahí, esa persona añade a sus compañeros por sí misma; su parte está en <a href="/docs/adding-staff-to-your-agent-organisation-es">añadir personal a tu organización de agente</a>.
</aside>

## Cómo funcionan los accesos de los agentes

Todo agente de compras es una organización de tipo *agent*. Cuando alguien de esa organización entra, solo ve su propia organización: el menú **Procurement** con las órdenes de compra a proveedores, las entregas de stock y el tablero de la lista de compras que le conciernen, un menú **HR** para su propia gente, y sus ajustes. Nunca ven tus tiendas, tus clientes ni a los otros agentes.

Nadie tiene acceso hasta que alguien se lo da, y el primero tiene que venir de ti. Después, la propiedad pasa al agente.

## Crear el primer usuario del agente

Necesitas permisos de edición de HR en la organización del agente; los administradores del grupo los tienen en todas las organizaciones.

1. Cambia a la organización del agente con el selector de organización en la parte superior de la página.
2. Ve a **HR → Employees** y pulsa **Create Employee**.
3. En **Employment**, rellena los campos obligatorios. El **worker number** y el **alias** solo necesitan ser únicos dentro de esa organización de agente, así que el nombre de pila de la persona sirve para ambos. Pon el estado en **Working**.
4. En **Job**, en **Position**, elige **Organisation Administrator**. Este es el paso que convierte a un empleado normal en alguien que puede gestionar la organización, incluyendo añadir y quitar a otras personas. Si te lo saltas, entrarán a una pantalla vacía.
5. En **User credentials**, escribe el **username** con el que entrará y una **password** inicial. aiku le obliga a elegir una contraseña nueva la primera vez que entra, así que esta solo necesita sobrevivir hasta que se la hayas pasado.
6. Guarda.

Envíale la dirección de la aplicación, el usuario y la contraseña inicial por el canal que ya uses con ese agente. Eso es todo lo que necesita.

## Agentes que ya tenían acceso en Aurora

Los agentes que ya tenían un acceso en el sistema antiguo mantienen su usuario y contraseña. Su cuenta se trasladó con el puesto Organisation Administrator, y su primer inicio de sesión en aiku convierte la contraseña antigua de forma transparente. No tienes que hacer nada por ellos.

## Si el agente se queda fuera de su cuenta

Mantienes permisos de edición de HR en la organización del agente, así que siempre puedes abrir el empleado del agente desde **HR → Employees**, ir a su usuario y ponerle una contraseña nueva, o crear un segundo administrador de la misma forma que el primero.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Crear el primer usuario del agente:</b> selector de organización → la organización del agente → <b>HR → Employees</b> → <b>Create Employee</b> → Position <b>Organisation Administrator</b> → rellena <b>User credentials</b>.</li>
<li><b>Restablecer a un agente bloqueado:</b> la organización del agente → <b>HR → Employees</b> → la persona → su usuario → <b>Edit</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Crear un usuario en una organización de agente requiere permisos de <b>HR edit</b> en esa organización. Los administradores del grupo los tienen en todas partes.</li>
</ul>
</aside>
