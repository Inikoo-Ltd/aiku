/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

import type { Chart, TooltipModel } from "chart.js"

const TOOLTIP_ID = "chartjs-floating-tooltip"

const tooltipElement = (): HTMLDivElement => {
    const existing = document.getElementById(TOOLTIP_ID) as HTMLDivElement | null
    if (existing) return existing

    const element = document.createElement("div")
    element.id = TOOLTIP_ID
    Object.assign(element.style, {
        position: "fixed",
        zIndex: "9999",
        pointerEvents: "none",
        opacity: "0",
        transition: "opacity 120ms ease",
        background: "rgba(17, 24, 39, 0.92)",
        color: "#ffffff",
        borderRadius: "6px",
        padding: "6px 10px",
        fontSize: "12px",
        lineHeight: "1.4",
        boxShadow: "0 6px 16px rgba(0, 0, 0, 0.18)",
        whiteSpace: "nowrap",
    })
    document.body.appendChild(element)

    return element
}

const line = (text: string, style: Partial<CSSStyleDeclaration> = {}): HTMLDivElement => {
    const element = document.createElement("div")
    element.textContent = text
    Object.assign(element.style, style)
    return element
}

export const hideChartTooltip = () => {
    const element = document.getElementById(TOOLTIP_ID)
    if (element) element.style.opacity = "0"
}

/**
 * Draws a Chart.js tooltip on the page rather than inside the canvas, so a small chart (a pie a
 * few rem wide) shows it whole. Pass as plugins.tooltip.external with plugins.tooltip.enabled false.
 */
export const externalChartTooltip = ({ chart, tooltip }: { chart: Chart; tooltip: TooltipModel<any> }) => {
    const element = tooltipElement()

    if (tooltip.opacity === 0) {
        element.style.opacity = "0"
        return
    }

    element.replaceChildren(
        ...(tooltip.title ?? []).map((text) => line(text, { color: "#d1d5db", fontSize: "11px" })),
        ...(tooltip.body ?? []).flatMap((part) => part.lines).map((text) => line(text, { fontWeight: "600" })),
        ...(tooltip.afterBody ?? []).filter(Boolean).map((text) => line(text, { color: "#d1d5db", fontSize: "11px", marginTop: "2px" })),
    )

    const canvas = chart.canvas.getBoundingClientRect()
    const left = canvas.left + tooltip.caretX + 12
    const top = canvas.top + tooltip.caretY - element.offsetHeight / 2
    const maxLeft = window.innerWidth - element.offsetWidth - 8

    element.style.left = `${left > maxLeft ? canvas.left + tooltip.caretX - element.offsetWidth - 12 : left}px`
    element.style.top = `${Math.max(8, top)}px`
    element.style.opacity = "1"
}
