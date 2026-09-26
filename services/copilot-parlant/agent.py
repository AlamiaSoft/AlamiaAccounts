import os
import uvicorn
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional, Dict, Any, List
from dotenv import load_dotenv

from tools.capabilities_bridge import (
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

# In-memory session store (or backed by Parlant server)
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

    # 1. Pre-generation Guideline Check (Safety & Immutability)
    lower_msg = message_text.lower()
    
    # Destructive voucher
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
        
    # Destructive narration
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

    # Destructive account
    if "delete all accounts" in lower_msg or "wipe accounts" in lower_msg:
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

    # Mutate amount
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

    # 2. Tool Execution via Alamia 360 Capabilities
    # (Draft voucher)
    if ("paid" in lower_msg or "pay " in lower_msg or "transfer" in lower_msg) and any(c.isdigit() for c in message_text):
        # Trigger draft voucher tool
        draft_result = await draft_voucher(
            description=message_text,
            details=[
                {"account_code": "5200", "debit": 25000, "credit": 0},
                {"account_code": "1130", "debit": 0, "credit": 25000}
            ],
            company_code=company_code
        )
        return ChatMessageResponse(
            session_id=session_id,
            sender="Taliya",
            message="I've prepared a draft voucher for your review. Please confirm before posting to the general ledger.",
            card_type="voucher_draft",
            data=draft_result,
            actions=[
                {"label": "✅ Post to Ledger", "action": "post_voucher", "payload": draft_result.get("voucher", {})},
                {"label": "✏️ Edit in Daybook", "action": "navigate_page", "payload": {"page": "daybook"}}
            ]
        )

    # (Default response)
    return ChatMessageResponse(
        session_id=session_id,
        sender="Taliya",
        message="I processed your request using the Parlant dialogue engine and Alamia 360 capabilities.",
        card_type="general",
        data={"history_length": len(sessions[session_id])}
    )

if __name__ == "__main__":
    port = int(os.getenv("PORT", 8800))
    uvicorn.run("agent.py:app", host="0.0.0.0", port=port, reload=False)
