import { ref, watch, Ref } from "vue"
import type { WorkshopShortcut } from "@/Composables/useWorkshopShortcuts"

const HIGHLIGHT_CLASS = "workshop-highlight-links"
const CONTENT_LINK = `html.${HIGHLIGHT_CLASS} section[data-block-id] a`
const MISSING_ADDRESS = [":not([href])", "[href=\"\"]", "[href=\"#\"]"]

const missingAddress = (selector: string, suffix = "") =>
    MISSING_ADDRESS.map(condition => `${selector}${condition}${suffix}`).join(",\n")

const HIGHLIGHT_STYLE = `
  ${CONTENT_LINK} {
    background-color: #fef9c3;
    color: #1d4ed8 !important;
    outline: 2px dashed #2563eb;
    outline-offset: 2px;
    border-radius: 2px;
  }
  ${CONTENT_LINK}::after {
    content: "🔗";
    font-size: 0.75em;
    margin-left: 2px;
  }
  ${missingAddress(CONTENT_LINK)} {
    background-color: #fee2e2;
    color: #b91c1c !important;
    outline-color: #dc2626;
  }
  ${missingAddress(CONTENT_LINK, "::after")} {
    content: "⚠";
  }
  ${CONTENT_LINK}:has(img) {
    position: relative;
    display: inline-block;
    background-color: transparent;
  }
  ${CONTENT_LINK}:has(img)::after {
    content: "🔗";
    position: absolute;
    right: 8px;
    bottom: 8px;
    margin: 0;
    padding: 4px 8px;
    font-size: 12px;
    line-height: 1;
    border-radius: 9999px;
    background-color: #2563eb;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
  }
  ${missingAddress(`${CONTENT_LINK}:has(img)`, "::after")} {
    content: "⚠";
    color: #ffffff;
    background-color: #dc2626;
  }
`

export const useHighlightLinks = (iframe: Ref<HTMLIFrameElement | null | undefined | any>) => {
    const isHighlightingLinks = ref(false)

    const applyToIframe = () => {
        const iframeDocument = (iframe.value as HTMLIFrameElement | null)?.contentDocument
        if (!iframeDocument?.documentElement) {
            return
        }

        if (!iframeDocument.getElementById(HIGHLIGHT_CLASS)) {
            const style = iframeDocument.createElement("style")
            style.id = HIGHLIGHT_CLASS
            style.textContent = HIGHLIGHT_STYLE
            iframeDocument.head.appendChild(style)
        }

        iframeDocument.documentElement.classList.toggle(HIGHLIGHT_CLASS, isHighlightingLinks.value)
    }

    const toggleHighlightLinks = () => {
        isHighlightingLinks.value = !isHighlightingLinks.value
    }

    watch(isHighlightingLinks, applyToIframe)

    const highlightLinksShortcut: WorkshopShortcut = {
        id: "highlight-links",
        group: "Editor",
        label: "Show or hide link highlights",
        combos: [["L"]],
        run: toggleHighlightLinks,
    }

    return { isHighlightingLinks, toggleHighlightLinks, applyToIframe, highlightLinksShortcut }
}
