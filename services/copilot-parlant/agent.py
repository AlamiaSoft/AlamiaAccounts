import os
import re
import uvicorn
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional, Dict, Any, List
from dotenv import load_dotenv

from tools.capabilities_bridge import (
    execute_alamia_capability,
    resolve_entity,
    lookup_account,
    account_balance,
    lookup_voucher,
    draft_voucher,
    reverse_voucher,
    get_financial_report,
    list_situations,
)
from guidelines.safety_immutability import GUIDELINES

load_dotenv()

app = FastAPI(title="Alamia Accounts - Parlant Copilot Sidecar", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# In-memory session store
sessions: Dict[str, List[Dict[str, Any]]] = {}

class ChatMessageRequest(BaseModel):
    session_id: Optional[str] = "default"
    message: str
    company_code: Optional[str] = "MAIN"
    context: Optional[Dict[str, Any]] = None

class ChatMessageResponse(BaseModel):
    session_id: str
    sender: str
    message: str
    card_type: str
    data: Optional[Dict[str, Any]] = None
    actions: Optional[List[Dict[str, Any]]] = None

@app.get("/health")
async def health():
    return {
        "status": "healthy",
        "service": "alamia-copilot-parlant",
        "ollama_endpoint": os.getenv("AI_ENDPOINT", "http://host.docker.internal:11434"),
        "model": os.getenv("AI_MODEL", "qwen3.5:4b"),
        "guidelines_count": len(GUIDELINES),
    }

@app.get("/guidelines")
async def get_guidelines():
    return {"guidelines": GUIDELINES}

@app.post("/sessions/{session_id}/messages", response_model=ChatMessageResponse)
async def handle_message(session_id: str, payload: ChatMessageRequest):
    message_text = payload.message.strip()
    company_code = payload.company_code or "MAIN"
    
    if session_id not in sessions:
        sessions[session_id] = []
        
    sessions[session_id].append({"role": "user", "content": message_text})

    lower_msg = message_text.lower()
    
    # 1. Tax Advisory / Evasion Refusal
    if any(k in lower_msg for k in ["evade", "tax evasion", "hide cash", "tax advice", "tax loophole", "avoid tax"]):
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Policy Refusal (Tax & Regulatory Advisory)**: Taliya is an operational accounting execution assistant and is strictly prohibited from providing tax evasion advice, tax planning strategies, or legal interpretations.\n\nPlease consult a certified chartered accountant (CA / CPA) or licensed tax authority for tax and regulatory guidance.",
            card_type="safety_policy",
            data={"policy": "TAX_ADVISORY_PROHIBITED"},
            actions=[
                {"label": "📊 Trial Balance", "action": "draft_prompt", "payload": {"prompt": "Show Trial Balance summary"}},
                {"label": "📄 View Daybook", "action": "navigate_page", "payload": {"page": "daybook"}},
                {"label": "📖 Chart of Accounts", "action": "navigate_page", "payload": {"page": "coa"}}
            ]
        )

    # 2. Chit-Chat & General Knowledge Refusal
    if any(k in lower_msg for k in ["capital of", "tell me a joke", "weather today", "weather forecast", "meaning of life", "who is the president"]):
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="I am **Taliya**, an institutional accounting assistant specialized exclusively in **Alamia Accounts** double-entry bookkeeping, ledger statements, vouchers, and financial reports.\n\nI cannot answer general knowledge questions, chit-chat, or non-financial inquiries.\n\nHow can I assist you with your books today?",
            card_type="out_of_scope",
            data={"type": "refusal_chitchat"},
            actions=[
                {"label": "📊 Trial Balance", "action": "draft_prompt", "payload": {"prompt": "Show Trial Balance summary"}},
                {"label": "🏦 Meezan Bank Balance", "action": "draft_prompt", "payload": {"prompt": "What is the balance of Meezan Bank?"}},
                {"label": "📄 View Daybook", "action": "navigate_page", "payload": {"page": "daybook"}}
            ]
        )

    # 3. Untracked Data Refusal
    if any(k in lower_msg for k in ["system password", "admin password", "database password", "api secret", "private key"]):
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Data Boundary**: The requested information is not tracked within the general ledger or chart of accounts. Taliya only accesses double-entry financial journals, accounts, fiscal periods, and subledger balances.",
            card_type="out_of_scope",
            data={"type": "refusal_untracked"},
            actions=[
                {"label": "📄 View Daybook", "action": "navigate_page", "payload": {"page": "daybook"}},
                {"label": "📖 Chart of Accounts", "action": "navigate_page", "payload": {"page": "coa"}}
            ]
        )

    # 4. Temporal Plausibility Invariant
    if any(k in lower_msg for k in ["last century", "century ago", "1800", "1900", "200 years ago", "millennium"]):
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Accounting Guardrail (Temporal Invariant)**: The specified date expression is outside valid fiscal operating periods.\n\nTransactions and ledger records can only be queried or recorded within active or valid historical fiscal accounting periods.",
            card_type="safety_policy",
            data={"policy": "FISCAL_PERIOD_PROTECTION"},
            actions=[
                {"label": "📄 View Daybook", "action": "navigate_page", "payload": {"page": "daybook"}},
                {"label": "📅 Accounting Periods", "action": "navigate_page", "payload": {"page": "periods"}}
            ]
        )

    # 5. Destructive Voucher & Ledger Immutability
    if "delete" in lower_msg and ("voucher" in lower_msg or "ob-" in lower_msg or "jv-" in lower_msg or "sv-" in lower_msg):
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Accounting Invariant (GAAP/IFRS)**: Posted vouchers and ledger records cannot be deleted or purged to preserve permanent double-entry audit history.\n\nIf a voucher was posted in error, you can create a compensating **Reversal Voucher** (`REV-`) with documented audit reasons.",
            card_type="safety_policy",
            data={"policy": "HISTORICAL_LEDGER_IMMUTABILITY"},
            actions=[
                {"label": "Reverse in Daybook", "action": "navigate_page", "payload": {"page": "daybook"}},
                {"label": "📖 Chart of Accounts", "action": "navigate_page", "payload": {"page": "coa"}}
            ]
        )
        
    # 6. Destructive Narration
    if ("delete" in lower_msg or "remove" in lower_msg or "change" in lower_msg) and "narration" in lower_msg:
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Accounting Invariant (Narration Immutability)**: The narration on a posted voucher is an immutable accounting record. It cannot be silently deleted or edited in-place.\n\nTo correct inaccurate descriptions, follow the standard reversal workflow (`REV-`) or review the voucher in Daybook.",
            card_type="safety_policy",
            data={"policy": "VOUCHER_DESCRIPTION_IMMUTABILITY"},
            actions=[
                {"label": "📄 View Daybook", "action": "navigate_page", "payload": {"page": "daybook"}},
                {"label": "📖 Chart of Accounts", "action": "navigate_page", "payload": {"page": "coa"}}
            ]
        )

    # 7. Destructive Account
    if "delete all accounts" in lower_msg or "wipe accounts" in lower_msg or "delete account" in lower_msg:
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Accounting Guardrail (Prohibited Action)**: Chart of Accounts and general ledger accounts cannot be deleted or purged via AI Copilot.\n\n• Double-entry accounting rules protect accounts with posted history permanently.\n• Unused accounts can be safely archived from the Chart of Accounts interface.",
            card_type="safety_policy",
            data={"policy": "CHART_OF_ACCOUNTS_PROTECTION"},
            actions=[
                {"label": "📖 Open Chart of Accounts", "action": "navigate_page", "payload": {"page": "coa"}}
            ]
        )

    # 8. Mutate Amount
    if "change" in lower_msg and "amount" in lower_msg and "voucher" in lower_msg:
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="🔒 **Accounting Invariant (Ledger Entry Immutability)**: Posted transaction amounts cannot be altered in-place.\n\nTo correct an incorrect amount, create an adjusting journal entry or reverse the voucher (`REV-`).",
            card_type="safety_policy",
            data={"policy": "LEDGER_ENTRY_IMMUTABILITY"},
            actions=[
                {"label": "📄 Open Daybook", "action": "navigate_page", "payload": {"page": "daybook"}}
            ]
        )

    # 9. Direct execution via Alamia 360 Capabilities
    # Self identity
    if lower_msg in ["who taliya", "who taliya??", "who is taliya", "help"]:
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="I am **Taliya**, your AI Accounting Copilot backed by Alamia 360.\n\nI can help you look up accounts, inspect vouchers, view financial statements, and draft transactions.",
            card_type="help",
            actions=[
                {"label": "📊 Trial Balance", "action": "draft_prompt", "payload": {"prompt": "Show Trial Balance summary"}},
                {"label": "🏦 Meezan Bank Balance", "action": "draft_prompt", "payload": {"prompt": "What is the balance of Meezan Bank?"}}
            ]
        )

    # Financial statements
    if "trial balance" in lower_msg or "tb" in lower_msg:
        tb = await get_financial_report("trial-balance", company_code=company_code)
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="Here is the Trial Balance summary as of today.",
            card_type="financial_report",
            data=tb
        )

    # Voucher lookup
    vm = re.search(r'\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b', lower_msg)
    if vm:
        v_data = await lookup_voucher(vm.group(0).upper(), company_code=company_code)
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message=f"Here are the details for Voucher **{vm.group(0).upper()}**.",
            card_type="voucher_brief",
            data=v_data
        )

    # Default out-of-scope for ambiguous or unmapped inputs
    return ChatMessageResponse(
        session_id=session_id,
        sender="Taliya",
        message="I am **Taliya**, an institutional accounting assistant for **Alamia Accounts**.\n\nI couldn't match your request to a supported accounting operation. I can only execute defined accounting workflows in your capability catalog.\n\nHow can I help with your books today?",
        card_type="out_of_scope",
        data={"query": message_text},
        actions=[
            {"label": "📊 Trial Balance", "action": "draft_prompt", "payload": {"prompt": "Show Trial Balance summary"}},
            {"label": "🏦 Meezan Bank Balance", "action": "draft_prompt", "payload": {"prompt": "What is the balance of Meezan Bank?"}},
            {"label": "📄 View Daybook", "action": "navigate_page", "payload": {"page": "daybook"}}
        ]
    )

if __name__ == "__main__":
    port = int(os.getenv("PORT", 8800))
    uvicorn.run("agent.py:app", host="0.0.0.0", port=port, reload=False)
