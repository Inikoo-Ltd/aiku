#!/usr/bin/env python3

"""
Analyze lines of code per author in git repository
Usage: python3 analyze_lines_by_author.py [output_file]
Example: python3 analyze_lines_by_author.py report.txt
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
    output_file = sys.argv[1] if len(sys.argv) > 1 else "lines_by_author_report.txt"
    repo_path = Path.cwd()

    # Check if we're in a git repository
    if not (repo_path / ".git").exists():
        print("Error: Not a git repository", file=sys.stderr)
        sys.exit(1)

    print("Starting analysis of all files in repository...")
    print("This may take several minutes depending on repository size.")
    print()

    # Get all files
    files_output = run_command("git ls-files", cwd=str(repo_path))
    files = [f for f in files_output.split('\n') if f]
    total_files = len(files)

    print(f"Found {total_files} files to process")
    print()

    author_lines = defaultdict(int)
    processed = 0

    for file in files:
        processed += 1

        # Show progress every 500 files
        if processed % 500 == 0:
            print(f"Processed {processed}/{total_files} files...", file=sys.stderr)

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
        except:
            continue

    # Sort by line count (descending)
    sorted_authors = sorted(author_lines.items(), key=lambda x: x[1], reverse=True)

    # Write raw results
    with open(output_file, 'w') as f:
        for author, count in sorted_authors:
            f.write(f"{count} {author}\n")

    # Write formatted report
    formatted_file = f"{output_file}.formatted"
    with open(formatted_file, 'w') as f:
        f.write("=" * 80 + "\n")
        f.write("Lines of Code by Author - Complete Analysis\n")
        f.write("=" * 80 + "\n\n")
        f.write(f"{'Author':<50} {'Lines of Code':>20}\n")
        f.write("=" * 80 + "\n")

        total_lines = 0
        for author, count in sorted_authors:
            f.write(f"{author:<50} {count:>20,}\n")
            total_lines += count

        f.write("=" * 80 + "\n")
        f.write(f"{'TOTAL':<50} {total_lines:>20,}\n")
        f.write("=" * 80 + "\n\n")
        f.write(f"Report generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")

    # Print to console
    print()
    print("=" * 80)
    print("Lines of Code by Author - Complete Analysis")
    print("=" * 80)
    print()
    print(f"{'Author':<50} {'Lines of Code':>20}")
    print("=" * 80)

    total_lines = 0
    for author, count in sorted_authors:
        print(f"{author:<50} {count:>20,}")
        total_lines += count

    print("=" * 80)
    print(f"{'TOTAL':<50} {total_lines:>20,}")
    print("=" * 80)
    print()

    print("✓ Analysis complete!")
    print(f"✓ Raw results saved to: {output_file}")
    print(f"✓ Formatted report saved to: {formatted_file}")

if __name__ == "__main__":
    main()
