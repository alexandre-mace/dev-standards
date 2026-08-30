# How to present a design system

How to present a design system, not what to put in it. What changes between products is how the
sections are filled, never their list or their order: a dev arriving on any of them finds the same
headings in the same place.

## 1. The principle: document provenance, not values

A catalogue listing "here are our colours, here are our buttons" answers "what exists". It does not
answer "which one do I take, and what am I allowed to do with it". And on a project consuming an
upstream library (shadcn, Radix, Base UI), it duplicates documentation already written elsewhere,
better, and kept up to date on its own.

Hence the central rule: **every brick carries a provenance**, and that decides how much gets written.

| Provenance | Meaning | What gets documented |
| ---------- | ------- | -------------------- |
| `upstream` | taken as-is from the library | one line in the inventory, a link to the official docs. Nothing more. |
| `variant` | upstream, plus in-house variants | **only the in-house variants**. The rest points upstream. |
| `custom` | invented here | the full card. |

Writing cost becomes proportional to what was actually invented: a product consuming 40 upstream
components writes 40 lines and 3 cards, not 40 cards. On a fully in-house system everything is
`custom` and the format falls back to a classic catalogue.

### What belongs in the design system, and what does not

A design system documents **reusable bricks**, not features. An application form or a simulator
belongs to its feature. Without an explicit criterion the boundary gets decided by accident: a
reusable brick lands in the folder of the first feature that needed it, and never enters the catalogue.

The criterion: **a brick used by at least two features, or written to be, belongs to the design
system** and must leave its feature folder.

The upstream library folder (`ui/` for shadcn) stays reserved for what comes from it, its variants,
and what has its shape. An in-house cross-cutting component goes to a separate shared folder. Without
that separation you can no longer tell what is ours from what is upstream, which is the whole question
this document answers.

The criterion is checkable mechanically: resolve the imports, count the areas consuming each
component, and list those crossing more than one area while living outside the shared folder. Those
are the promotion candidates. Run it periodically, otherwise the catalogue describes a perimeter that
is no longer the right one.

Two cases to exclude explicitly, or the list gets polluted: **infrastructure** (providers, application
contexts), which is cross-cutting without being an interface brick, and feature components shared
between two neighbouring areas out of functional proximity rather than genericity.

### Two environments need an adaptation

On a **no-code** site, values are read through the API, variables and classes, never by hand, and the
living page is a page of the site built with the real classes.

When **the upstream is our own kit**, provenance has two levels (stock, kit, project) and the
provenance documentation already lives in the kit: item descriptions, deviations from stock, and the
registry site which is already the living page. A consumer therefore documents only its identity line
and its custom bricks. Applying the full format to a consumer duplicates the kit, worse.

## 2. Two artefacts, never one

**`DESIGN-SYSTEM.md`**: the written description. Versioned, diffable, readable in code review. It is
the reference you can hold someone to.

**A living page** served by the application itself. It consumes the real CSS and the real production
components, so it cannot lie. It is the visual proof.

Both follow the plan in section 3, same headings in the same order. No screenshots in the `.md`: a
screenshot goes stale silently, a link to the living page does not.

## 3. The plan

### 0. Identity

One sentence, the most important in the document: what this design system is and how it relates to
upstream.

> "This DS is plain shadcn, themed per tool."
> "This DS is an in-house `ui-*` system laid over the Webflow variables."

The reader immediately knows what to expect, and above all what not to look for.

If a brand reference lives elsewhere (the marketing site, a brandbook), list the **deliberate
divergences** here: what this product does differently and why it is a choice. That is what lets the
format of brandbooks be uniform without making the identities uniform.

### 1. Axes of variation

Most systems vary along one or more axes: a theme per functional domain, an editorial mode,
light/dark, a breakpoint. For each axis, state:

- the possible values;
- what changes when you switch;
- **what does not change**, which is what guarantees switching breaks nothing.

A product without an axis writes "none" and moves on.

### 2. Foundations

Colour, typography, spacing, radius, icons, shadows, motion. With provenance as a column, to tell at
a glance what is the library default from what we laid on top.

**The living page shows every token**: swatch, name, role, and the value **read from the rendered CSS
at display time**, not copied. A copied token table drifts; a table that reads the CSS cannot lie.
Document the measured contrast of the pairs actually used.

### 3. Components

First the **inventory**: a table, one row per component, with its provenance and its upstream link.
It is the map of the territory, and on an upstream-heavy project it is often 90% of the useful content.

Then the **cards**, conditional on provenance (see section 4).

### 4. Patterns

Recurring assemblies, the ones meant to be reproduced identically: form, empty state, error state,
loading, pagination, destructive confirmation.

### 5. Recurrences

What repeats in the product **without being a design system brick**. It is the missing link between
the catalogue and feature code, and often the section that pays off most.

Not to be confused with the previous one: a **pattern** is an assembly you want reproduced, a
**recurrence** is an assembly that _is_ reproduced, and about which you have to decide whether it
should be.

For each archetype: where it appears, what it weighs, and a verdict.

| Verdict | When |
| ------- | ---- |
| **generalise** | same structure written several times, the differences are accidental |
| **watch** | same appearance, different domain objects: merging would make a props bag |
| **one precise fix** | an isolated case, fixable in one go |

The verdict matters more than the list. "Watch" is a real answer: five cards that look alike but carry
different data and behaviour produce, once merged, a component with fifteen optional props, less
readable than the five versions. What gets extracted then is the skeleton, not the component.

Two ways to find recurrences without guessing: **identical file names in different folders**, the most
reliable signal, and **needs covered by an upstream component that was never installed**, such as an
empty state rolled by hand when the library ships one.

### 6. Writing

Tone, register, button labels, capitalisation, date and number formats. A DS that says nothing about
text leaves every dev inventing their own.

### 7. What we do not do

Anti-patterns and deprecated elements, with the replacement next to each. Usually the most useful
section in the document, because it is the only one that actively prevents a mistake. A legacy token
left unmentioned will be reused.

## 4. The component card template

By provenance:

**`upstream`**: name, one sentence of usage, link to the official docs. Done.

**`variant`**: name, one sentence, upstream link, then **only** a preview of the in-house variants,
each with its reason to exist.

**`custom`**, the full template:

1. Preview (real rendering, with variant pickers if the component has any)
2. Anatomy (the named parts)
3. Variants
4. States: default, hover, focus, active, disabled, loading, error
5. Sizes
6. When to use it, when to take something else
7. Accessibility: keyboard, ARIA, contrast
8. Copyable code

The order is fixed. A section without content is removed, not moved.

## 5. The living page

A minimal Storybook, without the dependency. Three requirements:

- **It consumes the real production components**, not a copy nor a hand-styled fixture.
- **Every entry shows its provenance**, next to the title.
- **Declared axes produce pickers**, so the preview can be manipulated.

Storybook itself is only justified when the front end is a standalone SPA: as soon as part of the
design system lives in server templates and CSS classes, it sees half of it and forces a second build.

## 6. A machine-readable source

The inventory and its provenances are better off in a structured file at the root, consumed both by
the `.md` and by the living page, so the two cannot diverge. On a shadcn project, `npx shadcn info`
already lists the installed components: all that is left is to add the provenance.

Two adaptations. On a **no-code** site there is no repo to hold the file: it is generated from the
variables and classes through the API. When **the upstream is our own kit**, the kit's `registry.json`
already plays that role, with its typed and described items: do not create a second one.

## 7. Keeping it true over time

- **Date the document** and say how the values were obtained: read through the API, read from the CSS,
  measured. A value reconstructed from memory is a wrong value waiting to happen.
- **Never copy an upstream value**, reference it.
- **Every new colour passes a contrast check** before entering, and the ratio is recorded.
- **The living page settles it** when it disagrees with the `.md`: it renders the real code.
- **The trigger is the PR, not the calendar.** Any PR adding a component, creating a variant or
  customising a brick updates the inventory in the same PR. The mechanical check from section 1 is the
  safety net, to run at the next `/gap-code`.
- **The inventory is per product and it rules.** Documents shared between products never carry a local
  inventory: they keep the procedures and the upstream facts, and point here. Two inventories of the
  same fact always diverge.
