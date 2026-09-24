/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 21:16:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */
import { createSSRApp, h } from "vue"
import { renderToString } from "@vue/server-renderer"
import { createInertiaApp } from "@inertiajs/vue3"
import createServer from "@inertiajs/vue3/server"
import { ZiggyVue } from "ziggy-js"

import { createPinia } from "pinia"
import Notifications from "@kyvg/vue3-notification"
import FloatingVue from "floating-vue"
import "floating-vue/dist/style.css"
import PrimeVue from "primevue/config"
import Aura from "@primevue/themes/aura"
import { definePreset } from "@primevue/themes"
import { i18nVue } from "laravel-vue-i18n"
import { ctrans } from "@/Composables/useTrans"
import { irisI18nOptions, loadLocaleMessages, normalizeLocale } from "@/Composables/useIrisTranslations"
import IrisLayout from "@/Layouts/Iris.vue"
import cluster from "node:cluster"
import { existsSync, readFileSync } from "node:fs"
import { fileURLToPath } from "node:url"

if (cluster.isPrimary) {
	cluster.on("exit", (worker, code, signal) => {
		if (signal === "SIGTERM" || signal === "SIGINT") {
			return
		}
		console.error(`SSR worker ${worker.process.pid} exited (${signal ?? code}), respawning`)
		cluster.fork()
	})
}


const loadIrisManifest = () => {
	try {
		return JSON.parse(readFileSync(fileURLToPath(new URL("../../public/iris/manifest.json", import.meta.url)), "utf8"))
	} catch {
		return {}
	}
}

const irisManifest = loadIrisManifest()

const collectChunkCss = (key, cssFiles, visited = new Set()) => {
	if (visited.has(key) || !irisManifest[key]) {
		return cssFiles
	}
	visited.add(key)
	irisManifest[key].css?.forEach((file) => cssFiles.add(file))
	irisManifest[key].imports?.forEach((imported) => collectChunkCss(imported, cssFiles, visited))

	return cssFiles
}

const entryCss = collectChunkCss("resources/js/app-iris.js", new Set())

const renderedBlocksStylesheets = (renderedModules) => {
	if (existsSync(fileURLToPath(new URL("../../public/iris.hot", import.meta.url)))) {
		return []
	}

	const cssFiles = new Set()
	renderedModules.forEach((module) => collectChunkCss(module, cssFiles))

	return [...cssFiles]
		.filter((file) => !entryCss.has(file))
		.map((file) => `<link rel="stylesheet" href="/iris/${file}">`)
}

const MyPreset = definePreset(Aura, {
	semantic: {
		primary: {
			50: "{gray.50}",
			100: "{gray.100}",
			200: "{gray.200}",
			300: "{gray.300}",
			400: "{gray.400}",
			500: "{gray.500}",
			600: "{gray.600}",
			700: "{gray.700}",
			800: "{gray.800}",
			900: "{gray.900}",
			950: "{gray.950}",
		},
	},
})

createServer(
	async (page) => {
		const irisLocale = normalizeLocale(page.props?.iris?.locale)
		const irisLocaleMessages = await loadLocaleMessages(irisLocale)

		const renderedModules = new Set()

		const response = await createInertiaApp({
			page,
			render: async (app) => {
				const ssrContext = {}
				const html = await renderToString(app, ssrContext)
				ssrContext.modules?.forEach((module) => renderedModules.add(module))

				return html
			},
			title: (title) => `${title}`,
			resolve: (name) => {
				const pages = {
					...import.meta.glob("./Pages/Iris/**/*.vue", { eager: true }),
					...import.meta.glob("./Iris/Pages/**/*.vue", { eager: true }),
				}

				const page =
					pages[`./Pages/Iris/${name}.vue`] ||
					pages[`./Iris/Pages/${name}.vue`]

				if (!page) {
					throw new Error(`Page not found: ${name}`)
				}

				const component = page.default ?? page
				component.layout = component.layout || IrisLayout

				return component
			},
			setup({ App, props, plugin }) {
				const app = createSSRApp({ render: () => h(App, props) })

				app.config.globalProperties.ctrans = ctrans

				return app
					.use(i18nVue, irisI18nOptions(irisLocale, irisLocaleMessages))
					.use(Notifications)
					.use(FloatingVue)
					.use(PrimeVue, {
						theme: {
							preset: MyPreset,
							options: {
								darkModeSelector: ".my-app-dark", // dark mode of Primevue
								// depends .my-add-dark in
								// <html>
							},
						},
					})
					.use(plugin)
					.use(createPinia())
					.use(ZiggyVue, {
						...page.props.ziggy,
						location: new URL(page.props.ziggy.location),
					})
			},
		})

		response.head.unshift(...renderedBlocksStylesheets(renderedModules))

		return response
	},
	{
		port: import.meta.env.VITE_INERTIA_SSR_PORT ?? 13714,
		cluster: true,
	}
)
