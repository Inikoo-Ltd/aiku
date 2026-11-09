import SwiftUI

struct SearchBar: View {
    @Binding var text: String
    let placeholder: String
    var scanLabel = "Scan barcode"
    var onScan: (() -> Void)?

    var body: some View {
        HStack(spacing: 8) {
            HStack(spacing: 10) {
                Image(systemName: AppIcon.search)
                    .foregroundStyle(AikuColor.textMuted)
                TextField(placeholder, text: $text)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .foregroundStyle(AikuColor.textPrimary)
                    .accessibilityIdentifier("search.field")
                if !text.isEmpty {
                    Button {
                        text = ""
                    } label: {
                        Image(systemName: "xmark.circle.fill").foregroundStyle(AikuColor.textFaint)
                    }
                    .accessibilityLabel("Clear search")
                }
            }
            .padding(.horizontal, 14)
            .frame(height: 46)
            .background(.white, in: RoundedRectangle(cornerRadius: 8))

            if let onScan {
                Button(action: onScan) {
                    Image(systemName: AppIcon.scan)
                        .font(.system(size: 20, weight: .semibold))
                        .foregroundStyle(AikuColor.navy)
                        .frame(width: 46, height: 46)
                        .background(AikuColor.yellow, in: RoundedRectangle(cornerRadius: 8))
                }
                .accessibilityLabel(scanLabel)
            }
        }
    }
}
