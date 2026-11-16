import SwiftUI

struct DispatchDestination: View {
    let route: DispatchRoute

    var body: some View {
        switch route {
        case .pickingSessions:
            DispatchPickingSessionsView()
        case .pickingSession(let reference):
            DispatchSessionByReference(reference: reference, title: reference) { DispatchPickingSessionView(sessionID: $0) }
        case .pickingSessionID(let id):
            DispatchPickingSessionView(sessionID: id)
        case .shippers:
            DispatchShippersView()
        case .trolleys:
            DispatchTrolleysView()
        case .pickedBays:
            DispatchPickedBaysView()
        case .pickingSessionPick(let reference):
            DispatchSessionByReference(reference: reference, title: "Pick by location") { DispatchPickView(sessionID: $0) }
        case .pickingSessionPickID(let id):
            DispatchPickView(sessionID: id)
        case .pickingSessionPack(let reference):
            DispatchSessionByReference(reference: reference, title: "Pack by scan") { DispatchPackView(sessionID: $0) }
        case .pickingSessionPackID(let id):
            DispatchPackView(sessionID: id)
        }
    }
}
