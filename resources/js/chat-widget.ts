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
import { i18nVue } from "laravel-vue-i18n"
import ChatButton from "@/Components/Chat/Customer/ChatButton.vue"
import widgetStyles from "../css/chat-widget.css?inline"
import { useColorTheme } from "@/Composables/useStockList"
import { setColorStyleRootByEl } from "@/Composables/useApp"

const CONTAINER_ID = "aiku-chat-widget"

interface WidgetConfig {
    shop_id: number
    name: string
    theme: string[] | null
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
    app.use(i18nVue, {
        resolve: async () => ({ default: {} }),
    })

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
