# Writing

Applies to everything written: prose, site content, UI labels, validation messages,
code comments, commit messages, documentation, and replies in conversation. In French
as in English.

## Which language

Two decisions, not one.

- **The content is in the project's language**, chosen at the start and held everywhere:
  interface labels, validation and error messages, the text a visitor reads, and the
  declarations that announce it (`<html lang>`, `openGraph.locale`).
- **The code is in English**, always, whatever the project's language: classes,
  functions, variables, properties, file names, routes, serialization groups, URL
  parameters, database columns.
- **A domain term naming a French legal, administrative or agricultural reality stays in
  French**, unchanged. A `Fonciere`, the `Aides`, the `statuts` of a company: translating
  them loses what they name, since `RealEstateCompany` drops the legal vehicle and
  `Subsidies` drops the specific scheme. Keep the French word whole, never half of it.
- **Never French word order in English words.** `$datePayment` is the real defect, worse
  than a French name would be: it reads as English and is not. It is `$paymentDate`.
- **The comments are in French** on a French project. A comment carries nothing but
  nuance, and the nuance is better in the language it was thought in; the identifiers
  around it already carry the English.

Measured on two projects: 4 of 468 entity properties are not in English, and
each of the three real ones is a French legal term. The rule describes what the code
already does.

## Typography

- No dash to set off a phrase inside a sentence, whatever its length: an apposition or an
  aside takes commas, an explanation a colon, a digression parentheses. No em dash (`—`)
  at all, and no hyphen standing in for a dash.
- The en dash (`–`) is allowed where it joins rather than interrupts: between a page and
  the site in a title (« Annonces – Le site »), and in a range (« 10–20 ha »).
- No middle dot (`·`) as a separator.
- Bold only what should catch a scanning eye, never to punctuate a paragraph.

## Substance

- Never assert what you cannot source. An invented number, frequency or constraint reads
  exactly like a real one.
- **What a tool offers is read, not remembered.** These projects run versions released
  after training: the installed version, the current documentation, then a grep of the
  codebase to see whether the pattern is already in use. Answering "the framework has no
  such thing" from memory has already been wrong about a feature that was both shipped
  and used everywhere in the code.
- Where an impression would stand, give the number or the cause; a number that replaces
  no impression stays out. "Considerably faster" says nothing, "ten times faster" says
  something.
- Explain a term the reader cannot know, such as a name coined during the work; a common
  technical term or acronym stays as it is. The explanation keeps the exact name beside it
  (a status code, a field, a class), never a paraphrase in its place.
- Keep a technical detail when it changes a decision or shortens the explanation.
- A report for a reader outside the code (a manager, a partner) gives the current state:
  no PR number, no date of the work. It keeps the technical reason behind each choice,
  which is what that reader weighs.

## Structure

- A sentence that lines up several items, each with its own detail, becomes a short
  lead-in followed by a bulleted list.
- Every sentence has a verb and says one whole thing.
- Sections only when the content has several distinct parts.

## Say it once

- Cut a sentence that restates something the text already said, whether in negative, in
  stronger form or as a summary.
