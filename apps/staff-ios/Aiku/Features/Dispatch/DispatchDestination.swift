import SwiftUI

struct DispatchDestination: View {
    let route: DispatchRoute

    var body: some View {
        switch route {
        case .pickingSessions:
            DispatchPickingSessionsView()
        case .pickingSession(let reference):
            DispatchPickingSessionView(reference: reference)
        case .shippers:
            DispatchShippersView()
        case .trolleys:
            DispatchTrolleysView()
        case .pickedBays:
            DispatchPickedBaysView()
        case .pickingSessionPick(let reference):
            DispatchPickView(reference: reference)
        case .pickingSessionPack(let reference):
            DispatchPackView(reference: reference)
        }
    }
}
