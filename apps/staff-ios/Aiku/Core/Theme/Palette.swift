import Foundation

struct Palette: Equatable, Sendable {
    var nav: RGBA
    var onNav: RGBA
    var onNav2: RGBA
    var navAct: RGBA
    var navAccent: RGBA
    var navRing: RGBA
    var primary: RGBA
    var onPrimary: RGBA
    var primaryBd: RGBA
    var scan: RGBA
    var onScan: RGBA
    var link: RGBA
    var ink: RGBA
    var soft: RGBA
    var softBd: RGBA
    var bg: RGBA
    var surface: RGBA
    var surface2: RGBA
    var border: RGBA
    var borderStrong: RGBA
    var divider: RGBA
    var text: RGBA
    var text2: RGBA
    var text3: RGBA
    var text4: RGBA
    var faint: RGBA
    var danger: RGBA
    var dangerSoft: RGBA
    var dangerBd: RGBA
    var dangerInk: RGBA
    var dangerDot: RGBA
    var success: RGBA
    var successSoft: RGBA
    var warn: RGBA
    var warnSoft: RGBA
    var warnBd: RGBA
    var warnInk: RGBA
    var orange: RGBA
    var orangeSoft: RGBA
    var lime: RGBA
    var limeSoft: RGBA
    var shadow: RGBA

    init(theme: ThemeID, dark: Bool) {
        let slots = theme.slots
        if dark {
            let page = RGBA(0x1F1F1F)
            let card = RGBA(0x292A2D)
            let grey = RGBA(0x5F6368)
            let pale = RGBA(0xE8EAED)
            nav = slots.bar.mixed(0.30, with: page)
            onNav = .white
            onNav2 = RGBA.white.opacity(0.74)
            navAct = slots.barActive.mixed(0.38, with: RGBA(0x2A2B2E))
            navAccent = slots.barAccent.mixed(0.45, with: pale)
            navRing = slots.barActive.mixed(0.45, with: grey)
            primary = slots.primary.mixed(0.72, with: page)
            onPrimary = slots.onPrimary
            primaryBd = slots.primary.mixed(0.55, with: grey)
            scan = slots.scan.mixed(0.24, with: RGBA(0x303134))
            onScan = slots.scan.mixed(0.60, with: pale)
            link = slots.linkDark
            ink = slots.linkDark
            soft = slots.linkDark.mixed(0.14, with: card)
            softBd = slots.linkDark.mixed(0.38, with: card)
            bg = page
            surface = card
            surface2 = RGBA(0x303134)
            border = RGBA(0x3C4043)
            borderStrong = grey
            divider = RGBA(0x303134)
            text = pale
            text2 = RGBA(0xDADCE0)
            text3 = RGBA(0xBDC1C6)
            text4 = RGBA(0x9AA0A6)
            faint = RGBA(0x80868B)
            danger = RGBA(0xF28B82)
            dangerSoft = RGBA(0xF28B82, alpha: 0.14)
            dangerBd = RGBA(0xF28B82, alpha: 0.4)
            dangerInk = RGBA(0xF6AEA9)
            dangerDot = RGBA(0xF28B82)
            success = RGBA(0x81C995)
            successSoft = RGBA(0x81C995, alpha: 0.14)
            warn = RGBA(0xFDD663)
            warnSoft = RGBA(0xFDD663, alpha: 0.14)
            warnBd = RGBA(0xFDD663, alpha: 0.35)
            warnInk = RGBA(0xFDE293)
            orange = RGBA(0xFCAD70)
            orangeSoft = RGBA(0xFCAD70, alpha: 0.14)
            lime = RGBA(0xC5E1A5)
            limeSoft = RGBA(0xC5E1A5, alpha: 0.14)
            shadow = RGBA(0x000000, alpha: 0.5)

            if theme == .pureBw || theme == .blackWhite {
                primary = RGBA(0xDADCE0)
                onPrimary = page
                primaryBd = pale
                navAct = RGBA(0x3C4043)
            }
            if theme == .pureBw {
                scan = RGBA(0x3C4043)
                onScan = pale
                navAccent = .white
            }
        } else {
            nav = slots.bar
            onNav = slots.onBar
            onNav2 = slots.onBar.mixed(0.82, with: slots.bar)
            navAct = slots.barActive
            navAccent = slots.barAccent
            navRing = slots.barActive.mixed(0.80, with: .white)
            primary = slots.primary
            onPrimary = slots.onPrimary
            primaryBd = slots.primary.mixed(0.80, with: .black)
            scan = slots.scan
            onScan = slots.onScan
            link = slots.link
            ink = slots.link
            soft = slots.primary.mixed(0.12, with: .white)
            softBd = slots.primary.mixed(0.35, with: .white)
            bg = RGBA(0xF3F4F6)
            surface = .white
            surface2 = RGBA(0xF9FAFB)
            border = RGBA(0xE5E7EB)
            borderStrong = RGBA(0xD1D5DB)
            divider = RGBA(0xF3F4F6)
            text = RGBA(0x111827)
            text2 = RGBA(0x374151)
            text3 = RGBA(0x4B5563)
            text4 = RGBA(0x6B7280)
            faint = RGBA(0x9CA3AF)
            danger = RGBA(0xDC2626)
            dangerSoft = RGBA(0xFEF2F2)
            dangerBd = RGBA(0xFECACA)
            dangerInk = RGBA(0x991B1B)
            dangerDot = RGBA(0xEF4444)
            success = RGBA(0x15803D)
            successSoft = RGBA(0xF0FDF4)
            warn = RGBA(0xB45309)
            warnSoft = RGBA(0xFFFBEB)
            warnBd = RGBA(0xFDE68A)
            warnInk = RGBA(0x92400E)
            orange = RGBA(0xC2410C)
            orangeSoft = RGBA(0xFFF7ED)
            lime = RGBA(0x4D7C0F)
            limeSoft = RGBA(0xF7FEE7)
            shadow = RGBA(0x111827, alpha: 0.08)
        }
    }
}
