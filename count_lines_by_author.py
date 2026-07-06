#!/usr/bin/env python3
import subprocess
import sys
from collections import defaultdict

author_lines = defaultdict(int)

try:
    # Get all tracked files
    result = subprocess.run(
        ['git', 'ls-files'],
        cwd='/Users/raul/aiku',
        capture_output=True,
        text=True,
        timeout=30
    )
    files = result.stdout.strip().split('\n')

    total_files = len(files)
    processed = 0

    for file in files:
        if not file:
            continue

        processed += 1
        if processed % 500 == 0:
            print(f"Processing {processed}/{total_files}...", file=sys.stderr)

        try:
            blame_result = subprocess.run(
                ['git', 'blame', '--line-porcelain', file],
                cwd='/Users/raul/aiku',
                capture_output=True,
                text=True,
                timeout=5
            )

            for line in blame_result.stdout.split('\n'):
                if line.startswith('author '):
                    author = line[7:]  # Remove 'author '
                    author_lines[author] += 1
        except subprocess.TimeoutExpired:
            continue
        except Exception:
            continue

    # Sort and print results
    sorted_authors = sorted(author_lines.items(), key=lambda x: x[1], reverse=True)

    print("\n" + "="*60)
    print("Lines of Code by Author (Current Codebase)")
    print("="*60)

    for author, count in sorted_authors:
        print(f"{author:<40} {count:>10,}")

    print("="*60)
    total_lines = sum(count for _, count in sorted_authors)
    print(f"{'TOTAL':<40} {total_lines:>10,}")

except Exception as e:
    print(f"Error: {e}", file=sys.stderr)
    sys.exit(1)
