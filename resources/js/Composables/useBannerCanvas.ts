export interface CanvasBox {
    left: number
    top: number
    width: number
    height: number
}

export interface EditableTarget extends CanvasBox {
    id: string
    key: string
    scope: "slide" | "common"
    label: string
    isText: boolean
    text: string
}

export const BACKGROUND_KEY = "background"

/**
 * Fields a selection opens instead of its own path. Clicking the banner
 * background opens Background Type, which is always visible, rather than the
 * colour picker that only shows for colour slides.
 */
const FIELD_OVERRIDES: Record<string, string> = {
    [BACKGROUND_KEY]: "backgroundType"
}

export const EDITABLE_LABELS: Record<string, string> = {
    background: "Background",
    "centralStage.title": "Title",
    "centralStage.subtitle": "Subtitle",
    "centralStage.titles": "Text block",
    "corners.topLeft": "Top left",
    "corners.topMiddle": "Top middle",
    "corners.topRight": "Top right",
    "corners.bottomLeft": "Bottom left",
    "corners.bottomMiddle": "Bottom middle",
    "corners.bottomRight": "Bottom right"
}

const INLINE_EDITABLE_KEYS = ["centralStage.title", "centralStage.subtitle"]

export const isInlineEditable = (key: string): boolean => INLINE_EDITABLE_KEYS.includes(key)

export const labelForEditableKey = (key: string): string => EDITABLE_LABELS[key] ?? key

/**
 * Path of the blueprint field that edits a canvas selection: the slide editor
 * keeps its fields under `layout`, the common editor under `common`.
 */
export const fieldPathForEditableKey = (key: string, scope: "slide" | "common"): string => {
    return `${scope === "common" ? "common" : "layout"}.${FIELD_OVERRIDES[key] ?? key}`
}

/**
 * Position of a measured node inside its stage, expressed in the stage's own
 * untransformed pixels so the overlay can be rendered inside the same scaled
 * container as the banner.
 */
export const relativeBox = (node: DOMRectReadOnly, stage: DOMRectReadOnly, scale = 1): CanvasBox => {
    const safeScale = scale > 0 ? scale : 1

    return {
        left: (node.left - stage.left) / safeScale,
        top: (node.top - stage.top) / safeScale,
        width: node.width / safeScale,
        height: node.height / safeScale
    }
}

export const isMeasurableBox = (box: CanvasBox): boolean => box.width > 0 && box.height > 0

export const targetId = (key: string, scope: string): string => `${scope}:${key}`

export const dedupeTargets = <T extends { id: string }>(targets: T[]): T[] => {
    const seen = new Set<string>()

    return targets.filter((target) => {
        if (seen.has(target.id)) {
            return false
        }

        seen.add(target.id)

        return true
    })
}

/**
 * Smallest box wins so a corner sitting on top of a full width text block stays
 * reachable.
 */
export const sortTargetsBySize = <T extends CanvasBox>(targets: T[]): T[] => {
    return [...targets].sort((a, b) => b.width * b.height - a.width * a.height)
}
