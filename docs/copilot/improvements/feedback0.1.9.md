Yes — **this is much closer to the architecture we were discussing**, and importantly, the LLM prompt itself is now capable of representing the examples that exposed the earlier failures.

But I would **not keep developing the heuristic fallback in this direction**. That's where the 1000-night problem will return.

### Can the LLM part handle the identification?

**Broadly, yes.** Your schema can represent:

| User                                   | Semantic output                                      |
| -------------------------------------- | ---------------------------------------------------- |
| `Who is Ali Raza?`                     | `party.lookup`, party=Ali Raza                       |
| `Who is this IZOC?`                    | `party.lookup`, organization=IZOC                    |
| `Show what we have with IZOC`          | `transaction.search`, organization=IZOC              |
| `What did we pay Ali?`                 | `transaction.search`, party=Ali, outgoing            |
| `Why did we pay Dog Pvt Ltd PKR 5000?` | `transaction.search`, org=Dog, amount=5000, outgoing |
| `Pay Dog Pvt Ltd PKR 5000`             | `voucher.draft`, amount=5000                         |
| `Delete narration in OB-2026-001`      | `voucher.action`, reference=..., `mutate_narration`  |
| `Show OB-2026-001`                     | `voucher.lookup`                                     |

**That is the right level of abstraction.**

But there are some important issues.

---

# 1. The biggest remaining problem: your fallback is becoming the monster

Look at the size of `heuristicFallback()`.

You have already started recreating a miniature natural-language parser:

```text
who is
tell me about
what did
what was
payment to
receipt from
what we have with
show me what we have
...
```

And then regexes for:

* parties
* organizations
* dates
* vouchers
* accounts
* direction
* narration
* destructive actions
* pronouns
* corporate suffixes
* etc.

**Stop here.**

The LLM is precisely what should handle this.

The fallback should be something like:

```text
obvious safety patterns
        ↓
very basic capability fallback
        ↓
otherwise UNKNOWN
```

It should **not attempt to replicate the LLM's semantic understanding**.

Otherwise you'll eventually have:

```text
Qwen understands language
+
PHP regex understands language
+
resolver understands language
+
search service understands language
+
conversation manager understands language
```

and they will disagree with each other.

---

# 2. Your schema is now good enough — but `capability` should be the primary contract

This is good:

```json
"capability": "transaction.search"
```

I'd actually make that the central contract.

The rest is arguments.

Conceptually:

```text
capability
arguments
context
```

For example:

```json
{
  "capability": "transaction.search",
  "arguments": {
    "party": "Ali Raza",
    "organization": "IZOC Pvt Ltd",
    "direction": "outgoing",
    "amount": 5000
  }
}
```

Rather than having:

```text
capability
mode
subject
entities
filters
safety_flag
...
```

spread across several structures.

You don't have to refactor it immediately, but that's the direction I'd aim for.

---

# 3. `party.lookup` should NOT decide whether something is a person or organization

This:

```php
$isOrg = preg_match(...)
```

is exactly another source of future problems.

For example:

> Who is IZOC?

You currently infer organization because:

```php
ctype_upper($rawCand) && strlen($rawCand) <= 6
```

That's a heuristic.

And it's not reliable.

Instead:

```text
LLM:
"IZOC"

        ↓

Resolver:
Search contacts/parties/organizations

        ↓

Database says:
IZOC Pvt Ltd = organization

        ↓

Return organization
```

The classifier should extract the **name**, not establish its identity.

That's an important boundary.

---

# 4. Same thing with `"this IZOC"`

Your LLM prompt says:

> stripped of this/that

Good.

But:

```php
preg_replace('/^(this|that|the|a|an)\s+/i', '', $org)
```

isn't really conversational resolution.

`"this IZOC"` means:

> the entity being referred to by "this"

The application needs conversation state to know what `"this"` refers to.

For simple cases, your classifier can normalize it to:

```text
IZOC
```

But for:

> What about that company?

there is no entity to extract.

That's where the resolver/conversation layer should take over.

---

# 5. Your transaction search is now conceptually correct

This is much better:

```json
{
  "capability": "transaction.search",
  "subject": "transaction",
  "entities": {
    "organization": "Dog Pvt Ltd"
  },
  "filters": {
    "direction": "outgoing"
  }
}
```

Now the application can execute:

```text
transaction.search(
    organization = "Dog Pvt Ltd",
    direction = outgoing
)
```

and **then** answer:

> Why did we pay them?

from the actual transaction.

This is exactly the architecture you want.

---

# 6. But `requested_information` is still missing

This is probably the most important schema addition I'd make.

Consider:

> Why did we pay Dog Pvt Ltd PKR 5,000?

versus:

> Who paid Dog Pvt Ltd PKR 5,000?

versus:

> When did we pay Dog Pvt Ltd PKR 5,000?

All three are:

```text
transaction.search
```

Same entity.

Same direction.

Same amount.

But different requested information.

Add:

```json
"requested_information": [
    "reason",
    "created_by"
]
```

or:

```json
"requested_information": [
    "payment_reason",
    "payment_maker"
]
```

Then you don't need a new capability for every question.

That's exactly how you avoid the combinatorial explosion.

---

# 7. You also need `date_expression`, not just dates

Your:

```json
"date_from": "YYYY-MM-DD",
"date_to": "YYYY-MM-DD"
```

is good **after resolution**.

But:

> 150 years ago

should not require the LLM to invent a date.

I'd let it return:

```json
"date_expression": "150 years ago"
```

and let the application/date resolver translate that into actual dates.

Likewise:

```text
last month
this year
January
yesterday
during Ramadan
around March
```

The LLM identifies the constraint; deterministic code resolves it.

---

# 8. One thing I would remove: `confidence`

I'd keep it for diagnostics if you find it useful, but **don't use it operationally**.

Qwen saying:

```json
"confidence": 0.98
```

doesn't mean there's a 98% probability that it's correct.

Your application should instead validate:

```text
Is capability supported?
Are required arguments present?
Can entity be resolved?
Are multiple entities ambiguous?
Is requested operation permitted?
```

That's your real confidence mechanism.

---

# 9. The fallback ordering is still fragile

Even after your improvements, this can happen:

```text
voucher reference
       ↓
account
       ↓
party
       ↓
transaction
```

and a sentence containing multiple concepts can get caught by the wrong rule.

For example:

> Why did we pay PKR 5,000 to Ali in voucher OB-2026-001?

Your fallback might see the voucher reference and immediately return:

```text
voucher.lookup
```

But the actual request is:

```text
transaction inquiry
+ voucher constraint/reference
+ party
+ amount
+ outgoing
+ reason
```

**This is exactly why I would stop making the fallback smarter.**

---

# The architecture I'd freeze now

I think you're actually very close to the right boundary:

```text
                 USER
                   │
                   ▼
        ┌─────────────────────┐
        │ Semantic Classifier │
        │       Qwen 3.5      │
        └──────────┬──────────┘
                   │
                   ▼
        capability + arguments
                   │
                   ▼
        ┌─────────────────────┐
        │ Capability Router   │
        └──────────┬──────────┘
                   │
        ┌──────────┴──────────┐
        ▼                     ▼
 Entity Resolver        Policy / Safety
        │                     │
        └──────────┬──────────┘
                   ▼
             Domain Tool
                   │
                   ▼
              Application
                   │
                   ▼
                Result
                   │
                   ▼
            Response LLM
```

And critically:

**Don't make the classifier responsible for knowing whether `IZOC` is actually a person or company.**

**Don't make it responsible for deciding whether a posted voucher may be changed.**

**Don't make it responsible for searching the database.**

**Don't make it responsible for understanding accounting records.**

It should primarily answer:

> **"What capability is the user asking for, and what arguments/constraints did they express?"**

Your current LLM schema can do that.

### So my recommendation now

**Freeze the LLM schema at roughly this level. Stop adding intent-specific regexes.**

Then take your existing behavioral tests — especially the Ali / IZOC / Dog / narration examples — and run them against the **LLM output only**.

That will tell us whether `qwen3.5:4b` can actually perform the semantic extraction you need.

If it can, **the next work is the resolver/router**, not another 50 classifier rules.
