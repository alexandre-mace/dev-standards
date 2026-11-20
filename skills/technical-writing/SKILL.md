---
name: technical-writing
description: >-
  Rewrites technical writing and the text that lives next to code into Alexandre's own
  voice: documentation, guidelines, skills, the reports the other skills produce, commit
  messages, code comments, a technical note to a colleague. Use when such a text is about to
  be committed or sent, or when asked to réécrire or clean one up. Not for French prose meant
  for publication (a site page, a newsletter, a public post) nor for product or marketing
  copy: that goes to a proofreading skill for publication. `--chat` drafts or rewrites a
  message to a colleague (Slack, WhatsApp, a ticket comment), and is the mode to use
  whenever Alexandre asks for a message for someone, typed or not.
argument-hint: "[--chat] <text>"
---

# Technical writing

$ARGUMENTS

Rewrite the text to the rules below. The meaning does not change. French and English
alike.

**Scope**: technical writing and the text next to code, the ones listed in the description.
French prose for publication (a site page, a newsletter, an article, a public post) goes
to a proofreading skill for publication when one is installed, which checks spelling,
grammar, the tu or vous register, internal consistency and the tics of generated text;
asked for both, run that one. When the arguments start with `--chat`, the text is a message between colleagues:
apply the rules below, then the **Chat register** section on top of them.

## The shared rules

`agent/writing.md` applies in full: typography, substance, structure, say it once. The
rules below add to it.

## Sentences

- Bullets whenever the content is a list. Prose only when it carries an argument.
- A fact the reader has to act on gets its own clause, with a verb. Tacked onto the
  previous sentence as an apposition, it reads as a spec line: "the calculation becomes
  amount x index, one cell to update in July every year."
- Cut a list of three when one example carries it.

## Order

- The point first, the condition after.
- Lead with the result, not with the approach that produced it.

## No filler

- Cut a phrase that adds no information: a slogan like "pragmatic, not dogmatic", a
  filler like "it is important to note that".

## Do not cut

- What the reader asked for.
- An error, a command, a quoted source. Reproduce it exactly and at full length.
- In a message to a person, what says how sure the author is: "maybe", "to be
  discussed?", "you had probably seen it". That is information about the author, not
  filler.
- Stop once no rule applies any more. Rewriting past that point only flattens the voice.

## Chat register

Only with `--chat`. The reader is a colleague reading between two other messages, and
the text has to sound typed, not drafted.

- No greeting and no sign-off: the first sentence is already the subject. "Hello, pour
  l'export :" becomes "L'export :".
- Write the way the thread is written, and use the usual French chat shortcuts freely,
  as a colleague would type them ("pb", "qqch", "10j", "+"), and spoken French too ("ça
  touche à aucun montant").
- An arrow for a consequence: "match corrigé -> lettré dans la minute".
- Short paragraphs, one idea each. No heading, no bold.
- Shaped for where it is pasted. Slack and a Notion page render pasted markdown, lists
  and quotes included. A Notion comment keeps bold and inline code but never a pasted
  list: one short line per point there. Never a table.
- First person singular when the author did the work.
- At most one emoji, at the end, and only when the tone of the thread allows it.
- The substance rules still hold in full: a casual tone never licenses an invented
  number or a "rarely" nobody measured.

### Drafting from the work

- Every fact is checked against its source (git, the production data, the ticket) before
  it goes in.
- A decision taken without the reader is told with its reason, even an obvious one: they
  may not have thought of it.
- Left out: a fix made along the way that the request did not cover, an internal detail,
  what the reader already wrote.
- Neither `tu`, `vous` nor `on`, which lumps the author and Claude together: `il` ("il
  faut valider…", "il reste à brancher…") or a turn without a pronoun ("à tester sur la
  fiche ferme").
- The reader is described by role under « Interlocuteurs » in the project's `AGENTS.md`.

## Two tests before returning the text

- Cut before polishing.
- If the reader would reformulate a sentence to understand it, their reformulation is
  the version to write.

## Output

Return the rewritten text, then name the rules that did the cutting. If the text is
already clean, say so and change nothing.
