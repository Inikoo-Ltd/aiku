import Foundation
import Observation

@MainActor
@Observable
final class ThemeStore {
    static let shared = ThemeStore()

    private enum Key {
        static let theme = "appearance.theme"
        static let mode = "appearance.mode"
        static let colourPerOrganisation = "appearance.colourPerOrganisation"
    }

    @ObservationIgnored private let defaults: UserDefaults

    var theme: ThemeID {
        didSet {
            defaults.set(theme.rawValue, forKey: Key.theme)
            rebuild()
        }
    }

    var mode: AppearanceMode {
        didSet {
            defaults.set(mode.rawValue, forKey: Key.mode)
            rebuild()
        }
    }

    var systemIsDark = false {
        didSet { rebuild() }
    }

    var colourPerOrganisation: Bool {
        didSet { defaults.set(colourPerOrganisation, forKey: Key.colourPerOrganisation) }
    }

    private(set) var palette: Palette

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        let theme = defaults.string(forKey: Key.theme).flatMap(ThemeID.init(rawValue:)) ?? .deepSea
        let mode = defaults.string(forKey: Key.mode).flatMap(AppearanceMode.init(rawValue:)) ?? .light
        self.theme = theme
        self.mode = mode
        colourPerOrganisation = defaults.bool(forKey: Key.colourPerOrganisation)
        palette = Palette(theme: theme, dark: mode == .dark)
    }

    var isDark: Bool {
        switch mode {
        case .light: false
        case .dark: true
        case .system: systemIsDark
        }
    }

    private func rebuild() {
        let next = Palette(theme: theme, dark: isDark)
        if next != palette {
            palette = next
        }
    }
}
