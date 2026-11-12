# Model and effort: the measures behind §9

`high` everywhere Opus runs: `xhigh` adds 2.4 points of index (56.0 against 53.6) for 1.9
times the cost per task, and `max` 1.6 more for 1.7 times again.

Measured by Artificial Analysis on 2026-10-17. The figures sit in the page source of
`artificialanalysis.ai/models/<slug>` (`claude-opus-5-5-high`…): `intelligenceIndex`, and
`intelligenceIndexCostPerTask.cost.total` in US dollars. Read them there, never off a chart:
a reading by eye was wrong by five points on Opus `low`.

| Model | Effort | Index | Cost per task | Output tokens per task |
|---|---|---|---|---|
| Opus 5.5 | max | 57.6 | 5.98 USD | 84 K |
| Opus 5.5 | xhigh | 56.0 | 3.46 USD | 40 K |
| Opus 5.5 | high | 53.6 | 1.82 USD | 18 K |
| Opus 5.5 | medium | 51.2 | 1.34 USD | 12 K |
| Opus 5.5 | low | 42.3 | 0.55 USD | 3 K |
| Sonnet 5.5 | max | 56.0 | 5.46 USD | 147 K |
| Sonnet 5.5 | xhigh | 51.9 | 2.01 USD | 48 K |
| Sonnet 5.5 | high | 46.8 | 0.88 USD | 19 K |
| Sonnet 5.5 | medium | 40.8 | 0.48 USD | 8 K |
| Sonnet 5.5 | low | 35.9 | 0.35 USD | 5 K |
| Haiku 5.5 | max | 43.4 | 0.21 USD | 129 K |
| Haiku 5.5 | xhigh | 41.2 | 0.12 USD | 66 K |
| Fable 5.1 | max | 53.4 | 7.63 USD | 47 K |
| Fable 5.1 | high | 51.2 | 3.91 USD | 19 K |

- Fable 5.1 sits under Opus 5.5 at every cost (Opus `high` scores more for half the
  price of Fable `high`): no role.
- At equal effort Sonnet spends about as many tokens as Opus for half the token price,
  but scores less: Sonnet `xhigh` (51.9, 2.01 USD) is under Opus `high` (53.6, 1.82 USD).
- For the mechanical lots, four candidates stand close on the index: Opus `low` (42.3,
  0.55 USD), Sonnet `medium` (40.8, 0.48 USD), Haiku `max` (43.4, 0.21 USD) and Sonnet `high`
  (46.8, 0.88 USD). The index is not an agentic coding measure: the fix session after this
  measure runs mechanical lots on Opus `low`, Sonnet `medium` and Haiku `max`, compares
  tokens, duration and what the review finds behind each, and writes the outcome in the
  role table.

- Opus `medium` loses 2.4 more points (51.2) for 26 % less than `high`, a worse trade than
  `xhigh` to `high` (2.4 points for 47 %). Measure it before moving a role there: the next
  delta reads one zone twice, at `high` and at `medium`, and compares findings and false
  positives; the next logic fix lots alternate `high` and `medium`, and the delta after
  them counts what it finds behind each. Write the outcome in the role table.

Measured on one project, 2026-10-17, three mechanical lots of comparable work, then a delta
reading their files at equal depth (Opus `high`):

| Lot | Fixed | Tokens | Duration | Introduced, per 100 files touched |
|---|---|---|---|---|
| Opus `low` | 31 of 35 | 187 K | 20 min | 5, all Basse |
| Sonnet `medium` | 53 of 60 | 280 K | 44 min | about 10, one a wrong total |
| Haiku `max` | 27 of 34 | 663 K, plus four review agents | 85 min | 6 after four review rounds that caught a broken test and a display regression; it also printed `.env.local` against its brief |

Haiku gets no role. And one reading of a zone misses a third to a half of what is there: the
same 33 files read at Opus `high` and `medium` gave 10 and 7 findings, only 3 in common, 14
in all. The delicate zones are worth two readings at different efforts rather than one at a
higher effort.

Re-read these figures when a new model ships.
