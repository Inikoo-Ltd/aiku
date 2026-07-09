#!/usr/bin/env python3

"""
Analyze lines of code per author in app/Actions directory
Groups results by subfolder (depth 1 only)
Usage: python3 analyze_actions_by_folder.py [output_file]
Example: python3 analyze_actions_by_folder.py actions_report.txt
"""

import subprocess
import sys
from collections import defaultdict
from pathlib import Path
from datetime import datetime

def run_command(cmd, cwd=None):
    """Run shell command and return output"""
    try:
        result = subprocess.run(
            cmd,
            cwd=cwd,
            capture_output=True,
            text=True,
            shell=True
        )
        return result.stdout.strip()
    except Exception as e:
        print(f"Error running command: {e}", file=sys.stderr)
        return ""

def main():
    output_file = sys.argv[1] if len(sys.argv) > 1 else "actions_by_folder_report.txt"
    repo_path = Path.cwd()
    actions_path = repo_path / "app" / "Actions"

    # Check if app/Actions exists
    if not actions_path.exists():
        print("Error: app/Actions directory not found", file=sys.stderr)
        sys.exit(1)

    print("Starting analysis of app/Actions directory...")
    print("Grouping by subfolder (depth 1 only)")
    print()

    # Get all PHP files in app/Actions
    files_output = run_command(
        'git ls-files "app/Actions/**/*.php"',
        cwd=str(repo_path)
    )
    files = [f for f in files_output.split('\n') if f]
    total_files = len(files)

    if total_files == 0:
        print("Error: No PHP files found in app/Actions", file=sys.stderr)
        sys.exit(1)

    print(f"Found {total_files} PHP files in app/Actions")
    print()

    # Organize files by folder (depth 1)
    folders = defaultdict(list)
    for file in files:
        parts = file.split('/')
        if len(parts) >= 3:
            folder = parts[2]  # e.g., "Invoices", "Orders", etc.
            folders[folder].append(file)

    # Analyze each folder
    folder_stats = {}
    total_author_lines = defaultdict(int)

    for folder in sorted(folders.keys()):
        print(f"Processing {folder}/ ({len(folders[folder])} files)...")
        sys.stderr.flush()

        author_lines = defaultdict(int)
        processed = 0

        for file in folders[folder]:
            processed += 1

            try:
                # Get blame for this file
                blame_output = run_command(
                    f'git blame --line-porcelain "{file}"',
                    cwd=str(repo_path)
                )

                # Count lines per author
                for line in blame_output.split('\n'):
                    if line.startswith('author '):
                        author = line[7:]  # Remove 'author ' prefix
                        author_lines[author] += 1
                        total_author_lines[author] += 1
            except:
                continue

        folder_stats[folder] = dict(author_lines)

    # Generate report
    formatted_file = f"{output_file}.formatted"

    with open(formatted_file, 'w') as f:
        f.write("=" * 100 + "\n")
        f.write("Lines of Code Analysis - app/Actions Directory\n")
        f.write("Grouped by Subfolder (Depth 1)\n")
        f.write("=" * 100 + "\n\n")

        # Calculate totals
        grand_total = sum(total_author_lines.values())
        total_folders = len(folder_stats)

        # Summary stats
        f.write(f"Total Folders: {total_folders}\n")
        f.write(f"Total Files: {total_files}\n")
        f.write(f"Total Lines: {grand_total:,}\n\n")

        # Overall stats by author
        f.write("=" * 100 + "\n")
        f.write("OVERALL STATS (All folders combined)\n")
        f.write("=" * 100 + "\n\n")
        f.write(f"{'Author':<45} {'Lines':>15} {'Percentage':>15}\n")
        f.write("-" * 100 + "\n")

        sorted_total = sorted(total_author_lines.items(), key=lambda x: x[1], reverse=True)
        for author, count in sorted_total:
            percentage = (count / grand_total * 100) if grand_total > 0 else 0
            f.write(f"{author:<45} {count:>15,} {percentage:>14.2f}%\n")

        f.write("-" * 100 + "\n")
        f.write(f"{'TOTAL':<45} {grand_total:>15,} {100:>14.2f}%\n\n")

        # Per-folder breakdown
        f.write("=" * 100 + "\n")
        f.write("BREAKDOWN BY FOLDER\n")
        f.write("=" * 100 + "\n\n")

        for folder in sorted(folder_stats.keys()):
            folder_total = sum(folder_stats[folder].values())
            f.write(f"\n📁 {folder}/ ({folder_total:,} lines)\n")
            f.write("-" * 100 + "\n")
            f.write(f"{'Author':<45} {'Lines':>15} {'% of Folder':>15}\n")
            f.write("-" * 100 + "\n")

            sorted_folder = sorted(
                folder_stats[folder].items(),
                key=lambda x: x[1],
                reverse=True
            )

            for author, count in sorted_folder:
                percentage = (count / folder_total * 100) if folder_total > 0 else 0
                f.write(f"{author:<45} {count:>15,} {percentage:>14.2f}%\n")

            f.write("-" * 100 + "\n")
            f.write(f"{'Subtotal':<45} {folder_total:>15,} {100:>14.2f}%\n")

        f.write("\n" + "=" * 100 + "\n")
        f.write(f"Report generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write("=" * 100 + "\n")

    # Print to console
    print()
    print("=" * 100)
    print("Lines of Code Analysis - app/Actions Directory")
    print("Grouped by Subfolder (Depth 1)")
    print("=" * 100)
    print()

    grand_total = sum(total_author_lines.values())

    print(f"Total Folders: {total_folders}")
    print(f"Total Files: {total_files}")
    print(f"Total Lines: {grand_total:,}\n")

    print("=" * 100)
    print("OVERALL STATS (All folders combined)")
    print("=" * 100)
    print()
    print(f"{'Author':<45} {'Lines':>15} {'Percentage':>15}")
    print("-" * 100)

    sorted_total = sorted(total_author_lines.items(), key=lambda x: x[1], reverse=True)
    for author, count in sorted_total:
        percentage = (count / grand_total * 100) if grand_total > 0 else 0
        print(f"{author:<45} {count:>15,} {percentage:>14.2f}%")

    print("-" * 100)
    print(f"{'TOTAL':<45} {grand_total:>15,} {100:>14.2f}%")

    print()
    print("=" * 100)
    print("BREAKDOWN BY FOLDER")
    print("=" * 100)

    for folder in sorted(folder_stats.keys()):
        folder_total = sum(folder_stats[folder].values())
        print(f"\n📁 {folder}/ ({folder_total:,} lines)")
        print("-" * 100)
        print(f"{'Author':<45} {'Lines':>15} {'% of Folder':>15}")
        print("-" * 100)

        sorted_folder = sorted(
            folder_stats[folder].items(),
            key=lambda x: x[1],
            reverse=True
        )

        for author, count in sorted_folder:
            percentage = (count / folder_total * 100) if folder_total > 0 else 0
            print(f"{author:<45} {count:>15,} {percentage:>14.2f}%")

        print("-" * 100)
        print(f"{'Subtotal':<45} {folder_total:>15,} {100:>14.2f}%")

    print()
    print("✓ Analysis complete!")
    print(f"✓ Formatted report saved to: {formatted_file}")

if __name__ == "__main__":
    main()
