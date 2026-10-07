import { computed, inject, ref } from "vue"
import type { Ref } from "vue"
import { DEFAULT_ACCENT } from "@/Components/Websites/WebsiteDialog/Templates/Blueprint"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

export const hasText = (html?: string | null): boolean =>
    !!(html ?? "").replace(/<[^>]*>/g, "").replace(/&nbsp;/g, " ").trim()

export const useDialogTemplate = (props: { dialogData?: WebsiteDialogTemplateData, isEditable?: boolean }) => {
    const screenType = inject<Ref<string>>("screenType", ref("desktop"))
    const openFieldWorkshop = inject<Ref<string | number | null>>("openFieldWorkshop", ref(null))

    const fields = computed(() => props.dialogData?.fields ?? {})
    const accent = computed(() => fields.value.accent || DEFAULT_ACCENT)
    const accentSoft = computed(() => `color-mix(in srgb, ${accent.value} 14%, transparent)`)
    const accentGradient = computed(() =>
        `linear-gradient(135deg, ${accent.value} 0%, color-mix(in srgb, ${accent.value} 55%, white) 100%)`
    )

    const editable = (accordionKey: string) => props.isEditable
        ? {
            class: "website-dialog-editable",
            onClick: (event: MouseEvent) => {
                event.stopPropagation()
                openFieldWorkshop.value = accordionKey
            },
        }
        : {}

    return { screenType, fields, accent, accentSoft, accentGradient, editable }
}
