const textToolbar = [
    'heading', 'fontSize', 'bold', 'italic', 'underline', 'fontFamily',
    'alignLeft', 'alignCenter', 'alignRight', 'link',
    'undo', 'redo', 'highlight', 'color', 'clear'
]

const containerSection = {
    name: "Dialog box",
    icon: { icon: "fal fa-rectangle-wide", tooltip: "Dialog box" },
    key: ["container_properties"],
    accordion_key: "container",
    replaceForm: [
        { key: ["background"], label: "Background", type: "background" },
        { key: ["text"], type: "textProperty" },
        { key: ["border"], label: "Border", type: "border", useIn: ["desktop", "tablet", "mobile"] },
        { key: ["dimension"], label: "Dimension", type: "dimension", useIn: ["desktop", "tablet", "mobile"] },
    ]
}

const accentSection = {
    name: "Accent colour",
    icon: { icon: "fal fa-palette", tooltip: "Accent colour" },
    key: ["fields"],
    accordion_key: "accent",
    replaceForm: [
        { key: ["accent"], label: "Accent", type: "color" },
    ]
}

const imageSection = {
    name: "Image",
    icon: { icon: "fal fa-image", tooltip: "Image" },
    key: ["fields", "image"],
    accordion_key: "image",
    replaceForm: [
        { key: ["source"], label: "Image", type: "upload_image" },
        { key: ["alt"], label: "Alternate Text", type: "text" },
    ]
}

const textSection = (name: string, key: string) => ({
    name,
    icon: { icon: "fal fa-text", tooltip: name },
    key: ["fields", key],
    accordion_key: key,
    replaceForm: [
        { key: ["text"], type: "editorhtml", props_data: { toggle: textToolbar } }
    ]
})

const buttonSection = {
    name: "Button",
    icon: { icon: "fal fa-hand-pointer", tooltip: "Button" },
    key: ["fields"],
    accordion_key: "button",
    replaceForm: [
        { key: ["button"], type: "button" }
    ]
}

const couponSection = {
    name: "Coupon",
    icon: { icon: "fal fa-ticket-alt", tooltip: "Coupon" },
    key: ["fields", "coupon"],
    accordion_key: "coupon",
    replaceForm: [
        { key: ["label"], label: "Label", type: "text" },
        { key: ["code"], label: "Code", type: "text" },
    ]
}

const subscribeSection = {
    name: "Subscribe form",
    icon: { icon: "fal fa-envelope", tooltip: "Subscribe form" },
    key: ["fields", "subscribe"],
    accordion_key: "subscribe",
    replaceForm: [
        { key: ["placeholder"], label: "Email placeholder", type: "text" },
        { key: ["success_text"], label: "Message after subscribing", type: "text" },
    ]
}

const subscribeButtonSection = {
    name: "Subscribe button",
    icon: { icon: "fal fa-hand-pointer", tooltip: "Subscribe button" },
    key: ["fields", "button"],
    accordion_key: "button",
    replaceForm: [
        { key: ["text"], label: "Text", type: "text" },
        { key: ["container", "properties", "background"], label: "Background", type: "background" },
        { key: ["container", "properties", "text"], type: "textProperty" },
        { key: ["container", "properties", "border"], label: "Border", type: "border" },
    ]
}

const contentSections = [
    textSection("Label above title", "eyebrow"),
    textSection("Title", "title"),
    textSection("Description", "description"),
]

const blueprints: Record<string, any[]> = {
    "dialog-centered": [containerSection, accentSection, imageSection, ...contentSections, buttonSection, textSection("Small print", "note")],
    "dialog-image-side": [containerSection, accentSection, imageSection, ...contentSections, buttonSection, textSection("Small print", "note")],
    "dialog-coupon": [containerSection, accentSection, ...contentSections, couponSection, buttonSection, textSection("Small print", "note")],
    "dialog-subscribe": [containerSection, accentSection, ...contentSections, subscribeSection, subscribeButtonSection, textSection("Small print", "note")],
}

export const blueprint = blueprints["dialog-centered"]

export const getWebsiteDialogBlueprint = (component?: string | null) => blueprints[component ?? ""] ?? blueprint

export const DEFAULT_ACCENT = "rgba(79, 70, 229, 1)"
