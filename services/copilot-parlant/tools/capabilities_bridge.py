import os
import httpx
from typing import Any, Dict, Optional

BACKEND_URL = os.getenv("BACKEND_API_URL", "http://backend:8000/api")

async def execute_alamia_capability(capability: str, payload: Dict[str, Any], company_code: Optional[str] = None) -> Dict[str, Any]:
    """
    Executes a registered Alamia 360 capability via the backend execution gateway.
    """
    headers = {"Content-Type": "application/json"}
    if company_code:
        headers["X-Company-Code"] = company_code

    url = f"{BACKEND_URL}/copilot/capabilities-public/{capability}/execute"
    
    async with httpx.AsyncClient(timeout=10.0) as client:
        try:
            resp = await client.post(url, json={"input": payload, "company_code": company_code}, headers=headers)
            if resp.status_code == 200:
                data = resp.json()
                return data.get("data", {})
            return {
                "error": f"Backend returned HTTP {resp.status_code}",
                "detail": resp.text
            }
        except Exception as e:
            return {"error": f"Failed to connect to Alamia backend: {str(e)}"}

# Tool bridge functions for Parlant:

async def resolve_entity(query: str, company_code: Optional[str] = None) -> Dict[str, Any]:
    """Fuzzy searches contacts, accounts, and ledger narration memos to resolve entity identity."""
    return await execute_alamia_capability("resolve_entity", {"query": query}, company_code)

async def lookup_account(query: str, leaf_only: bool = False, company_code: Optional[str] = None) -> Dict[str, Any]:
    """Searches Chart of Accounts for matching accounts by name or code."""
    return await execute_alamia_capability("lookup_account", {"query": query, "leaf_only": leaf_only}, company_code)

async def account_balance(account: str, company_code: Optional[str] = None) -> Dict[str, Any]:
    """Retrieves live running balance and account position for an account code or name."""
    return await execute_alamia_capability("account_balance", {"account": account}, company_code)

async def lookup_voucher(reference: str, company_code: Optional[str] = None) -> Dict[str, Any]:
    """Retrieves exact voucher details and line items by reference."""
    return await execute_alamia_capability("lookup_voucher", {"reference": reference}, company_code)

async def draft_voucher(description: str, details: list, type: str = "journal", company_code: Optional[str] = None) -> Dict[str, Any]:
    """Drafts an uncommitted double-entry voucher and verifies mathematical balance (Dr === Cr)."""
    return await execute_alamia_capability("draft_voucher", {"description": description, "details": details, "type": type}, company_code)

async def reverse_voucher(reference: str, reason: str = "Reversal via Copilot", company_code: Optional[str] = None) -> Dict[str, Any]:
    """Initiates compensating reversal (REV-) workflow for a posted voucher."""
    return await execute_alamia_capability("reverse_voucher", {"reference": reference, "reason": reason}, company_code)

async def get_financial_report(report_type: str, as_of_date: Optional[str] = None, company_code: Optional[str] = None) -> Dict[str, Any]:
    """Retrieves financial reports (trial-balance, profit-loss, balance-sheet)."""
    return await execute_alamia_capability("get_financial_report", {"report_type": report_type, "as_of_date": as_of_date}, company_code)

async def list_situations(company_code: Optional[str] = None) -> Dict[str, Any]:
    """Retrieves active operational situations and financial alerts."""
    return await execute_alamia_capability("list_situations", {}, company_code)
