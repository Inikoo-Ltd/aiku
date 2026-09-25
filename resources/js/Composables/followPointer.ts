export const followPointer = (event: PointerEvent, onMove: (move: PointerEvent) => void, onEnd?: () => void) => {
    event.preventDefault()

    const onUp = () => {
        window.removeEventListener("pointermove", onMove)
        window.removeEventListener("pointerup", onUp)
        document.body.style.userSelect = ""
        onEnd?.()
    }

    document.body.style.userSelect = "none"
    window.addEventListener("pointermove", onMove)
    window.addEventListener("pointerup", onUp)
}
