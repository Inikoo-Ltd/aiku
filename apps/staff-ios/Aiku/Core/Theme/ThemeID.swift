import Foundation

struct ThemeSlots: Equatable, Sendable {
    let bar: RGBA
    let onBar: RGBA
    let barActive: RGBA
    let barAccent: RGBA
    let primary: RGBA
    let onPrimary: RGBA
    let scan: RGBA
    let onScan: RGBA
    let link: RGBA
    let linkDark: RGBA
}

enum ThemeID: String, CaseIterable, Identifiable, Sendable {
    case deepSea = "deep-sea"
    case rosewater
    case amoled
    case blackWhite = "black-white"
    case ukAw = "uk-aw"
    case esPurple = "es-purple"
    case esBlue = "es-blue"
    case esGold = "es-gold"
    case esRose = "es-rose"
    case esOlive = "es-olive"
    case pureBw = "pure-bw"

    var id: String { rawValue }

    var name: String {
        switch self {
        case .deepSea: "Deep Sea Serenity"
        case .rosewater: "Rosewater Blush"
        case .amoled: "Amoled"
        case .blackWhite: "Black and White"
        case .ukAw: "UK AW Distributions"
        case .esPurple: "Spain · purple"
        case .esBlue: "Spain · blue"
        case .esGold: "Spain · gold"
        case .esRose: "Spain · rose"
        case .esOlive: "Spain · olive"
        case .pureBw: "Pure Black and White"
        }
    }

    var slots: ThemeSlots {
        switch self {
        case .deepSea: Self.make(0x4F46E5, 0xF3F4F6, 0x362CDB, 0xFDE047, 0x4F46E5, 0xF3F4F6, 0xFCD34D, 0x374151, 0x4F46E5, 0xA5B4FC)
        case .rosewater: Self.make(0xF43F5E, 0xF5F5F5, 0xE3B7C8, 0x000000, 0xD6C7E2, 0x000000, 0xFCA5A5, 0x374151, 0xE11D48, 0xFDA4AF)
        case .amoled: Self.make(0x000000, 0xF2F2F2, 0x424242, 0xFDE047, 0x8E44AD, 0xFFFFFF, 0xFCD34D, 0x374151, 0x8E44AD, 0xD2A8E8)
        case .blackWhite: Self.make(0x0F1626, 0xFFFFFF, 0x1E293B, 0xF1F5F9, 0x0F172A, 0xE2E8F0, 0xCCCCCC, 0x424242, 0x0F172A, 0xE2E8F0)
        case .ukAw: Self.make(0x957A65, 0xFFFFFF, 0x4B5058, 0xFFFFFF, 0xE87928, 0xFFFFFF, 0xCCCCCC, 0x957A65, 0xB85A14, 0xF5A867)
        case .esPurple: Self.make(0xA57FBC, 0xFFFFFF, 0x4B5058, 0xFFFFFF, 0xA57FBC, 0xFFFFFF, 0xCCCCCC, 0x957A65, 0x7E5A96, 0xCDB3DD)
        case .esBlue: Self.make(0x8DBAD8, 0xFFFFFF, 0x4B5058, 0xFFFFFF, 0x8DBAD8, 0xFFFFFF, 0xCCCCCC, 0x957A65, 0x3F7DA6, 0xB5D5EA)
        case .esGold: Self.make(0xC1A027, 0xFFFFFF, 0x4B5058, 0xFFFFFF, 0xC1A027, 0xFFFFFF, 0xCCCCCC, 0x957A65, 0x856C12, 0xE3C85E)
        case .esRose: Self.make(0xC48497, 0xFFFFFF, 0x4B5058, 0xFFFFFF, 0xC48497, 0xFFFFFF, 0xCCCCCC, 0x957A65, 0x9E5A6E, 0xE3B3C1)
        case .esOlive: Self.make(0x909B44, 0xFFFFFF, 0x4B5058, 0xFFFFFF, 0x909B44, 0xFFFFFF, 0xCCCCCC, 0x957A65, 0x66702A, 0xC4CE7A)
        case .pureBw: Self.make(0x000000, 0xFFFFFF, 0x000000, 0xFFFFFF, 0x000000, 0xFFFFFF, 0x000000, 0xFFFFFF, 0x000000, 0xE5E7EB)
        }
    }

    private static func make(
        _ bar: UInt32, _ onBar: UInt32, _ barActive: UInt32, _ barAccent: UInt32,
        _ primary: UInt32, _ onPrimary: UInt32, _ scan: UInt32, _ onScan: UInt32,
        _ link: UInt32, _ linkDark: UInt32
    ) -> ThemeSlots {
        ThemeSlots(
            bar: RGBA(bar), onBar: RGBA(onBar), barActive: RGBA(barActive), barAccent: RGBA(barAccent),
            primary: RGBA(primary), onPrimary: RGBA(onPrimary), scan: RGBA(scan), onScan: RGBA(onScan),
            link: RGBA(link), linkDark: RGBA(linkDark)
        )
    }
}

enum AppearanceMode: String, CaseIterable, Identifiable, Sendable {
    case light
    case dark
    case system

    var id: String { rawValue }

    var title: String {
        switch self {
        case .light: "Light"
        case .dark: "Dark"
        case .system: "System"
        }
    }
}
