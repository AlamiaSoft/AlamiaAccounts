#!/usr/bin/env python3
"""
scripts/github/gh_tool.py
Reusable, parameterized CLI utility for GitHub API interactions in AlamiaAccounts.
Eliminates ad-hoc script generation and preserves model generation tokens.
"""

import sys
import io
import os
import argparse
import subprocess
import json
import urllib.request
import urllib.error

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

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

def cmd_create_pr(args, token):
    body = ""
    if args.body_file and os.path.isfile(args.body_file):
        with open(args.body_file, 'r', encoding='utf-8') as f:
            body = f.read()
    elif args.body:
        body = args.body

    url = f"{API_BASE}/pulls"
    data = {
        "title": args.title,
        "head": args.head,
        "base": args.base,
        "body": body
    }
    if args.draft:
        data["draft"] = True
    res = make_request(url, method='POST', data=data, token=token)
    print(f"PR created successfully: #{res.get('number')} - {res.get('html_url')}")

def cmd_runs(args, token):
    url = f"{API_BASE}/actions/runs?per_page={args.limit}"
    res = make_request(url, token=token)
    runs = res.get("workflow_runs", [])
    print(f"Total Workflow Runs: {len(runs)}")
    for r in runs:
        print(f"Run ID: {r.get('id')} | Name: {r.get('name')} | Event: {r.get('event')} | Status: {r.get('status')} | Conclusion: {r.get('conclusion')}")
        print(f"  Branch: {r.get('head_branch')} | Commit: {r.get('head_sha')[:7]} | URL: {r.get('html_url')}")

def cmd_run_jobs(args, token):
    url = f"{API_BASE}/actions/runs/{args.run_id}/jobs"
    res = make_request(url, token=token)
    jobs = res.get("jobs", [])
    print(f"Total Jobs: {len(jobs)}")
    for j in jobs:
        print(f"Job ID: {j.get('id')} | Name: {j.get('name')} | Status: {j.get('status')} | Conclusion: {j.get('conclusion')}")
        for step in j.get("steps", []):
            if step.get("conclusion") != "success":
                print(f"   Step: {step.get('name')} | Status: {step.get('status')} | Conclusion: {step.get('conclusion')}")

def cmd_job_logs(args, token):
    url = f"{API_BASE}/actions/jobs/{args.job_id}/logs"
    headers = {
        'Accept': 'application/vnd.github.v3+json',
        'User-Agent': 'Alamia-Agent-Tooling',
        'Authorization': f'token {token}'
    }
    class NoAuthRedirectHandler(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            new_req = super().redirect_request(req, fp, code, msg, headers, newurl)
            if new_req and 'Authorization' in new_req.headers:
                del new_req.headers['Authorization']
            return new_req

    opener = urllib.request.build_opener(NoAuthRedirectHandler())
    req = urllib.request.Request(url, headers=headers)
    try:
        with opener.open(req) as resp:
            content = resp.read().decode('utf-8', errors='replace')
            lines = content.splitlines()
            print(f"Total Log Lines: {len(lines)}")
            # print error/failure lines
            fail_lines = [line for line in lines if "FAIL" in line or "ERR" in line or "Exception" in line or "Error" in line or "failed" in line]
            print(f"Relevant failure lines ({len(fail_lines)}):")
            for line in fail_lines[-30:]:
                print(line)
            if args.tail:
                print(f"\n--- Tail {args.tail} lines ---")
                for line in lines[-args.tail:]:
                    print(line)
    except Exception as e:
        sys.stderr.write(f"Error fetching logs: {e}\n")

def main():
    parser = argparse.ArgumentParser(description="AlamiaAccounts GitHub Utility")
    subparsers = parser.add_subparsers(dest="command", required=True)

    # pr
    p_pr = subparsers.add_parser("pr", help="View pull request details")
    p_pr.add_argument("number", type=int, help="Pull request number")

    # create-pr
    p_cpr = subparsers.add_parser("create-pr", help="Create a pull request")
    p_cpr.add_argument("--title", required=True, help="PR title")
    p_cpr.add_argument("--head", required=True, help="Head branch")
    p_cpr.add_argument("--base", default="main", help="Base branch (default: main)")
    p_cpr.add_argument("--body", help="PR description text")
    p_cpr.add_argument("--body-file", help="Path to markdown file containing PR description")
    p_cpr.add_argument("--draft", action="store_true", help="Create as draft PR")

    # runs
    p_runs = subparsers.add_parser("runs", help="List recent GitHub Actions workflow runs")
    p_runs.add_argument("--limit", type=int, default=10, help="Number of runs to show")

    # run-jobs
    p_rj = subparsers.add_parser("run-jobs", help="List jobs in a GitHub Actions workflow run")
    p_rj.add_argument("run_id", type=int, help="Workflow run ID")

    # job-logs
    p_jl = subparsers.add_parser("job-logs", help="Fetch logs for a GitHub Actions job")
    p_jl.add_argument("job_id", type=int, help="Job ID")
    p_jl.add_argument("--tail", type=int, default=50, help="Tail N lines")

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
    elif args.command == "create-pr":
        cmd_create_pr(args, token)
    elif args.command == "runs":
        cmd_runs(args, token)
    elif args.command == "run-jobs":
        cmd_run_jobs(args, token)
    elif args.command == "job-logs":
        cmd_job_logs(args, token)
    elif args.command == "issue":
        cmd_issue(args, token)
    elif args.command == "comments":
        cmd_comments(args, token)
    elif args.command == "comment":
        cmd_comment(args, token)

if __name__ == "__main__":
    main()
