---
name: check-implementation
description: Verify that recent code changes follow the latest official documentation and best practices of the technologies used. Use after writing code to ensure the implementation aligns with current recommendations.
---

# Check Implementation Against Official Docs

Verify that the code just written or modified follows the latest official documentation and best practices.

## Process

### 1. Identify what to check

- Look at the recent changes in the conversation (code written, modified, or discussed)
- If no recent changes, ask the user what code they want verified

### 2. Identify the technologies involved

For each piece of code, identify:
- The framework/library (e.g., Symfony, React, Next.js, Doctrine, Tailwind, etc.)
- The specific feature used (e.g., form validation, routing, hooks, etc.)
- The current version used in the project (check `composer.json`, `package.json`, etc.)

### 3. Search the official documentation

For each technology/feature identified:
- Use `WebSearch` to find the **official documentation** for the specific feature in the **latest stable version**
- Use `WebFetch` to read the relevant documentation page
- Focus on:
  - Recommended patterns and approaches
  - Deprecated features or methods
  - New alternatives introduced in recent versions
  - Security best practices

### 4. Compare and report

For each piece of code checked, report:

**If the implementation follows best practices:**
- Confirm it aligns with the official docs
- Mention the doc page consulted

**If improvements are found:**
- Explain what the docs recommend differently
- Show the recommended approach with a code example
- Indicate if the current approach is deprecated, suboptimal, or just an alternative
- Rate the severity: `deprecated` (must fix), `improvement` (should fix), `alternative` (informational)

**If a newer/better API exists:**
- Show what's available in the version used by the project
- Explain the benefits of switching

### 5. Format the output

Use this format for the report:

```
## Check Implementation Report

### [Technology] - [Feature]
- **Version used**: X.Y
- **Status**: OK | Improvement found | Deprecated pattern
- **Details**: ...
- **Doc reference**: [link]
```

## Important rules

- Always check the **official** documentation, not blog posts or tutorials
- Always verify against the **version actually used** in the project, not just the latest
- If the project uses an older version, note what's available in their version AND what the latest version offers
- Be specific: link to the exact doc page, not just the homepage
- Don't suggest changes just for style — only flag things that the official docs explicitly recommend differently
- If multiple valid approaches exist in the docs, say so and explain trade-offs
