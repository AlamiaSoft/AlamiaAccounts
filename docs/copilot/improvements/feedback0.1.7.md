Yes. This exposes a **different missing capability: party/contact/organization resolution**.

The system is currently treating:

> `Who is Ali Raza?`

as a generic accounting search, and:

> `Who is this IZOC???`

as a literal entity search for `"this izoc"`.

But your application already has evidence of **IZOC Pvt Ltd in transactions**, so the copilot should be able to resolve that organization from accounting data.

### What should happen

For:

> **Who is Ali Raza?**

The copilot should first determine:

```text
entity type = person/contact
entity name = Ali Raza
```

Then search the application's **contacts/parties**, not accounts.

For:

> **Who is this IZOC???**

Conversation context matters. If the previous conversation established:

> Ali Raza of IZOC Pvt Ltd

then `"this IZOC"` should resolve to:

```text
entity type = organization
entity = IZOC Pvt Ltd
```

Even without that context, fuzzy/normalized organization resolution should turn:

```text
"this izoc"
"IZOC"
"IZOC Ltd"
"IZOC Pvt Ltd"
"IZOC private limited"
```

into the same organization candidate where appropriate.

---

## The bigger issue

Your current intent taxonomy doesn't really have a **party/entity inquiry**.

You have:

```text
INQUIRE_ACCOUNT
INQUIRE_VOUCHER
FIND_TRANSACTION
INQUIRE_REPORT
...
GENERAL_SEARCH
```

I'd add:

```text
INQUIRE_PARTY
```

or, more generically:

```text
INQUIRE_ENTITY
```

with:

```json
{
  "intent": "INQUIRE_ENTITY",
  "entity_type": "person",
  "party": "Ali Raza"
}
```

and:

```json
{
  "intent": "INQUIRE_ENTITY",
  "entity_type": "organization",
  "organization": "IZOC Pvt Ltd"
}
```

Then your application can answer from actual data.

---

### And don't limit entity discovery to a Contacts table

This is important for your case.

If **IZOC Pvt Ltd isn't formally registered as a contact**, but appears in:

* voucher description
* party/customer/vendor fields
* transaction memo
* invoice
* payment
* receipt
* journal line
* organization/contact relationship

the copilot should still be able to say something like:

> **IZOC Pvt Ltd** appears in your accounting records as a party involved in transactions. I found 3 related transactions, including voucher **SV-2026-112**.

Rather than:

> I couldn't find any contacts.

That's a much more useful accounting copilot.

### Therefore your resolver should have layers

```text
"IZOC"
   ↓
1. Exact Contact/Organization
   ↓
2. Normalized Contact/Organization
   ↓
3. Aliases
   ↓
4. Accounting transaction references
   ↓
5. Fuzzy candidate matching
   ↓
6. Ambiguous → ask user
```

Not:

```text
"IZOC"
   ↓
search accounts
   ↓
nothing found
   ↓
not found
```

---

## One more subtle problem: "Who is this IZOC?"

Your classifier probably extracts the literal noun phrase:

```text
"this izoc"
```

It should understand **deictic words** such as:

* this
* that
* them
* he
* she
* it
* this company
* that customer

as requiring **conversation/entity resolution**, rather than part of the entity name.

For example:

> Show me the transaction with IZOC.

→ organization = `IZOC`

Then:

> Who is this IZOC?

should inherit the immediately relevant entity:

```text
organization = IZOC
```

That's exactly the sort of thing your **Copilot Behavioral Contract Tests** should now cover.

### I'd add these tests

| #  | Query                                     | Expected                             |
| -- | ----------------------------------------- | ------------------------------------ |
| 27 | Who is Ali Raza?                          | `INQUIRE_ENTITY`, person             |
| 28 | Who is IZOC?                              | `INQUIRE_ENTITY`, organization       |
| 29 | Who is IZOC Pvt Ltd?                      | `INQUIRE_ENTITY`, organization       |
| 30 | Tell me about Ali Raza                    | `INQUIRE_ENTITY`, person             |
| 31 | Tell me about IZOC                        | `INQUIRE_ENTITY`, organization       |
| 32 | Who is this IZOC?                         | Resolve conversational entity        |
| 33 | Who is he?                                | Resolve previous person              |
| 34 | What company is Ali Raza associated with? | person → organization relationship   |
| 35 | Show me what we have with IZOC            | organization → transaction history   |
| 36 | What did we pay IZOC?                     | organization + outgoing transactions |

**The key architectural distinction is now becoming clear:**

> **Search is not entity resolution.**

Your `SearchService` currently does too much generic text searching. The copilot needs a proper **Entity Resolver** that can resolve *people, organizations, accounts, vouchers and transactions* before the relevant domain query runs.
