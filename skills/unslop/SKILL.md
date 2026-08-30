---
name: unslop
description: Strip the AI writing tells out of a text, in French and in English. Triggers - unslop, désloppe, "it sounds like AI", relis ce texte, humanise.
---

# Unslop

Rewrite a text to remove the markers of machine writing and give it a human voice.
Applies to anything an agent produced or co-produced: a chat answer to be reused, a
note for the PM, a PR description, a README, documentation, site copy. The meaning
does not change, the length almost always drops.

## Target

The text the user supplies (`$ARGUMENTS`), or failing that the last substantial text
produced in the conversation. Detect the language and apply the matching section, or
both when the text is mixed.

## Both languages

- **Chatbot politeness**: "I hope this helps", "Feel free to", « J'espère que cela vous
  aidera », « N'hésitez pas à » → delete.
- **Systematic bold**: keep bold for what should catch the eye on a scan, not to
  punctuate every sentence.
- **Lists of three**: three examples, three adjectives, three benefits, everywhere.
  Break the rhythm: one strong example beats three average ones.
- **Empty emphasis**: replace the impression with the mechanism. "improves performance
  considerably" → the number, or the cause.
- **Clustered hedging**: "perhaps", "it would seem", "to some extent" stacked together
  → decide, or say plainly that you don't know.
- **Decorative headers**: no heading for two sentences, no section emoji.
- **Final self-audit**: re-read and ask "what still sounds like a machine here?", fix
  it, then stop. An over-cleaned text rings as false as a slopped one.

## French

- **Em dash (—): forbidden.** Replace with a colon, a comma, or a new sentence. House
  rule, not negotiable.
- **Middle dot (·): forbidden** in labels and separators.
- « **Il est important de noter que** », « **il convient de** », « **force est de
  constater** » → delete, say the thing.
- « **En tant que** [rôle], ... » as an opening → delete.
- « **n'hésitez pas à** » → delete, or use the direct imperative.
- « **De plus** », « **Par ailleurs** », « **En outre** » repeatedly opening sentences →
  vary or cut: the sentence usually stands without a connector.
- « **cruciales** », « **essentielles** », « **incontournables** » → a fact instead of
  an adjective.
- Vouvoiement and tutoiement: follow the context, never mix the two.

## English

- Signature vocabulary: "pivotal", "testament to", "landscape", "tapestry", "delve",
  "seamless", "robust", "leverage" as a verb → plain words.
- "serves as", "boasts", "stands as" → "is".
- Hollow "-ing" constructions: "highlighting", "fostering", "showcasing" trailing a
  sentence → cut, or say what actually happens.
- "It's not just X, it's Y" → say Y.
- Em dashes in series → normal punctuation.
- Title Case Headings → sentence case.

## Output

Return the rewritten text, then one or two lines on what was removed, by category, not
as an inventory. If the text is already clean, say so and change nothing: the goal is
accuracy, not rewriting for its own sake.
