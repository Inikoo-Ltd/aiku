import type { Component } from "vue"
import DialogCentered from "@/Components/Websites/WebsiteDialog/Templates/DialogCentered.vue"
import DialogImageSide from "@/Components/Websites/WebsiteDialog/Templates/DialogImageSide.vue"

const websiteDialogComponents: Record<string, Component> = {
    "dialog-centered": DialogCentered,
    "dialog-image-side": DialogImageSide,
}

export const getWebsiteDialogComponent = (component?: string | null): Component | null => {
    return component ? websiteDialogComponents[component] ?? null : null
}
