import SwiftUI

private struct LiveReloadKey: Equatable {
    let channel: String?
    let events: Set<String>
}

private struct LiveReloadModifier: ViewModifier {
    let channel: String?
    let events: Set<String>
    let live: LiveUpdates
    let action: @MainActor () async -> Void

    func body(content: Content) -> some View {
        content.task(id: LiveReloadKey(channel: channel, events: events)) {
            guard let channel else { return }
            for await _ in live.settledEvents(on: channel, named: events) {
                await action()
            }
        }
    }
}

extension View {
    func liveReload(
        on channel: String?,
        events: Set<String>,
        live: LiveUpdates = .shared,
        perform action: @escaping @MainActor () async -> Void
    ) -> some View {
        modifier(LiveReloadModifier(channel: channel, events: events, live: live, action: action))
    }
}
