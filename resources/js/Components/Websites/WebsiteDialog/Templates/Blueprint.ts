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
        { key: ["padding"], label: "Padding", type: "padding", useIn: ["desktop", "tablet", "mobile"] },
        { key: ["border"], label: "Border", type: "border", useIn: ["desktop", "tablet", "mobile"] },
        { key: ["dimension"], label: "Dimension", type: "dimension", useIn: ["desktop", "tablet", "mobile"] },
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

export const blueprint = [
    containerSection,
    imageSection,
    textSection("Title", "title"),
    textSection("Description", "description"),
    buttonSection,
]
