#!/bin/bash
set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <iterations>"
  exit 1
fi

touch docs-progress.txt

for ((i=1; i<=$1; i++)); do
  echo "Iteration $i"
  echo "--------------------------------"
  result=$(claude --model "claude-opus-4-6" --permission-mode acceptEdits -p "@CLAUDE.md @AGENTS.md @docs/en/* @docs-progress.txt @doc.md \
You are a Senior Full Stack Architect documenting a Laravel + React + Inertia.js multi-tenant SaaS project. \
Your job is to produce or improve documentation files per iteration in docs/. \
\
LANGUAGE STRUCTURE — every feature must be documented in BOTH languages: \
- docs/en/{role}/{domain}/feature.md — English \
- docs/it/{role}/{domain}/feature.md — Italian \
Write both versions in a single iteration. Prose and headings must be in the target language. \
Code snippets, Mermaid diagrams, file paths, method names, and class names stay in English always. \
\
DIRECTORY STRUCTURE — docs are organized by target audience: \
- docs/{lang}/dev/ — technical documentation for developers: controllers, models, services, jobs, middleware, queues, events, infrastructure, internal tooling. Audience: engineers. \
- docs/{lang}/po/ — product documentation for product owners: feature flows, business rules, user journeys, state transitions, edge cases. No code. Audience: product managers. \
- docs/{lang}/sales/ — sales documentation: value propositions, customer benefits, differentiators. ONLY for customer-facing features (booking, notifications, calendar, contacts, integrations). Skip for infrastructure, internal tooling. Audience: sales team. \
\
DOCUMENTATION FORMAT — adapt format to the directory. Be thorough and detailed: \
\
docs/{lang}/dev/: \
# Title \
## Overview — what the feature does, why it exists, which problem it solves \
## Architecture — high-level design decisions, patterns used (e.g. job chaining, observer, strategy) \
## How It Works — step-by-step technical walkthrough of the request/process lifecycle \
## Key Components — table with columns: File, Class/Method, Responsibility \
## Database Schema — relevant tables, columns, indexes, foreign keys (if applicable) \
## Configuration — env vars, config keys, feature flags that affect this feature \
## Error Handling — what can fail, how errors are caught, retries, fallbacks \
## Mermaid diagram(s) — required for any multi-step flow or state machine \
\
docs/{lang}/po/: \
# Title \
## What It Does — plain-language description, no code or file paths \
## User Journey — numbered steps from the user perspective \
## Business Rules — bullet list of rules enforced by the system \
## Edge Cases — what happens in unusual/error scenarios \
## Permissions & Access — who can use this feature and under what conditions \
\
docs/{lang}/sales/: \
# Title \
## What Clients Get — 2-3 sentence summary of the value \
## Key Benefits — 4-6 bullet points, benefit-driven language, no technical jargon \
100-200 words total. \
\
MERMAID DIAGRAMS (docs/{lang}/dev/ only) — use Mermaid.js diagrams to visualize flows: \
- State machines: stateDiagram-v2 (e.g. event lifecycle, booking status) \
- Data/request flows: flowchart TD (e.g. booking submission, notification dispatch) \
- Sequence diagrams: sequenceDiagram (e.g. API calls, webhook processing) \
Include at least one diagram per dev doc when the feature involves a multi-step flow or state transitions. \
Wrap diagrams in fenced code blocks: \`\`\`mermaid ... \`\`\` \
\
RULES: \
- Do not skip complex logic — document it thoroughly in docs/{lang}/dev/ \
- Read the actual source code before writing; do not guess or summarize superficially \
- One feature per iteration, documented in both languages \
- A single feature may produce up to 6 files: en/dev + en/po + en/sales + it/dev + it/po + it/sales \
- Create only the files appropriate for the feature type (skip sales doc for infrastructure) \
- Place docs in the correct subdirectory: docs/en/dev/calendar/, docs/it/po/booking/, etc. \
\
HOW TO PICK WHAT TO DOCUMENT: \
1. If plans/docs-prd.json exists, read it and find the first task with passes: false (respecting depends_on order). \
   Check docs-progress.txt: if the last entry already attempted this exact task, skip it and pick the next eligible one. Work only on that task. \
2. If no docs-prd.json exists, first scan the codebase (controllers, models, services, commands, jobs) and compare against existing docs in docs/en/. \
   If you find an undocumented feature, document it. \
3. If all features are already documented, pick an existing doc and expand/deepen it: add more technical detail, improve accuracy, enrich Mermaid diagrams, cover additional edge cases or configuration. \
   Check docs-progress.txt to avoid working on the same doc as the previous iteration. \
4. When reading an existing doc for improvement, always read the actual source code it covers first to find gaps and inaccuracies. Update both language versions. \
\
IMPORTANT — ALL commands must run inside Docker: \
- Artisan: docker compose -f deploy/docker-compose.yml exec app php artisan <command> --no-interaction \
- Any PHP execution: docker compose -f deploy/docker-compose.yml exec app php <command> \
\
AFTER WRITING/UPDATING THE DOC: \
1. If docs-prd.json exists, mark the completed task by setting passes: true. Do NOT add, remove, or modify other tasks. \
2. Publish all created/updated files to Outline following the instructions in docs/doc.md exactly. \
3. Append your progress to docs-progress.txt with: \
   - Which docs were created/updated (both languages) \
   - What was documented \
   - What areas still need coverage (feed-forward for next iteration) \
4. Make a git commit: docs: document {feature-name} (en + it) \
\
ONLY WORK ON A SINGLE FEATURE PER ITERATION (which may produce up to 6 files: en + it × dev/po/sales). \
If docs-prd.json exists and all tasks have passes: true, output <promise>COMPLETE</promise>. \
If no docs-prd.json exists, only output <promise>COMPLETE</promise> if you find absolutely nothing left to document or improve across the entire codebase. \
")

  echo "$result"

  if [[ "$result" == *"<promise>COMPLETE</promise>"* ]]; then
    echo "Documentation complete, exiting."
    echo "Documentation complete after $i iterations"
    exit 0
  fi
done
