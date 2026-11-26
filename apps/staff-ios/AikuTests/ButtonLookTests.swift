import Foundation
import Testing
@testable import Aiku

struct ButtonLookTests {
    @Test func buttonTextKeepsAtLeastFourAndAHalfToOneOnItsFillInEveryTheme() {
        var failures: [String] = []
        for theme in ThemeID.allCases {
            for dark in [false, true] {
                let palette = Palette(theme: theme, dark: dark)
                let pairs: [(String, RGBA, RGBA)] = [
                    ("onPrimary on primary", palette.onPrimary, palette.primary),
                    ("onFinish on finish", palette.onFinish, palette.finish),
                    ("onDangerStrong on dangerStrong", palette.onDangerStrong, palette.dangerStrong),
                    ("danger on surface", palette.danger, palette.surface),
                    ("cautionInk on cautionSoft", palette.cautionInk, palette.cautionSoft),
                    ("softInk on soft", palette.softInk, palette.soft),
                ]
                for (name, foreground, background) in pairs {
                    let fill = background.over(palette.surface)
                    let ratio = foreground.over(fill).contrast(with: fill)
                    if ratio < Palette.readableContrast {
                        failures.append("\(theme.rawValue) \(dark ? "dark" : "light"), \(name): \(String(format: "%.2f", ratio))")
                    }
                }
            }
        }

        #expect(failures.isEmpty, "\(failures.joined(separator: "\n"))")
    }

    @Test func deepSeaKeepsItsStatusColoursInLightAndDark() {
        let light = Palette(theme: .deepSea, dark: false)
        let dark = Palette(theme: .deepSea, dark: true)
        let soft = Double(0x24) / 255

        #expect([light.danger, light.dangerSoft, light.dangerBd, light.dangerInk, light.dangerDot, light.success, light.successSoft, light.warn, light.warnSoft, light.warnBd, light.warnInk]
            == ([0xDC2626, 0xFEF2F2, 0xFECACA, 0x991B1B, 0xEF4444, 0x15803D, 0xF0FDF4, 0xB45309, 0xFFFBEB, 0xFDE68A, 0x92400E] as [UInt32]).map { RGBA($0) })
        #expect([dark.danger, dark.dangerSoft, dark.dangerBd, dark.dangerInk, dark.success, dark.successSoft, dark.warn, dark.warnSoft, dark.warnBd, dark.warnInk]
            == [RGBA(0xF28B82), RGBA(0xF28B82, alpha: soft), RGBA(0xF28B82, alpha: Double(0x66) / 255), RGBA(0xF6AEA9), RGBA(0x81C995), RGBA(0x81C995, alpha: soft), RGBA(0xFDD663), RGBA(0xFDD663, alpha: soft), RGBA(0xFDD663, alpha: Double(0x59) / 255), RGBA(0xFDE293)])
        #expect(light.dangerButtonBd == RGBA(0xF19A9A))
        #expect(light.dangerStrong == RGBA(0xB91C1C))
        #expect(dark.cautionBd == RGBA(0x7A6A33))
    }

    @Test func statusChipsFollowTheButtonShadesOfEachTheme() {
        for theme in ThemeID.allCases {
            for dark in [false, true] {
                let palette = Palette(theme: theme, dark: dark)
                #expect(palette.success == palette.finish, "\(theme.rawValue)")
                #expect(palette.warnInk == palette.cautionInk, "\(theme.rawValue)")
                #expect(palette.warnSoft == palette.cautionSoft, "\(theme.rawValue)")
            }
        }
    }

    @Test func aCautionButtonWithoutItsOwnIconShowsUndoAsOnAndroid() {
        #expect(AikuButtonKind.caution.defaultIcon == .undo)
        #expect(AikuButtonKind.danger.defaultIcon == .xCircle)
        #expect(AikuButtonKind.finish.defaultIcon == .check)
        #expect(AikuButtonKind.primary.defaultIcon == nil)
    }

    @Test func conflictChoicesAreOrderedAndStyledByWhatTheyDo() {
        let buttons = OfflineResolution.footerButtons([.discard, .keepCount, .recount])

        #expect(buttons.map(\.resolution) == [.recount, .keepCount, .discard])
        #expect(buttons.map(\.look) == [AikuButtonLook(.primary), AikuButtonLook(.caution), AikuButtonLook(.danger)])
    }

    @Test func onlyTheFirstMainConflictChoiceIsFilled() {
        let buttons = OfflineResolution.footerButtons([.done, .putBack, .retry])

        #expect(buttons.map(\.resolution) == [.retry, .putBack, .done])
        #expect(buttons.map(\.look) == [AikuButtonLook(.primary, icon: .retry), AikuButtonLook(.secondary), AikuButtonLook(.secondary)])
    }

    @Test func aRedActionConfirmsWithTheFilledRedAndKeepsItsIcon() {
        #expect(AikuButtonLook(.danger, icon: .trash).forConfirm == AikuButtonLook(.dangerStrong, icon: .trash))
        #expect(AikuButtonLook(.danger).forConfirm == AikuButtonLook(.dangerStrong, icon: .xCircle))
    }

    @Test func aHelperConfirmsAsTheMainStepAndTheOtherGroupsKeepTheirLook() {
        #expect(AikuButtonLook(.secondary, icon: .checkDouble).forConfirm == AikuButtonLook(.primary, icon: .checkDouble))
        for kind in [AikuButtonKind.primary, .finish, .caution, .tertiary, .scan] {
            #expect(AikuButtonLook(kind, icon: .undo).forConfirm == AikuButtonLook(kind, icon: .undo))
        }
    }

    @Test func onlyTheFirstFooterActionIsFilled() {
        #expect(AikuButtonLook(.finish).inFooter(isFirst: true).kind == .finish)
        #expect(AikuButtonLook(.primary).inFooter(isFirst: true).kind == .primary)
        #expect(AikuButtonLook(.finish, icon: .check).inFooter(isFirst: false) == AikuButtonLook(.secondary, icon: .check))
        #expect(AikuButtonLook(.primary).inFooter(isFirst: false).kind == .secondary)
        #expect(AikuButtonLook(.caution).inFooter(isFirst: false).kind == .caution)
        #expect(AikuButtonLook(.danger).inFooter(isFirst: false).kind == .danger)
    }

    @Test func iconTilesTakeTheToneOfTheirGroup() {
        #expect(AikuButtonKind.finish.tileTone == .success)
        #expect(AikuButtonKind.caution.tileTone == .warn)
        #expect(AikuButtonKind.danger.tileTone == .danger)
        #expect(AikuButtonKind.dangerStrong.tileTone == .danger)
        #expect(AikuButtonKind.tertiary.tileTone == .info)
    }

    @Test func goodsOutActionsTakeTheirLookFromTheirMeaning() {
        for key in [GoodsOutActionKey.setAsPicked, .setAsPacked, .autoFinishWaiting, .finalise, .dispatch, .finaliseAndDispatch] {
            #expect(key.look == AikuButtonLook(.finish, icon: .check), "\(key.rawValue)")
        }
        for key in [GoodsOutActionKey.backToPicking, .undoSetAsPicked, .undoPacking, .unpack, .removePicker] {
            #expect(key.look == AikuButtonLook(.caution, icon: .undo), "\(key.rawValue)")
        }
        #expect(GoodsOutActionKey.startPicking.look == AikuButtonLook(.primary, icon: .next))
        #expect(GoodsOutActionKey.takeOver.look == AikuButtonLook(.caution, icon: .unlock))
        #expect(GoodsOutActionKey.cancel.look.forConfirm == AikuButtonLook(.dangerStrong, icon: .xCircle))
        #expect(GoodsOutActionKey.look(for: "change_trolley") == AikuButtonLook(.tertiary))
        #expect(GoodsOutActionKey.look(for: "something_new") == AikuButtonLook(.tertiary))
    }
}
