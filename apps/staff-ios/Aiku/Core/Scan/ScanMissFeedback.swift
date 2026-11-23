import SwiftUI

private struct ScanMissFeedback: ViewModifier {
    let misses: Int
    @AppStorage(ScanSettingsKey.vibrate) private var vibrates = true

    func body(content: Content) -> some View {
        content.sensoryFeedback(.error, trigger: misses) { _, _ in vibrates }
    }
}

extension View {
    func scanMissFeedback(_ misses: Int) -> some View {
        modifier(ScanMissFeedback(misses: misses))
    }
}
