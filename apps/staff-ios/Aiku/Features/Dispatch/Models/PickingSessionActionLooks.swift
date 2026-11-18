import Foundation

extension PickingSessionActionKey {
    static let neutral = AikuButtonLook(.tertiary)

    static func look(for key: String) -> AikuButtonLook {
        switch key {
        case Self.startPicking: AikuButtonLook(.primary, icon: .next)
        case Self.backToPicking: AikuButtonLook(.caution, icon: .undo)
        case Self.scanPick, Self.scanPack: AikuButtonLook(.primary, icon: .scan)
        case Self.addDeliveryNotes: AikuButtonLook(.secondary, icon: .plus)
        case Self.removeDeliveryNotes: AikuButtonLook(.danger, icon: .trash)
        default: Self.neutral
        }
    }
}
