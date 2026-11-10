import Foundation

enum ClockingFormat {
    static func time(_ seconds: Int) -> String {
        String(format: "%02d:%02d:%02d", seconds / 3600, (seconds % 3600) / 60, seconds % 60)
    }

    static func shortTime(_ seconds: Int) -> String {
        String(format: "%02d:%02d", seconds / 3600, (seconds % 3600) / 60)
    }

    static func duration(_ seconds: Int) -> String {
        let totalMinutes = max(seconds, 0) / 60
        let hours = totalMinutes / 60
        let minutes = totalMinutes % 60
        return hours > 0 ? String(format: "%dh %02dm", hours, minutes) : "\(minutes)m"
    }

    static func seconds(_ hours: Int, _ minutes: Int, _ seconds: Int = 0) -> Int {
        hours * 3600 + minutes * 60 + seconds
    }

    static func parseTime(_ text: String) -> Int? {
        let parts = text.trimmingCharacters(in: .whitespaces).split(separator: ":")
        guard parts.count == 2,
              let hours = Int(parts[0]), let minutes = Int(parts[1]),
              (0...23).contains(hours), (0...59).contains(minutes), parts[1].count == 2
        else { return nil }
        return seconds(hours, minutes)
    }

    static func secondsSinceMidnight(_ date: Date = .now) -> Int {
        let components = Calendar.current.dateComponents([.hour, .minute, .second], from: date)
        return seconds(components.hour ?? 0, components.minute ?? 0, components.second ?? 0)
    }
}
