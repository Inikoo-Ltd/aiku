import Foundation
import Testing
@testable import Aiku

struct ThemeTests {
    @Test func mixesLikeCssColorMix() {
        let mixed = RGBA(0x4F46E5).mixed(0.12, with: .white)

        #expect(mixed.hex == "#EAE9FC")
    }

    @Test func lightPaletteUsesThemeSlots() {
        let palette = Palette(theme: .deepSea, dark: false)

        #expect(palette.nav.hex == "#4F46E5")
        #expect(palette.navAct.hex == "#362CDB")
        #expect(palette.navAccent.hex == "#FDE047")
        #expect(palette.primary.hex == "#4F46E5")
        #expect(palette.scan.hex == "#FCD34D")
        #expect(palette.bg.hex == "#F3F4F6")
        #expect(palette.text.hex == "#111827")
    }

    @Test func darkPaletteKeepsHueWithoutPureBlack() {
        let palette = Palette(theme: .deepSea, dark: true)

        #expect(palette.bg.hex == "#1F1F1F")
        #expect(palette.surface.hex == "#292A2D")
        #expect(palette.nav.hex == RGBA(0x4F46E5).mixed(0.30, with: RGBA(0x1F1F1F)).hex)
        #expect(palette.onNav.hex == "#FFFFFF")
        #expect(palette.link.hex == "#A5B4FC")
        #expect(palette.dangerSoft.alpha == 0.14)
    }

    @Test func darkMonochromeThemesUseLightButtons() {
        for theme in [ThemeID.pureBw, .blackWhite] {
            let palette = Palette(theme: theme, dark: true)

            #expect(palette.primary.hex == "#DADCE0")
            #expect(palette.onPrimary.hex == "#1F1F1F")
            #expect(palette.navAct.hex == "#3C4043")
        }
        #expect(Palette(theme: .pureBw, dark: true).navAccent.hex == "#FFFFFF")
    }

    @Test func everyThemeHasANameAndDistinctId() {
        #expect(ThemeID.allCases.count == 11)
        #expect(Set(ThemeID.allCases.map(\.rawValue)).count == 11)
        #expect(ThemeID.allCases.allSatisfy { !$0.name.isEmpty })
    }

    @MainActor
    @Test func storePersistsThemeAndMode() throws {
        let suite = "theme-tests-\(UUID().uuidString)"
        let defaults = try #require(UserDefaults(suiteName: suite))
        defer { defaults.removePersistentDomain(forName: suite) }

        let store = ThemeStore(defaults: defaults)
        #expect(store.theme == .deepSea)
        #expect(store.mode == .light)

        store.theme = .ukAw
        store.mode = .dark

        let restored = ThemeStore(defaults: defaults)
        #expect(restored.theme == .ukAw)
        #expect(restored.mode == .dark)
        #expect(restored.palette == Palette(theme: .ukAw, dark: true))
    }

    @MainActor
    @Test func systemModeFollowsTheDevice() throws {
        let suite = "theme-tests-\(UUID().uuidString)"
        let defaults = try #require(UserDefaults(suiteName: suite))
        defer { defaults.removePersistentDomain(forName: suite) }

        let store = ThemeStore(defaults: defaults)
        store.mode = .system
        #expect(store.isDark == false)

        store.systemIsDark = true

        #expect(store.isDark)
        #expect(store.palette.bg.hex == "#1F1F1F")
    }
}

struct SessionTests {
    @MainActor
    @Test func staffWithoutWarehouseAccessCannotScan() {
        let session = Session(access: .staff)

        #expect(session.canScan == false)
        #expect(Session(access: .all).canScan)
    }

    @Test func profileInitialsAndLabels() {
        #expect(StaffProfile.sample.initials == "AM")
        #expect(StaffProfile.sample.firstName == "Alex")
    }

    @MainActor
    @Test func fulfilmentSectionsNeedFulfilmentAccess() {
        let withFulfilment = AppModule.goodsIn.sections(includingFulfilment: true).map(\.id)
        let without = AppModule.goodsIn.sections(includingFulfilment: false).map(\.id)

        #expect(withFulfilment == [.goodsInBacklog, .stockDeliveries, .fulfilmentDeliveries, .goodsInReturns])
        #expect(without == [.goodsInBacklog, .stockDeliveries, .goodsInReturns])
    }
}
