/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 *
 * The storefront chat widget: a single script that draws Aiku chat on a site that is not ours.
 * It is loaded by a plain tag, so it carries its own Vue, its own styles and its own shop
 * context, and it must never assume Inertia, a layout store or anything else from our apps.
 */

import { createApp, h } from "vue"
import axios from "axios"
import Notifications from "@kyvg/vue3-notification"
import PrimeVue from "primevue/config"
import Aura from "@primevue/themes/aura"
import { i18nVue } from "laravel-vue-i18n"
import ChatButton from "@/Components/Chat/Customer/ChatButton.vue"
import widgetStyles from "../css/chat-widget.css?inline"
import { useColorTheme } from "@/Composables/useStockList"
import { setColorStyleRootByEl } from "@/Composables/useApp"
import { ctrans } from "@/Composables/useTrans"

const CONTAINER_ID = "aiku-chat-widget"

interface WidgetBroadcasting {
    key: string
    cluster: string | null
    host: string
    port: number
    scheme: string
}

interface WidgetConfig {
    shop_id: number
    name: string
    theme: string[] | null
    broadcasting: WidgetBroadcasting | null
}

/*
 * A conversation is a public channel, so the storefront needs no credentials to hear its replies
 * as they are written. Without this the widget only learns of an answer on its next poll, which
 * is a chat that arrives late and feels broken. Loaded on demand so a page that never opens the
 * chat never pays for it.
 */
const initEcho = async (broadcasting: WidgetBroadcasting | null): Promise<void> => {
    if (!broadcasting?.key || (window as any).Echo) {
        return
    }

    try {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import("laravel-echo"),
            import("pusher-js"),
        ])

        const isTls = broadcasting.scheme === "https"
        ;(window as any).Pusher = Pusher
        ;(window as any).Echo = new Echo({
            broadcaster: "pusher",
            key: broadcasting.key,
            cluster: broadcasting.cluster ?? "mt1",
            wsHost: broadcasting.host,
            wsPort: broadcasting.port,
            wssPort: broadcasting.port,
            forceTLS: isTls,
            encrypted: isTls,
            enabledTransports: ["ws", "wss"],
            disableStats: true,
        })
    } catch (error) {
        /* A storefront without realtime still works, it just falls back to polling. */
        console.error("Aiku chat widget: realtime unavailable", error)
    }
}

/*
 * The script knows where it came from, and that is the only reliable pointer back to us: a
 * storefront has no idea what our domain is, and hardcoding it breaks every environment but one.
 */
const resolveScript = (): HTMLScriptElement | null => {
    const current = document.currentScript as HTMLScriptElement | null

    if (current?.src) {
        return current
    }

    const scripts = Array.from(document.getElementsByTagName("script"))

    return scripts.reverse().find((script) => script.src.includes("chat-widget")) ?? null
}

const readSettings = (script: HTMLScriptElement | null) => {
    const src = script?.src ?? ""
    const url = src ? new URL(src, window.location.href) : null

    return {
        baseUrl: url ? url.origin : window.location.origin,
        /* The component stylesheet is emitted next to this script, whatever it is called. */
        stylesheet: url ? url.href.replace(/[^/]*$/, "v1.css") : null,
        key: script?.dataset.key ?? url?.searchParams.get("k") ?? null,
    }
}

const mount = (config: WidgetConfig, baseUrl: string, stylesheet: string | null) => {
    if (document.getElementById(CONTAINER_ID)) {
        return
    }

    const container = document.createElement("div")
    container.id = CONTAINER_ID

    /*
     * The shadow root protects what is inside the widget, not the element holding it. In the light
     * DOM this container has no children at all, so a storefront rule as ordinary as
     * `div:empty { display: none }` hides the whole widget, and a transform or filter on it would
     * quietly become the containing block for the fixed positioned bubble. These are pinned so the
     * host page cannot reach the one element we cannot hide behind the boundary.
     */
    const pinned: Record<string, string> = {
        display: "block",
        visibility: "visible",
        opacity: "1",
        position: "static",
        width: "auto",
        height: "auto",
        margin: "0",
        padding: "0",
        border: "0",
        transform: "none",
        filter: "none",
        perspective: "none",
        contain: "none",
        "clip-path": "none",
        "pointer-events": "auto",
    }

    Object.entries(pinned).forEach(([property, value]) => {
        container.style.setProperty(property, value, "important")
    })

    document.body.appendChild(container)

    /*
     * A shadow root so the host theme's CSS cannot reach in and ours cannot leak out. Without it
     * the widget inherits whatever the storefront does to buttons and inputs.
     */
    const shadow = container.attachShadow({ mode: "open" })

    const style = document.createElement("style")
    style.textContent = widgetStyles
    shadow.appendChild(style)

    /*
     * The component styles are compiled out of the single file components into their own
     * stylesheet, which nothing would otherwise load. Inside the shadow root it stays scoped.
     */
    if (stylesheet) {
        const link = document.createElement("link")
        link.rel = "stylesheet"
        link.href = stylesheet
        shadow.appendChild(link)
    }

    const root = document.createElement("div")
    shadow.appendChild(root)

    /*
     * Our apps set these on the document element at boot; a storefront never will, and the shadow
     * boundary means the page's own values would not be the ones we want anyway.
     */
    const theme = config.theme ?? useColorTheme[3]
    setColorStyleRootByEl(root, theme)

    const app = createApp({
        render: () =>
            h(ChatButton, {
                shopId: config.shop_id,
            }),
    })

    /*
     * ChatButton and the components under it read the host app's theme and app url from an
     * injected layout. On a storefront there is none, so it is supplied here.
     */
    app.provide("layout", {
        appUrl: baseUrl,
        app: {
            name: "chat-widget",
            theme,
        },
    })

    app.use(Notifications)

    /* The offline form and the message bubbles are built from PrimeVue inputs, which read their
       options off the plugin and throw without it. */
    app.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                /* Keep PrimeVue's own styles inside the shadow root with everything else. */
                cssLayer: false,
            },
        },
    })

    app.use(i18nVue, {
        resolve: async () => ({ default: {} }),
    })

    /* Templates call ctrans() without importing it, so it has to be global here as it is in the
       other apps; the widget ships no translations and it falls back to the original text. */
    app.config.globalProperties.ctrans = ctrans

    app.mount(root)
}

const boot = async () => {
    const { baseUrl, stylesheet, key } = readSettings(resolveScript())

    if (!key) {
        console.error("Aiku chat widget: no key on the script tag")

        return
    }

    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/widget-config`, {
            params: { key },
        })

        if (!data?.data?.shop_id) {
            return
        }

        await initEcho(data.data.broadcasting ?? null)

        mount(data.data, baseUrl, stylesheet)
    } catch (error) {
        console.error("Aiku chat widget: could not load its configuration", error)
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot)
} else {
    boot()
}
