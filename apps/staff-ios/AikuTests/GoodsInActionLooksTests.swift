import Foundation
import Testing
@testable import Aiku

struct GoodsInActionLooksTests {
    @Test func goodsInActionsTakeTheirLookFromTheirMeaning() {
        let expected: [String: AikuButtonLook] = [
            "receive": AikuButtonLook(.primary, icon: .next),
            "start": AikuButtonLook(.primary, icon: .next),
            "put_back": AikuButtonLook(.primary, icon: .next),
            "place": AikuButtonLook(.primary, icon: .pin),
            "check": AikuButtonLook(.secondary, icon: .checkDouble),
            "set_as_returned": AikuButtonLook(.finish, icon: .check),
            "unreceive": AikuButtonLook(.caution, icon: .undo),
            "undo": AikuButtonLook(.caution, icon: .undo),
        ]
        for (key, look) in expected {
            #expect(GoodsInActionKey.look(for: key) == look, "\(key)")
        }
    }

    @Test func anUnknownGoodsInActionIsNeutral() {
        #expect(GoodsInActionKey.look(for: "something_new") == AikuButtonLook(.tertiary))
        #expect(GoodsInActionKey.look(for: "") == AikuButtonLook(.tertiary))
    }

    @Test func goodsInConfirmsKeepTheirMeaning() {
        #expect(GoodsInActionKey.look(for: GoodsInActionKey.check).forConfirm == AikuButtonLook(.primary, icon: .checkDouble))
        #expect(GoodsInActionKey.look(for: GoodsInActionKey.unreceive).forConfirm == AikuButtonLook(.caution, icon: .undo))
        #expect(GoodsInActionKey.look(for: GoodsInActionKey.unreceive).kind.tileTone == .warn)
    }

    @Test func pickingSessionActionsTakeTheirLookFromTheirMeaning() {
        let expected: [String: AikuButtonLook] = [
            "start_picking": AikuButtonLook(.primary, icon: .next),
            "back_to_picking": AikuButtonLook(.caution, icon: .undo),
            "scan_pick": AikuButtonLook(.primary, icon: .scan),
            "scan_pack": AikuButtonLook(.primary, icon: .scan),
            "add_delivery_notes": AikuButtonLook(.secondary, icon: .plus),
            "remove_delivery_notes": AikuButtonLook(.danger, icon: .trash),
        ]
        for (key, look) in expected {
            #expect(PickingSessionActionKey.look(for: key) == look, "\(key)")
        }
        #expect(PickingSessionActionKey.look(for: "something_new") == AikuButtonLook(.tertiary))
    }

    @Test func removingDeliveryNotesConfirmsWithTheFilledRed() {
        #expect(PickingSessionActionKey.look(for: PickingSessionActionKey.removeDeliveryNotes).forConfirm == AikuButtonLook(.dangerStrong, icon: .trash))
        #expect(PickingSessionActionKey.look(for: PickingSessionActionKey.addDeliveryNotes).forConfirm == AikuButtonLook(.primary, icon: .plus))
    }

    @Test func onlyTheFirstSessionFooterActionIsFilled() {
        #expect(PickingSessionActionKey.look(for: PickingSessionActionKey.scanPick).inFooter(isFirst: true) == AikuButtonLook(.primary, icon: .scan))
        #expect(PickingSessionActionKey.look(for: PickingSessionActionKey.startPicking).inFooter(isFirst: false) == AikuButtonLook(.secondary, icon: .next))
        #expect(PickingSessionActionKey.look(for: PickingSessionActionKey.backToPicking).inFooter(isFirst: false) == AikuButtonLook(.caution, icon: .undo))
    }
}
