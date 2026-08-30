---
name: redaction
description: Rewrites a text to Alexandre's writing rules. Use when reviewing or cleaning any text before it ships - documentation, a skill, a guideline, a message, site copy, a commit message.
---

# Redaction

Rewrite the text to the rules below. The meaning does not change. The length almost
always drops.

**Target**: the text in `$ARGUMENTS`, or the last substantial text in the conversation.
French and English alike.

## Typography

- No em dash and no en dash. Use a colon, a comma, parentheses, or a new sentence.
- No middle dot as a separator.

## Sentences

- Short and simple. An advanced construction blurs the reading, it does not elevate it.
- One idea per sentence.
- Bullets whenever the content is a list. Prose only when it carries an argument.

## Say it once

- Cut the reinforcement sentence. Stating a rule then restating it in negative or in
  stronger form adds nothing.
- One strong example beats three average ones.

## Order

- The rule first, the circumstance after. Never open with a condition.
- Lead with the result, not with the approach that produced it.

## Substance

- Cut anything nobody asked for: an invented cadence, an invented justification.
- Cut slogans and aphorisms. "Pragmatic, not dogmatic" says nothing.
- Explain a term the first time it appears, or do not use it.
- A rule is written as a rule. Hedged into a suggestion, it gets ignored.

## In a shared document

- No project names, no company names, no ticket references, code examples included.
- No project state. "X has migrated" is true in one repo and a lie in its neighbours.
- Cut what the tool already documents, and what lives elsewhere in the repo. A copy
  drifts, and then it lies.

## Two tests before returning the text

- Does each rule deserve to exist? Polishing a rule that should not be there is wasted
  work.
- Would the reader have to reformulate a sentence to understand it? Then their
  reformulation is the version to write.

## Output

Return the rewritten text, then one or two lines on what was cut, by category. If the
text is already clean, say so and change nothing.
