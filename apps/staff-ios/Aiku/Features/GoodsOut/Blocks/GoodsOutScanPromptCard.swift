import SwiftUI

struct GoodsOutScanPromptCard: View {
    let title: String
    let location: String
    let detail: String
    var caption = "Take it from this location"
    var action: (() -> Void)?

    var body: some View {
        if let action {
            Button(action: action) { content }
                .buttonStyle(.plain)
        } else {
            content
        }
    }

    private var content: some View {
        VStack(alignment: .leading, spacing: 6) {
            HStack(spacing: 8) {
                AppIcon.scan.image
                    .font(.system(size: 18))
                    .accessibilityHidden(true)
                Text(title)
                    .font(.aiku(14, .bold))
            }
            Text(caption)
                .font(.aiku(13))
            Text(location)
                .font(.aiku(22, .bold))
                .monospacedDigit()
            Text(detail)
                .font(.aiku(13))
                .fixedSize(horizontal: false, vertical: true)
        }
        .foregroundStyle(AikuColor.onScan)
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
        .contentShape(RoundedRectangle(cornerRadius: 12))
        .accessibilityElement(children: .combine)
    }
}
