import Foundation

extension GoodsOutActionKey {
    static let mainStep = AikuButtonLook(.primary, icon: .next)
    static let finishStep = AikuButtonLook(.finish, icon: .check)
    static let stepBack = AikuButtonLook(.caution, icon: .undo)
    static let neutral = AikuButtonLook(.tertiary)

    var look: AikuButtonLook {
        switch self {
        case .startPicking, .resumePicking, .putBackIncompleteSets: Self.mainStep
        case .startPacking: AikuButtonLook(.primary, icon: .sko)
        case .addShipment, .getExternalShipment: AikuButtonLook(.primary, icon: .deliveryNote)
        case .setAsPicked, .setAsPacked, .autoFinishWaiting, .finaliseAndDispatch, .finalise, .dispatch: Self.finishStep
        case .takeOver: AikuButtonLook(.caution, icon: .unlock)
        case .backToPicking, .undoSetAsPicked, .undoPacking, .unpack, .removePicker: Self.stepBack
        case .cancel: AikuButtonLook(.danger, icon: .xCircle)
        case .assignPicker, .changePicker, .changePacker, .changeTrolley, .changePickedBay, .editDeliveryAddress: Self.neutral
        }
    }

    static func look(for key: String) -> AikuButtonLook {
        GoodsOutActionKey(rawValue: key)?.look ?? neutral
    }
}
