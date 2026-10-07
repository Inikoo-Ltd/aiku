import type { Component } from "vue"
import DialogCentered from "@/Components/Websites/WebsiteDialog/Templates/DialogCentered.vue"
import DialogImageSide from "@/Components/Websites/WebsiteDialog/Templates/DialogImageSide.vue"
import DialogSubscribe from "@/Components/Websites/WebsiteDialog/Templates/DialogSubscribe.vue"
import DialogCoupon from "@/Components/Websites/WebsiteDialog/Templates/DialogCoupon.vue"

const websiteDialogComponents: Record<string, Component> = {
    "dialog-centered": DialogCentered,
    "dialog-image-side": DialogImageSide,
    "dialog-coupon": DialogCoupon,
    "dialog-subscribe": DialogSubscribe,
}

export const getWebsiteDialogComponent = (component?: string | null): Component | null => {
    return component ? websiteDialogComponents[component] ?? null : null
}
