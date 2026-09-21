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
    isResizable: boolean
    modelPath: string | null
    text: string
}

export type ResizeAxis = "width" | "height" | "both"

export interface CardSize {
    width: number
    height: number
}

export const BACKGROUND_KEY = "background"
export const CARD_KEY_PREFIX = "card."

// Same bounds as the Width and Height sliders in CardsBuilder.
export const CARD_WIDTH_RANGE = { min: 10, max: 100 }
export const CARD_HEIGHT_RANGE = { min: 10, max: 800 }

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

export const isResizable = (key: string): boolean => key.startsWith(CARD_KEY_PREFIX)

export const labelForEditableKey = (key: string): string => {
    if (EDITABLE_LABELS[key]) {
        return EDITABLE_LABELS[key]
    }

    if (key.startsWith(CARD_KEY_PREFIX)) {
        const index = key.slice(CARD_KEY_PREFIX.length).match(/(\d+)$/)?.[1]

        return index ? `Card ${index}` : "Card"
    }

    return key
}

const clamp = (value: number, { min, max }: { min: number; max: number }) => {
    return Math.min(max, Math.max(min, value))
}

/**
 * Card size after a drag. The card is laid out in percent of the banner width
 * and pixels of height, then scaled by the responsive preview, so the pointer
 * delta is divided by the total on screen scale before it is applied.
 */
export const resizeCardFromDrag = (
    start: CardSize,
    delta: { x: number; y: number },
    axis: ResizeAxis,
    stage: { totalScale: number; containerWidth: number }
): CardSize => {
    const scale = stage.totalScale > 0 ? stage.totalScale : 1
    const containerWidth = stage.containerWidth > 0 ? stage.containerWidth : 1

    const width =
        axis === "height"
            ? start.width
            : start.width + ((delta.x / scale) / containerWidth) * 100

    const height = axis === "width" ? start.height : start.height + delta.y / scale

    return {
        width: Math.round(clamp(width, CARD_WIDTH_RANGE)),
        height: Math.round(clamp(height, CARD_HEIGHT_RANGE))
    }
}

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

/**
 * Room to type in. The editor keeps the middle of the text where it was and
 * widens both sides, so a growing title does not get clipped mid word and a
 * centred title does not appear to drift.
 */
export const expandBoxForEditing = (box: CanvasBox, containerWidth = 0, minSlack = 48): CanvasBox => {
    const slack = Math.max(minSlack, box.width * 0.2)

    let left = box.left - slack / 2
    let width = box.width + slack

    if (containerWidth > 0) {
        width = Math.min(width, containerWidth)
        left = Math.min(Math.max(left, 0), containerWidth - width)
    }

    return { ...box, left, width }
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
