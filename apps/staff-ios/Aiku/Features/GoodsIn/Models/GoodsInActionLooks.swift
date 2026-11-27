import Foundation

enum GoodsInActionKey {
    static let receive = "receive"
    static let start = "start"
    static let putBack = "put_back"
    static let place = "place"
    static let check = "check"
    static let setAsReturned = "set_as_returned"
    static let unreceive = "unreceive"
    static let undo = "undo"
    static let checklist = "checklist"
    static let addItem = "add_item"

    static let mainStep = AikuButtonLook(.primary, icon: .next)
    static let stepBack = AikuButtonLook(.caution, icon: .undo)
    static let neutral = AikuButtonLook(.tertiary)

    static func look(for key: String) -> AikuButtonLook {
        switch key {
        case Self.receive, Self.start, Self.putBack: Self.mainStep
        case Self.place: AikuButtonLook(.primary, icon: .pin)
        case Self.check: AikuButtonLook(.secondary, icon: .checkDouble)
        case Self.setAsReturned: AikuButtonLook(.finish, icon: .check)
        case Self.unreceive, Self.undo: Self.stepBack
        default: Self.neutral
        }
    }
}
