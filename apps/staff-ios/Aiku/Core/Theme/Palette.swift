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
    var softInk: RGBA
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
    var dangerButtonBd: RGBA
    var dangerStrong: RGBA
    var onDangerStrong: RGBA
    var success: RGBA
    var successSoft: RGBA
    var finish: RGBA
    var onFinish: RGBA
    var warn: RGBA
    var warnSoft: RGBA
    var warnBd: RGBA
    var warnInk: RGBA
    var cautionInk: RGBA
    var cautionSoft: RGBA
    var cautionBd: RGBA
    var orange: RGBA
    var orangeSoft: RGBA
    var lime: RGBA
    var limeSoft: RGBA
    var shadow: RGBA

    static let readableContrast = 4.5
    private static let darkInk = RGBA(0x111827)
    private static let darkOnSignal = RGBA(0x1F1F1F)
    private static let darkSignalWeight = 0.55
    private static let darkSoftAlpha = Double(0x24) / 255
    private static let darkCautionBorderAlpha = Double(0x59) / 255
    private static let darkDangerBorderAlpha = Double(0x66) / 255

    static func readable(on fill: RGBA, preferring preferred: RGBA) -> RGBA {
        let candidates = [preferred, darkInk, .black]
        if let readable = candidates.first(where: { $0.contrast(with: fill) >= readableContrast }) {
            return readable
        }
        return (candidates + [.white]).max { $0.contrast(with: fill) < $1.contrast(with: fill) } ?? preferred
    }

    private struct Signals {
        var finish: RGBA
        var successSoft: RGBA
        var cautionInk: RGBA
        var cautionSoft: RGBA
        var cautionBd: RGBA
        var warn: RGBA
        var warnBd: RGBA
        var danger: RGBA
        var dangerSoft: RGBA
        var dangerBd: RGBA
        var dangerButtonBd: RGBA
        var dangerInk: RGBA
        var dangerDot: RGBA
        var dangerStrong: RGBA
    }

    private static func lightSignals(_ theme: ThemeID) -> Signals {
        let shades = theme.shades
        var signals = Signals(
            finish: shades.finish,
            successSoft: shades.finish.mixed(0.08, with: .white),
            cautionInk: shades.cautionInk,
            cautionSoft: shades.cautionSoft,
            cautionBd: shades.cautionBorder,
            warn: shades.cautionInk.mixed(0.75, with: shades.cautionBorder),
            warnBd: shades.cautionBorder,
            danger: shades.danger,
            dangerSoft: shades.danger.mixed(0.06, with: .white),
            dangerBd: shades.dangerBorder,
            dangerButtonBd: shades.dangerBorder,
            dangerInk: shades.danger.mixed(0.70, with: .black),
            dangerDot: shades.danger,
            dangerStrong: shades.dangerStrong
        )
        if theme == .deepSea {
            signals.successSoft = RGBA(0xF0FDF4)
            signals.warn = RGBA(0xB45309)
            signals.warnBd = RGBA(0xFDE68A)
            signals.dangerSoft = RGBA(0xFEF2F2)
            signals.dangerBd = RGBA(0xFECACA)
            signals.dangerInk = RGBA(0x991B1B)
            signals.dangerDot = RGBA(0xEF4444)
        }
        return signals
    }

    private static func darkSignals(_ theme: ThemeID) -> Signals {
        if theme == .deepSea {
            return Signals(
                finish: RGBA(0x81C995),
                successSoft: RGBA(0x81C995, alpha: darkSoftAlpha),
                cautionInk: RGBA(0xFDE293),
                cautionSoft: RGBA(0xFDD663, alpha: darkSoftAlpha),
                cautionBd: RGBA(0x7A6A33),
                warn: RGBA(0xFDD663),
                warnBd: RGBA(0xFDD663, alpha: darkCautionBorderAlpha),
                danger: RGBA(0xF28B82),
                dangerSoft: RGBA(0xF28B82, alpha: darkSoftAlpha),
                dangerBd: RGBA(0xF28B82, alpha: darkDangerBorderAlpha),
                dangerButtonBd: RGBA(0xF28B82, alpha: darkDangerBorderAlpha),
                dangerInk: RGBA(0xF6AEA9),
                dangerDot: RGBA(0xF28B82),
                dangerStrong: RGBA(0xF28B82)
            )
        }
        let shades = theme.shades
        let lightened = { (color: RGBA) in color.mixed(darkSignalWeight, with: .white) }
        let finish = lightened(shades.finish)
        let cautionInk = shades.cautionInkDark ?? lightened(shades.cautionInk)
        let danger = lightened(shades.danger)
        let lightWarn = shades.cautionInk.mixed(0.75, with: shades.cautionBorder)
        return Signals(
            finish: finish,
            successSoft: finish.opacity(darkSoftAlpha),
            cautionInk: cautionInk,
            cautionSoft: cautionInk.opacity(darkSoftAlpha),
            cautionBd: cautionInk.opacity(darkCautionBorderAlpha),
            warn: lightWarn.mixed(darkSignalWeight, with: .white),
            warnBd: cautionInk.opacity(darkCautionBorderAlpha),
            danger: danger,
            dangerSoft: danger.opacity(darkSoftAlpha),
            dangerBd: danger.opacity(darkDangerBorderAlpha),
            dangerButtonBd: danger.opacity(darkDangerBorderAlpha),
            dangerInk: danger.mixed(0.70, with: .white),
            dangerDot: danger,
            dangerStrong: lightened(shades.dangerStrong)
        )
    }

    init(theme: ThemeID, dark: Bool) {
        let slots = theme.slots
        let signals = dark ? Self.darkSignals(theme) : Self.lightSignals(theme)
        danger = signals.danger
        dangerSoft = signals.dangerSoft
        dangerBd = signals.dangerBd
        dangerInk = signals.dangerInk
        dangerDot = signals.dangerDot
        dangerButtonBd = signals.dangerButtonBd
        dangerStrong = signals.dangerStrong
        success = signals.finish
        successSoft = signals.successSoft
        finish = signals.finish
        warn = signals.warn
        warnSoft = signals.cautionSoft
        warnBd = signals.warnBd
        warnInk = signals.cautionInk
        cautionInk = signals.cautionInk
        cautionSoft = signals.cautionSoft
        cautionBd = signals.cautionBd
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
            onPrimary = Self.readable(on: slots.primary.mixed(0.72, with: page), preferring: slots.onPrimary)
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
            onFinish = Self.darkOnSignal
            onDangerStrong = Self.darkOnSignal
            softInk = theme.shades.softInkDark ?? slots.linkDark
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
            onPrimary = Self.readable(on: slots.primary, preferring: slots.onPrimary)
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
            onFinish = .white
            onDangerStrong = .white
            softInk = theme.shades.softInk ?? slots.link
            orange = RGBA(0xC2410C)
            orangeSoft = RGBA(0xFFF7ED)
            lime = RGBA(0x4D7C0F)
            limeSoft = RGBA(0xF7FEE7)
            shadow = RGBA(0x111827, alpha: 0.08)
        }
    }
}
