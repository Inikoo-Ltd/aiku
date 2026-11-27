/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

export const MARKS_TOPIC = "marks"
export const PAGE_MARKS_CHANNEL = "aiku-staff-call-marks"

const FADE_AFTER_MS = 3000
const FADE_MS = 1000
const POINTER_MS = 3000
const STALE_STROKE_MS = 10000
const MAX_PAYLOAD_BYTES = 2048
const MAX_BATCH_POINTS = 64
const POINTS_PER_SECOND = 150

/**
 * s is the identity of the person whose shared screen the mark is on, so marks for a screen that has since changed are dropped.
 * p pointer, h pointer gone, d stroke points (flat x,y list, e=1 on the last batch), c clear all, o drawing off for others.
 */
export type MarkMessage =
    | { t: "p"; s: string; x: number; y: number }
    | { t: "h"; s: string }
    | { t: "d"; s: string; k: number; p: number[]; e?: 1 }
    | { t: "c"; s: string }
    | { t: "o"; s: string; off: boolean }

/** What the call tab of the person sharing sends to their other aiku tabs, so marks show on the page they are looking at. */
export interface PageMarkEvent {
    type: "mark" | "end"
    identity?: string
    name?: string
    mark?: MarkMessage
    surface?: SharedSurface
    width?: number
    height?: number
    handle?: string | null
}

export type Placement = (x: number, y: number) => [number, number]

const clamp01 = (value: unknown): number | null => (typeof value === "number" && Number.isFinite(value) ? Math.min(1, Math.max(0, value)) : null)

const decoder = new TextDecoder()
const encoder = new TextEncoder()

export const encodeMark = (mark: MarkMessage): Uint8Array => encoder.encode(JSON.stringify(mark))

/** Everything that arrives over the data channel is untrusted: numbers only, clamped to the picture, small batches. */
export const readMark = (payload: Uint8Array): MarkMessage | null => {
    if (payload.byteLength > MAX_PAYLOAD_BYTES) return null
    let raw: any
    try {
        raw = JSON.parse(decoder.decode(payload))
    } catch {
        return null
    }
    if (!raw || typeof raw.s !== "string" || raw.s.length > 128) return null
    const s = raw.s
    switch (raw.t) {
        case "p": {
            const x = clamp01(raw.x), y = clamp01(raw.y)
            return x === null || y === null ? null : { t: "p", s, x, y }
        }
        case "h":
        case "c":
            return { t: raw.t, s }
        case "o":
            return { t: "o", s, off: raw.off === true }
        case "d": {
            if (!Number.isInteger(raw.k) || !Array.isArray(raw.p) || raw.p.length % 2 || raw.p.length > MAX_BATCH_POINTS * 2) return null
            const p = raw.p.map(clamp01)
            if (p.some((value: number | null) => value === null)) return null
            return { t: "d", s, k: raw.k, p, ...(raw.e === 1 ? { e: 1 as const } : {}) }
        }
    }
    return null
}

/** A per-person budget of points per second, so one sender cannot flood everyone's canvas. */
export const markRateLimiter = () => {
    const buckets = new Map<string, { tokens: number; at: number }>()
    return (identity: string, points: number, now = performance.now()): boolean => {
        const bucket = buckets.get(identity) ?? { tokens: POINTS_PER_SECOND, at: now }
        bucket.tokens = Math.min(POINTS_PER_SECOND, bucket.tokens + ((now - bucket.at) / 1000) * POINTS_PER_SECOND)
        bucket.at = now
        buckets.set(identity, bucket)
        if (bucket.tokens < points) return false
        bucket.tokens -= points
        return true
    }
}

export const pointsIn = (mark: MarkMessage): number => (mark.t === "d" ? Math.max(1, mark.p.length / 2) : 1)

const COLOURS = ["#ef4444", "#f59e0b", "#22c55e", "#06b6d4", "#3b82f6", "#a855f7", "#ec4899", "#84cc16"]

export const markColour = (identity: string): string => {
    let hash = 0
    for (const char of identity) hash = (hash * 31 + char.charCodeAt(0)) | 0
    return COLOURS[Math.abs(hash) % COLOURS.length]
}

interface Stroke {
    points: number[]
    lastAt: number
    endedAt: number | null
    labelled: boolean
}

interface Person {
    name: string
    colour: string
    strokes: Map<number, Stroke>
    pointer: { x: number; y: number; at: number } | null
}

export class MarkBoard {
    people = new Map<string, Person>()
    onChange: (() => void) | null = null

    apply(identity: string, name: string, mark: MarkMessage, now = performance.now()) {
        if (mark.t === "c") {
            this.clear()
            return
        }
        if (mark.t === "o") return
        const person = this.people.get(identity) ?? { name, colour: markColour(identity), strokes: new Map(), pointer: null }
        this.people.set(identity, person)
        if (mark.t === "p") {
            person.pointer = { x: mark.x, y: mark.y, at: now }
        } else if (mark.t === "h") {
            person.pointer = null
        } else {
            let stroke = person.strokes.get(mark.k)
            if (!stroke) {
                const liveStroke = [...person.strokes.values()].some((other) => other.endedAt === null || now - other.endedAt < FADE_AFTER_MS)
                stroke = { points: [], lastAt: now, endedAt: null, labelled: !liveStroke }
                person.strokes.set(mark.k, stroke)
            }
            stroke.points.push(...mark.p)
            stroke.lastAt = now
            if (mark.e) stroke.endedAt = now
            if (mark.p.length >= 2) person.pointer = { x: mark.p[mark.p.length - 2], y: mark.p[mark.p.length - 1], at: now }
        }
        this.onChange?.()
    }

    clear() {
        this.people.clear()
        this.onChange?.()
    }

    /** Drops what has faded; true while anything is still on screen, so the drawing loop can stop when nothing is. */
    prune(now = performance.now()): boolean {
        for (const [identity, person] of this.people) {
            for (const [key, stroke] of person.strokes) {
                const end = stroke.endedAt ?? (now - stroke.lastAt > STALE_STROKE_MS ? stroke.lastAt : null)
                if (end !== null && now - end > FADE_AFTER_MS + FADE_MS) person.strokes.delete(key)
            }
            if (person.pointer && now - person.pointer.at > POINTER_MS) person.pointer = null
            if (!person.strokes.size && !person.pointer) this.people.delete(identity)
        }
        return this.people.size > 0
    }

    draw(context: CanvasRenderingContext2D, place: Placement, scale: number, now = performance.now()) {
        context.lineCap = "round"
        context.lineJoin = "round"
        context.font = `600 ${Math.round(11 * scale)}px system-ui, sans-serif`
        for (const person of this.people.values()) {
            for (const stroke of person.strokes.values()) {
                const end = stroke.endedAt ?? (now - stroke.lastAt > STALE_STROKE_MS ? stroke.lastAt : null)
                const alpha = end === null ? 1 : Math.max(0, Math.min(1, 1 - (now - end - FADE_AFTER_MS) / FADE_MS))
                if (alpha <= 0 || stroke.points.length < 2) continue
                context.globalAlpha = alpha
                context.beginPath()
                for (let index = 0; index < stroke.points.length; index += 2) {
                    const [x, y] = place(stroke.points[index], stroke.points[index + 1])
                    if (index === 0) context.moveTo(x, y)
                    else context.lineTo(x, y)
                }
                if (stroke.points.length === 2) {
                    const [x, y] = place(stroke.points[0], stroke.points[1])
                    context.lineTo(x + 0.1, y)
                }
                context.strokeStyle = "rgba(0,0,0,0.55)"
                context.lineWidth = 6 * scale
                context.stroke()
                context.strokeStyle = person.colour
                context.lineWidth = 3.5 * scale
                context.stroke()
                if (stroke.labelled) {
                    const [x, y] = place(stroke.points[0], stroke.points[1])
                    this.label(context, person, x + 8 * scale, y - 18 * scale, scale)
                }
            }
            if (person.pointer) {
                context.globalAlpha = Math.max(0, Math.min(1, (POINTER_MS - (now - person.pointer.at)) / 500))
                const [x, y] = place(person.pointer.x, person.pointer.y)
                context.beginPath()
                context.arc(x, y, 6 * scale, 0, Math.PI * 2)
                context.fillStyle = person.colour
                context.fill()
                context.lineWidth = 2 * scale
                context.strokeStyle = "#fff"
                context.stroke()
                this.label(context, person, x + 10 * scale, y + 4 * scale, scale)
            }
        }
        context.globalAlpha = 1
    }

    private label(context: CanvasRenderingContext2D, person: Person, x: number, labelY: number, scale: number) {
        const width = context.measureText(person.name).width + 8 * scale
        const height = 16 * scale
        const y = Math.max(2, labelY)
        context.fillStyle = person.colour
        context.beginPath()
        context.roundRect(x, y, width, height, 3 * scale)
        context.fill()
        context.fillStyle = "#fff"
        context.fillText(person.name, x + 4 * scale, y + 12 * scale)
    }
}

/** Where a picture of picWidth x picHeight sits inside a box when fitted with object-fit: contain (letterboxed). */
export const fittedRect = (boxWidth: number, boxHeight: number, picWidth: number, picHeight: number) => {
    const scale = Math.min(boxWidth / picWidth, boxHeight / picHeight)
    const width = picWidth * scale
    const height = picHeight * scale
    return { x: (boxWidth - width) / 2, y: (boxHeight - height) / 2, width, height }
}

export type SharedSurface = "browser" | "window" | "monitor"

const sameShape = (width: number, height: number, picWidth: number, picHeight: number) => Math.abs(width / height - picWidth / picHeight) < 0.03

/**
 * Where a point of the shared picture falls on this aiku page, in viewport pixels, or null when this page cannot be in
 * the picture. A tab is exact; a window is offset by the browser's own bars; a whole screen is an estimate from the
 * window's position, which can be a few pixels off and is less reliable with several monitors or a zoomed page.
 */
export const pagePlacement = (surface: SharedSurface, picWidth: number, picHeight: number): Placement | null => {
    if (surface === "browser") {
        return (x, y) => [x * window.innerWidth, y * window.innerHeight]
    }
    const side = Math.max(0, (window.outerWidth - window.innerWidth) / 2)
    const top = Math.max(0, window.outerHeight - window.innerHeight - side)
    if (surface === "window") {
        if (!sameShape(window.outerWidth, window.outerHeight, picWidth, picHeight)) return null
        return (x, y) => [x * window.outerWidth - side, y * window.outerHeight - top]
    }
    const screen = window.screen as Screen & { availLeft?: number; availTop?: number }
    if (!sameShape(screen.width, screen.height, picWidth, picHeight)) return null
    const screenLeft = screen.availLeft ?? 0
    // ponytail: a top edge under ~40px is taken as the menu bar of the main screen; the Window Management API would give exact screen origins
    const screenTop = (screen.availTop ?? 0) <= 40 ? 0 : screen.availTop!
    return (x, y) => [x * screen.width - (window.screenX - screenLeft) - side, y * screen.height - (window.screenY - screenTop) - top]
}
