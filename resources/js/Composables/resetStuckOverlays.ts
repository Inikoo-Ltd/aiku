/**
 * Clears overlay side effects that outlive the component that created them.
 *
 * Modal libraries (Headless UI, PrimeVue) apply body-level side effects while a
 * dialog is open: an `inert` attribute on the app root, a `.p-dialog-mask`
 * teleported to `<body>`, and the `p-overflow-hidden` scroll lock. When the
 * owning component is torn down mid-open (e.g. an Inertia SPA navigation while a
 * modal is visible) those side effects can be left behind, silently blocking all
 * clicks until a hard refresh rebuilds the DOM.
 *
 * Runs only when no modal is currently open, so it never touches the side
 * effects of a dialog that is legitimately mounted on the freshly loaded page.
 */
export const resetStuckOverlays = (): void => {
    if (typeof document === "undefined") {
        return
    }

    const headlessPortal = document.getElementById("headlessui-portal-root")
    const hasOpenModal =
        (headlessPortal?.children.length ?? 0) > 0 ||
        document.querySelector(".p-dialog") !== null

    if (hasOpenModal) {
        return
    }

    document.querySelectorAll(".p-dialog-mask").forEach((mask) => {
        mask.remove()
    })

    document.querySelectorAll("[inert]").forEach((el) => {
        el.removeAttribute("inert")
    })

    document.body.classList.remove("p-overflow-hidden")
    document.body.style.removeProperty("--scrollbar-width")
}
