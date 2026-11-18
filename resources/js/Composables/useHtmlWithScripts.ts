import { computed, onMounted, type Ref, watch } from "vue"

const scriptTagPattern = /<script\b[^>]*>[\s\S]*?<\/script\s*>/gi

const extractScripts = (html: string): HTMLScriptElement[] => {
    const parsedDocument = new DOMParser().parseFromString(html, "text/html")
    return Array.from(parsedDocument.querySelectorAll("script"))
}

const injectScript = (sourceScript: HTMLScriptElement, target: HTMLElement): Promise<void> => {
    return new Promise((resolve) => {
        const executableScript = document.createElement("script")
        Array.from(sourceScript.attributes).forEach((attribute) => {
            executableScript.setAttribute(attribute.name, attribute.value)
        })
        executableScript.text = sourceScript.text

        const shouldWaitForLoad = sourceScript.src && !sourceScript.hasAttribute("async") && !sourceScript.hasAttribute("defer")
        if (shouldWaitForLoad) {
            executableScript.async = false
            executableScript.onload = () => resolve()
            executableScript.onerror = () => resolve()
        }

        target.appendChild(executableScript)

        if (!shouldWaitForLoad) {
            resolve()
        }
    })
}

export const encodeScriptBlockHtml = (html: string): string => encodeURIComponent(html)

export const decodeScriptBlockHtml = (encoded: string): string => {
    try {
        return decodeURIComponent(encoded)
    } catch {
        return encoded
    }
}

export const renderHtmlWithScripts = async (target: HTMLElement, html: string): Promise<void> => {
    target.innerHTML = html.replace(scriptTagPattern, "")
    for (const script of extractScripts(html)) {
        await injectScript(script, target)
    }
}

export const useInlineScriptBlocks = (html: Ref<string | undefined | null>, container: Ref<HTMLElement | null>) => {
    const renderScriptBlocks = async () => {
        const blocks = Array.from(container.value?.querySelectorAll<HTMLElement>("[data-script-block]") ?? [])
        for (const block of blocks) {
            block.classList.add("not-prose")
            block.style.maxWidth = "100%"
            block.style.overflowX = "auto"
            block.style.margin = "1.5rem 0"
            await renderHtmlWithScripts(block, decodeScriptBlockHtml(block.getAttribute("data-html") ?? ""))
        }
    }

    onMounted(renderScriptBlocks)
    watch(html, renderScriptBlocks, { flush: "post" })
}

export const useHtmlWithScripts = (html: Ref<string | undefined | null>, container: Ref<HTMLElement | null>) => {
    const htmlWithoutScripts = computed(() => String(html.value ?? "").replace(scriptTagPattern, ""))

    const runScripts = async () => {
        if (!container.value || !html.value) {
            return
        }

        for (const script of extractScripts(html.value)) {
            await injectScript(script, container.value)
        }
    }

    onMounted(runScripts)
    watch(html, runScripts, { flush: "post" })

    return { htmlWithoutScripts }
}
