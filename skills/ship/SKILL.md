---
name: ship
description: Carries a ticket to production when /ticket's definition of done holds - the user's word "ship" is the go /deploy waits for, given in advance. Triggers - "/ship <ticket>", or the user saying "ship" during a ticket. Never inferred - without the word, /ticket stops at UAT.
---

# Ship

Run `/ticket` on `$ARGUMENTS`, or on the ticket in hand. Where it would run `/preprod` and
stop for the UAT, run `/deploy` instead, when all of these hold:

- `/ticket`'s definition of done holds with no check declared unreachable: a surface
  `/live-test` could not reach is no confidence;
- none of `/ticket`'s reasons for a change to stop being minor applies (money, permissions,
  data, schema, dependency, shared component), unless the ticket asks for it in so many words;
- neither the ticket nor the repository's `AGENTS.md` asks for a human UAT.

Otherwise stop at UAT as `/ticket` does, and say which condition held you back.

The word covers this ticket only, and is never inferred from urgency or tone. A line in a
repository's `AGENTS.md` saying its tickets ship by default grants it there.

After the deploy, a new Sentry issue whose trace runs through the diff gets its revert
commit through `/deploy` at once, without asking (`/deploy` §4).
