#!/usr/bin/env python3
"""
scripts/github/gh_tool.py
Reusable, parameterized CLI utility for GitHub API interactions in AlamiaAccounts.
Eliminates ad-hoc script generation and preserves model generation tokens.
"""

import sys
import os
import argparse
import subprocess
import json
import urllib.request
import urllib.error

REPO_OWNER = "AlamiaSoft"
REPO_NAME = "AlamiaAccounts"
API_BASE = f"https://api.github.com/repos/{REPO_OWNER}/{REPO_NAME}"

def get_git_token() -> str:
    """Retrieve the GitHub authentication token from git credential storage or environment."""
    env_token = os.environ.get("GITHUB_TOKEN") or os.environ.get("GH_TOKEN")
    if env_token:
        return env_token

    try:
        p = subprocess.Popen(
            ['git', 'credential', 'fill'],
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        out, _ = p.communicate('protocol=https\nhost=github.com\n\n')
        for line in out.splitlines():
            if line.startswith('password='):
                return line.split('=', 1)[1]
    except Exception as e:
        sys.stderr.write(f"Warning: Failed to read git credential: {e}\n")

    return ""

def make_request(url: str, method: str = 'GET', data: dict = None, token: str = ""):
    headers = {
        'Accept': 'application/vnd.github.v3+json',
        'User-Agent': 'Alamia-Agent-Tooling'
    }
    if token:
        headers['Authorization'] = f'token {token}'

    body = json.dumps(data).encode('utf-8') if data is not None else None
    if data is not None:
        headers['Content-Type'] = 'application/json'

    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req) as resp:
            content = resp.read().decode('utf-8')
            return json.loads(content) if content else {}
    except urllib.error.HTTPError as e:
        err_msg = e.read().decode('utf-8', errors='replace')
        sys.stderr.write(f"HTTPError {e.code}: {err_msg}\n")
        sys.exit(1)
    except Exception as e:
        sys.stderr.write(f"Error: {e}\n")
        sys.exit(1)

def cmd_pr(args, token):
    url = f"{API_BASE}/pulls/{args.number}"
    res = make_request(url, token=token)
    print(json.dumps({
        "number": res.get("number"),
        "title": res.get("title"),
        "state": res.get("state"),
        "merged": res.get("merged"),
        "head": {
            "ref": res.get("head", {}).get("ref"),
            "sha": res.get("head", {}).get("sha"),
        },
        "base": {
            "ref": res.get("base", {}).get("ref"),
            "sha": res.get("base", {}).get("sha"),
        },
        "draft": res.get("draft"),
        "comments": res.get("comments"),
        "html_url": res.get("html_url")
    }, indent=2))

def cmd_issue(args, token):
    url = f"{API_BASE}/issues/{args.number}"
    res = make_request(url, token=token)
    print(f"Title: {res.get('title')}")
    print(f"State: {res.get('state')}")
    print(f"URL: {res.get('html_url')}")
    print("\n--- BODY ---\n")
    print(res.get('body', ''))

def cmd_comments(args, token):
    url = f"{API_BASE}/issues/{args.number}/comments"
    comments = make_request(url, token=token)
    if args.last:
        comments = comments[-args.last:]
    
    print(f"Total Comments Retrieved: {len(comments)}")
    for c in comments:
        print(f"\n=======================================================")
        print(f"Comment ID: {c.get('id')} | Author: {c.get('user', {}).get('login')} | Date: {c.get('created_at')}")
        print(f"URL: {c.get('html_url')}")
        print("-------------------------------------------------------")
        print(c.get('body', ''))

def cmd_comment(args, token):
    content = ""
    if os.path.isfile(args.text_or_file):
        with open(args.text_or_file, 'r', encoding='utf-8') as f:
            content = f.read()
    else:
        content = args.text_or_file

    if not content.strip():
        sys.stderr.write("Error: Comment content cannot be empty.\n")
        sys.exit(1)

    url = f"{API_BASE}/issues/{args.number}/comments"
    res = make_request(url, method='POST', data={'body': content}, token=token)
    print(f"Comment posted successfully: {res.get('html_url')}")

def main():
    parser = argparse.ArgumentParser(description="AlamiaAccounts GitHub Utility")
    subparsers = parser.add_subparsers(dest="command", required=True)

    # pr
    p_pr = subparsers.add_parser("pr", help="View pull request details")
    p_pr.add_argument("number", type=int, help="Pull request number")

    # issue
    p_issue = subparsers.add_parser("issue", help="View issue details")
    p_issue.add_argument("number", type=int, help="Issue number")

    # comments
    p_comments = subparsers.add_parser("comments", help="List comments on a PR or issue")
    p_comments.add_argument("number", type=int, help="Issue/PR number")
    p_comments.add_argument("--last", type=int, default=None, help="Show last N comments")

    # comment
    p_post = subparsers.add_parser("comment", help="Post a comment to a PR or issue")
    p_post.add_argument("number", type=int, help="Issue/PR number")
    p_post.add_argument("text_or_file", help="Comment text or path to a markdown file")

    args = parser.parse_args()
    token = get_git_token()
    if not token:
        sys.stderr.write("Error: Unable to authenticate with GitHub. Ensure git credentials or GITHUB_TOKEN are set.\n")
        sys.exit(1)

    if args.command == "pr":
        cmd_pr(args, token)
    elif args.command == "issue":
        cmd_issue(args, token)
    elif args.command == "comments":
        cmd_comments(args, token)
    elif args.command == "comment":
        cmd_comment(args, token)

if __name__ == "__main__":
    main()
