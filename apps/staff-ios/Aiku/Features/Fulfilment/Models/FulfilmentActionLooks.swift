import Foundation

enum FulfilmentInboundActionLooks {
    static let neutral = AikuButtonLook(.tertiary)

    static let looks: [String: AikuButtonLook] = [
        "receive": AikuButtonLook(.primary, icon: .next),
        "start_booking": AikuButtonLook(.primary, icon: .pallet),
        "book_in": AikuButtonLook(.primary, icon: .pin),
        "finish_booking": AikuButtonLook(.finish, icon: .check),
        "undo_not_received": AikuButtonLook(.caution, icon: .undo),
        "not_received": AikuButtonLook(.danger, icon: .xCircle),
        "damaged": AikuButtonLook(.danger, icon: .warning),
        "lost": AikuButtonLook(.danger, icon: .warning),
        "move": Self.neutral,
        "audit": Self.neutral,
    ]

    static func look(for key: String) -> AikuButtonLook {
        looks[key] ?? neutral
    }
}

enum PalletReturnActionLooks {
    static let neutral = AikuButtonLook(.tertiary)

    static let looks: [String: AikuButtonLook] = [
        "start_picking": AikuButtonLook(.primary, icon: .next),
        "pick": AikuButtonLook(.primary, icon: .next),
        "add_shipment": AikuButtonLook(.primary, icon: .deliveryNote),
        "finish_picking": AikuButtonLook(.finish, icon: .check),
        "dispatch": AikuButtonLook(.finish, icon: .check),
        "print_inserts": AikuButtonLook(.secondary, icon: .printer),
        "undo": AikuButtonLook(.caution, icon: .undo),
        "not_picked": AikuButtonLook(.danger, icon: .xCircle),
    ]

    static func look(for key: String) -> AikuButtonLook {
        looks[key] ?? neutral
    }
}

enum StoredItemAuditActionLooks {
    static let neutral = AikuButtonLook(.tertiary)

    static let looks: [String: AikuButtonLook] = [
        "count": AikuButtonLook(.primary, icon: .check),
        "complete": AikuButtonLook(.finish, icon: .check),
    ]

    static func look(for key: String) -> AikuButtonLook {
        looks[key] ?? neutral
    }
}
