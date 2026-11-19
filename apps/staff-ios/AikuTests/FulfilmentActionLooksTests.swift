import Foundation
import Testing
@testable import Aiku

struct FulfilmentActionLooksTests {
    @Test func inboundActionsTakeTheirLookFromTheirMeaning() {
        let expected: [String: AikuButtonLook] = [
            "receive": AikuButtonLook(.primary, icon: .next),
            "start_booking": AikuButtonLook(.primary, icon: .pallet),
            "book_in": AikuButtonLook(.primary, icon: .pin),
            "finish_booking": AikuButtonLook(.finish, icon: .check),
            "undo_not_received": AikuButtonLook(.caution, icon: .undo),
            "not_received": AikuButtonLook(.danger, icon: .xCircle),
            "damaged": AikuButtonLook(.danger, icon: .warning),
            "lost": AikuButtonLook(.danger, icon: .warning),
            "move": AikuButtonLook(.tertiary),
            "audit": AikuButtonLook(.tertiary),
        ]
        for (key, look) in expected {
            #expect(FulfilmentInboundActionLooks.look(for: key) == look, "\(key)")
        }
        #expect(FulfilmentInboundActionLooks.look(for: "something_new") == AikuButtonLook(.tertiary))
    }

    @Test func notReceivedConfirmsWithTheFilledRed() {
        #expect(FulfilmentInboundActionLooks.look(for: "not_received").forConfirm == AikuButtonLook(.dangerStrong, icon: .xCircle))
    }

    @Test func palletReturnActionsTakeTheirLookFromTheirMeaning() {
        let expected: [String: AikuButtonLook] = [
            "start_picking": AikuButtonLook(.primary, icon: .next),
            "pick": AikuButtonLook(.primary, icon: .next),
            "add_shipment": AikuButtonLook(.primary, icon: .deliveryNote),
            "finish_picking": AikuButtonLook(.finish, icon: .check),
            "dispatch": AikuButtonLook(.finish, icon: .check),
            "print_inserts": AikuButtonLook(.secondary, icon: .printer),
            "undo": AikuButtonLook(.caution, icon: .undo),
            "not_picked": AikuButtonLook(.danger, icon: .xCircle),
        ]
        for (key, look) in expected {
            #expect(PalletReturnActionLooks.look(for: key) == look, "\(key)")
        }
        #expect(PalletReturnActionLooks.look(for: "something_new") == AikuButtonLook(.tertiary))
    }

    @Test func notPickedConfirmsWithTheFilledRedAndAddShipmentStepsAsideForDispatch() {
        #expect(PalletReturnActionLooks.look(for: "not_picked").forConfirm == AikuButtonLook(.dangerStrong, icon: .xCircle))
        #expect(PalletReturnActionLooks.look(for: "add_shipment").inFooter(isFirst: false) == AikuButtonLook(.secondary, icon: .deliveryNote))
        #expect(PalletReturnActionLooks.look(for: "add_shipment").inFooter(isFirst: true) == AikuButtonLook(.primary, icon: .deliveryNote))
    }

    @Test func storedItemAuditActionsTakeTheirLookFromTheirMeaning() {
        #expect(StoredItemAuditActionLooks.look(for: "count") == AikuButtonLook(.primary, icon: .check))
        #expect(StoredItemAuditActionLooks.look(for: "complete") == AikuButtonLook(.finish, icon: .check))
        #expect(StoredItemAuditActionLooks.look(for: "add_item") == AikuButtonLook(.tertiary))
    }
}
