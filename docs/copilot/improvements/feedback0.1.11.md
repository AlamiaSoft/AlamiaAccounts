Yes — I think I know what you're remembering now. The strongest match I found is LLMRouter by UIUC, particularly its multi-round routing work.

LLMRouter — ulab-uiuc/LLMRouter

It explicitly supports multi-round conversations, including a pre-trained Router-R1 for multi-turn conversations, rather than RouteLLM's original primarily single-turn quality/cost routing.

But there's another repo that is even more interesting given the specific problems we're seeing: ai-assistant-framework. It has explicit short-term memory analysis, entity resolution, alias handling, query rewriting, and knowledge-graph-based contextual expansion.

I suspect your remembered repo is one of these two
Repo	What it addresses	Match to our problem
LLMRouter / Router-R1	Multi-turn conversational routing	🟢 Context across turns
ai-assistant-framework	Memory + entity resolution + contextual retrieval	🟢 she, that payment, aliases, context