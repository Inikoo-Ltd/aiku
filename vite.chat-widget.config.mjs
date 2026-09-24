/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 *
 * Builds the storefront chat widget as one self-contained file. It is loaded by a plain script
 * tag from a site that is not ours, so nothing may be split out, nothing may be hashed, and the
 * filename has to stay put: it is pasted into a tag manager and we do not get to edit it back.
 */

import { defineConfig } from "vite"
import vue from "@vitejs/plugin-vue"
import { fileURLToPath, URL } from "node:url"

export default defineConfig({
    cacheDir: "node_modules/.vite-chat-widget",
    publicDir: false,
    define: {
        "process.env.NODE_ENV": JSON.stringify("production"),
    },
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./resources/js", import.meta.url)),
            "@iris": fileURLToPath(new URL("./resources/js/Iris", import.meta.url)),
            "@common": fileURLToPath(new URL("./resources/js/Common", import.meta.url)),
            "@fad": fileURLToPath(new URL("./private/fa/pro-duotone-svg-icons", import.meta.url)),
            "@fal": fileURLToPath(new URL("./private/fa/pro-light-svg-icons", import.meta.url)),
            "@far": fileURLToPath(new URL("./private/fa/pro-regular-svg-icons", import.meta.url)),
            "@fas": fileURLToPath(new URL("./private/fa/pro-solid-svg-icons", import.meta.url)),
            "@fonts": fileURLToPath(new URL("./public/assets/Fonts/", import.meta.url)),
            "@art": fileURLToPath(new URL("./public/art/", import.meta.url)),
        },
    },
    build: {
        outDir: "public/chat-widget",
        emptyOutDir: true,
        cssCodeSplit: false,
        sourcemap: false,
        target: "es2019",
        lib: {
            entry: fileURLToPath(new URL("./resources/js/chat-widget.ts", import.meta.url)),
            name: "AikuChatWidget",
            formats: ["iife"],
            fileName: () => "v1.js",
            cssFileName: "v1",
        },
    },
})
