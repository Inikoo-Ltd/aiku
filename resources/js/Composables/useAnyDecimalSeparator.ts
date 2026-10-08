const localeDecimalSeparator = new Intl.NumberFormat().format(1.1).replace(/\d/g, "")

export const acceptAnyDecimalSeparator = (event: KeyboardEvent) => {
    if (![".", ","].includes(event.key) || event.key === localeDecimalSeparator) {
        return
    }

    event.preventDefault()
    event.stopPropagation()
    event.target?.dispatchEvent(new KeyboardEvent("keypress", { key: localeDecimalSeparator, bubbles: true, cancelable: true }))
}
