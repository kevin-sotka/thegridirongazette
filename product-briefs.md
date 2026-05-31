# Gridiron Gazette — Product Briefs

**Date:** 2026-05-29
**Target user:** The casual fantasy football manager — plays 1–2 leagues, wants to win without obsessing, can't read 10 articles a week, hates losing because Saturday-night Schefty tweets got missed.
**Author:** SodClaw (drafted for Kevin)

---

## Why now / what the research says

Quick scan of the FF Twitter ecosystem (@AdamSchefter, @RapSheet for news; @SigmundBloom, @mattwaldman, @ihartitz, @AdamLevitan, @JonBales for analysis; @JeneBramel for injuries) and a sweep of the casual-player corner of the market surface a consistent shape: serious players have endless tools (Footballguys, FantasyPros, Fantasy Life, Draft Sharks, FantasyPoints) and the casual player has the official ESPN/Yahoo/Sleeper app. The gap in between — between "the basics" and "the firehose" — is where everyone says they live but nobody actually builds for.

**Five recurring pain points for casual managers:**

1. **Information overload.** Too many sources, contradictory takes, no signal-to-noise. Generic rankings everywhere; nothing tuned to *their* league.
2. **Decision fatigue.** Start/sit and waiver decisions every single week. Casual players want a confident answer, not a 1,200-word column.
3. **Time poverty.** They have 10 minutes on Sunday morning, not three hours on Thursday night.
4. **Late-breaking volatility.** A Sunday-morning inactive can sink a week if they didn't check.
5. **The fun gap.** Big leagues with friends used to live on group-chat trash talk and printed-out power rankings — the modern app experience strips all of that out and just shows numbers.

Each brief below picks a different pain point and a different UX shape. They're deliberately distinct products — but they share the same underlying data spine (league sync + news ingestion), so building one paves the road for the next.

---

## Brief 1 — **The Sunday Morning Brief**
*"A 2-minute Sunday text. Just your team, just what you need."*

### The pitch

A weekly personalized text or email that lands at 9:00am local time on Sunday morning. It says exactly three things about your team: (1) who on your roster is inactive or downgraded, (2) one start/sit call we'd change in your lineup, (3) the single highest-leverage waiver pickup to grab for next week. No links. No tables. Sixty seconds to read.

The Gazette becomes a *concierge*, not a destination. You don't visit it; it visits you.

### Pain points addressed

- Time poverty (60-second read)
- Late-breaking volatility (Sunday-morning send)
- Decision fatigue (one call, not ten)

### User stories

- **As a casual player**, I want a single Sunday-morning message that tells me if anyone on my team got hurt overnight, so I don't lose because I forgot to check inactives.
- **As a casual player**, I want one specific start/sit recommendation for my lineup this week, so I don't have to read three articles to make a 50/50 call.
- **As a casual player**, I want the one waiver pickup most likely to move the needle for *my* roster, so I don't have to scan a top-20 list and figure out which apply to me.
- **As a casual player on Sunday morning**, I want to forward the brief to my buddy with one tap, so I can argue about it in the group chat.
- **As a casual player who lost**, I want a Tuesday "what went wrong + what to do" mini-brief, so the next week feels less random.

### Feature list

- Connect Sleeper / ESPN / Yahoo / NFL.com league with one OAuth step
- Choose delivery channel (SMS, email, or both) and timezone
- Sunday 9am brief (matchday) — inactives + start/sit + waiver
- Tuesday 9am brief (post-mortem) — what changed + waiver targets for the deeper view
- Voice/tone selector (Coach, Beat Reporter, Friend Who Just Knows) — same intel, different vibe
- Group-chat share card (auto-generated image with the brief's headline)

### Technical brief

- **Platform:** Email + SMS. No app. Web onboarding page only.
- **Stack:**
  - Frontend / onboarding: Next.js on Vercel
  - Auth + user data: Supabase (Postgres)
  - League data: Sleeper API (free, public) first; ESPN + Yahoo via existing community SDKs (espn-api, yahoofantasy)
  - News/injury feed: RotoWire RSS + Schefter/Rapaport scraped via X API or RSS bridge; PFF or FantasyPros for projections
  - LLM: Claude Haiku (Anthropic API) for brief drafting — cost-critical at this volume
  - Email: Resend
  - SMS: Twilio
  - Scheduling: Vercel Cron / Inngest
- **Architecture:** Lightweight cron → per-user pipeline (fetch league state → fetch news → prompt Haiku with structured league context → render brief → send via Resend/Twilio). All stateless except user prefs in Supabase. Per-brief cost target < $0.02.
- **Implementation timeline (4 weeks, nights/weekends pace):**
  - **Week 1** — Sleeper-only league sync, onboarding page, Supabase schema
  - **Week 2** — News ingestion + Haiku brief generation, internal preview
  - **Week 3** — Email + SMS delivery, scheduler, voice selector
  - **Week 4** — Closed beta with 5 leagues (recruit from your group chats), polish
- **Stretch (post-MVP):** ESPN + Yahoo support (week 5–6), Tuesday brief (week 7), share cards (week 8).

### Why this one wins

It's the smallest, cheapest, fastest thing you could ship that actually solves the *biggest* casual pain — "I'm busy and I keep losing because of it." It's also the gateway drug: once a user trusts the Sunday text, you can upsell them into Brief 2 or 3.

---

## Brief 2 — **Coach in Your Pocket**
*"Text us anything. We know your league."*

### The pitch

A chat-first AI fantasy coach. You text a number (or open a PWA) and ask plain English questions — "should I start Hall or Pollard?", "is Bijan a sell-high?", "who do I drop for the Steelers DST?" — and you get a confident, league-aware answer in five seconds. The coach knows your roster, your scoring settings, the rest of your league's standings, and the latest news.

This is **Gazette as conversation partner**. No menus, no dashboards, no tabs.

### Pain points addressed

- Information overload (one answer beats ten rankings)
- League-specific context (knows your scoring, your roster, your matchup)
- Decision fatigue (chat *is* the decision)

### User stories

- **As a casual player**, I want to text "Hall or Pollard this week?" and get an answer with one sentence of reasoning, so I can decide in 10 seconds.
- **As a casual player**, I want to ask "anything I should know about my team today?" and get a proactive summary, so I don't have to check three apps.
- **As a casual player**, I want the coach to remember my preferences (I value floor over ceiling, I hate kickers), so I get advice that fits how I play.
- **As a casual player**, I want to ask "who should I target on waivers?" and get a ranked list of *available* players in *my* league, so I don't waste a claim on someone already rostered.
- **As a casual player at 10:55am Sunday**, I want a single "set my lineup" command that proposes a final lineup with reasoning, so I can confirm with one tap.
- **As a curious casual**, I want to ask "explain why X is being dropped everywhere", so I learn while I play.

### Feature list

- League sync (shared with Brief 1)
- Conversational chat UI (web + SMS) backed by an LLM with tool-calling
- Tool calls: `get_roster`, `get_matchup`, `get_player_news`, `get_projections`, `get_available_waivers`, `get_league_standings`
- Memory layer (per-user preferences and prior conversation)
- Proactive nudges (push or SMS): "Heads up — your TE is questionable, here's the swap I'd make"
- "Set my lineup" one-shot command
- Lock during games (the coach goes quiet kickoff → end of MNF so you don't tilt-text it)

### Technical brief

- **Platform:** Mobile-first PWA + SMS gateway. No native app required.
- **Stack:**
  - Frontend: Next.js PWA, Vercel
  - Chat runtime: Vercel AI SDK or LangChain-style tool-calling loop
  - LLM: Claude Sonnet for chat, Haiku for retrieval and summarization (two-tier for cost)
  - League data: same SDKs as Brief 1 (Sleeper, ESPN, Yahoo)
  - Vector store: Pinecone or pgvector for news + depth chart embeddings
  - Session memory: Upstash Redis
  - SMS: Twilio (in/out)
  - Auth + user data: Supabase
- **Architecture:** Chat turn arrives → LLM decides which tools to call → tools hit league API + vector search over news → LLM composes answer with citations → response streamed back. Proactive nudges are a separate cron that runs the same tool set and pushes when a confidence threshold is crossed.
- **Implementation timeline (6–8 weeks):**
  - **Weeks 1–2** — League sync + data pipeline (shared with Brief 1; if Brief 1 already shipped, skip)
  - **Weeks 3–4** — News + projections ingestion, embeddings, retrieval tools
  - **Weeks 5–6** — Chat UI, tool-calling loop, memory
  - **Week 7** — SMS gateway, lock-during-games behavior
  - **Week 8** — Closed beta, prompt tuning, latency tuning

### Why this one wins

This is the **defensible product**. Sunday Morning Brief can be copied by any newsletter team. A truly league-aware coach is hard to fake — it requires the data pipeline, the retrieval layer, the tool-calling loop, and the prompt craft. It's also where the magic moment lives ("how did it know that?"), which is what drives word-of-mouth.

---

## Brief 3 — **League Drama Engine**
*"The Athletic, but for your league."*

### The pitch

Every league gets its own private weekly publication: AI-generated power rankings, a "what just happened" recap of every matchup, weekly awards ("The Bell Cow", "Disaster of the Week", "Couch Vibes"), trash talk targeted at the manager who deserves it, and shareable cards for the group chat. Choose a voice — Hot Takes Sports Talk, Old-Timey Beat Reporter, Wild West Trail Guide — and the whole publication takes on that personality.

This is **Gazette as the diner where the league hangs out**. The product isn't optimization; it's *fun*.

### Pain points addressed

- The fun gap (group-chat trash talk meets editorial quality)
- Engagement decay (mid-season fade in big leagues)
- League-specific context (the only product that knows YOUR league's lore)

### User stories

- **As a commissioner**, I want a weekly recap published automatically every Tuesday morning, so my league has something to react to without me having to write it.
- **As a casual player**, I want to see a power-ranking blurb roasting my opponent before our matchup, so I can paste it into the group chat.
- **As a manager who just lost on a bad coach's-decision Hail Mary**, I want a "Disaster of the Week" award post that I can self-deprecatingly share, so my pain becomes content.
- **As a league member**, I want my own season-long stat page that tracks weird/funny stats (lucky wins, points left on bench, trades won/lost), so there's a season narrative.
- **As a commissioner**, I want to choose the publication's voice and tweak it mid-season, so it matches my league's vibe.
- **As anyone**, I want one-tap share to group chat with a generated image card, so the league lives where it actually lives — iMessage/Discord/WhatsApp.

### Feature list

- League sync (shared)
- Weekly Tuesday-morning publish job
- Generated content modules: Power Rankings (with personality), Matchup Recaps, Weekly Awards, Trade Block Watch, Manager Profiles
- Voice selector (Hot Takes / Beat Reporter / Trail Guide / Diner Owner / Custom)
- Per-manager season-long story pages
- OG image card generator for every post (shareable)
- Optional: end-of-season "yearbook" PDF (revenue hook)

### Technical brief

- **Platform:** Per-league static site (subdomain per league) + shareable image cards. No login required to view; auth only for commissioners to configure.
- **Stack:**
  - Site generator: Next.js (App Router) or Astro for fast static sites; one deployment per league as a tenant route
  - Hosting: Vercel
  - LLM: Claude Sonnet for content generation (longer-form than Brief 1, but lower volume — once per week per league, ~10 pieces per league)
  - Image cards: Satori + Next.js OG image generator
  - League data: same SDKs as Briefs 1 and 2
  - Database: Supabase (league config, voice prefs, manager-name nicknames, weekly recaps cached)
  - Cron: Vercel Cron, Tuesday 9am league timezone
  - Optional revenue: Stripe for paid commissioner tier (custom domain + yearbook PDF)
- **Architecture:** Tuesday cron iterates leagues → for each league, fetches week's results + standings + transactions → constructs prompt with league lore + voice → Claude Sonnet generates each post → static pages regenerated → OG cards rendered → optional Discord/group-chat webhook fires the digest. Heavy on prompt engineering, light on infra.
- **Implementation timeline (5 weeks):**
  - **Week 1** — League sync (shared)
  - **Week 2** — Recap LLM prompts, voice modes, content templates
  - **Week 3** — Static-site template, per-league routing
  - **Week 4** — OG card generator + share flow
  - **Week 5** — Closed beta with 3 leagues, voice tuning

### Why this one wins

It's the only one that taps the **social** layer of fantasy football, which is where casual players actually live. It also has the clearest viral mechanic (shareable cards land in group chats every week), and the clearest paid-tier story (commissioners will pay $5–10/mo to keep their league entertained). It's the lowest-tech moat but the highest emotional moat — once a league makes the Gazette part of their week, switching cost is enormous.

---

## How the three fit together

These aren't competing concepts — they're a product family with a shared spine:

```
              Shared data spine
              -----------------
   League sync  +  News ingestion  +  Per-user prefs
                       │
        ┌──────────────┼──────────────┐
        │              │              │
   Brief 1         Brief 2         Brief 3
   "Concierge"    "Coach"         "Diner"
   (delivery)     (decision)      (community)
```

If you build **Brief 1** first, you get the data pipeline standing and the lowest-cost path to first users. **Brief 2** is the deeper moat. **Brief 3** is the most fun and the most viral. Any one is a viable Gazette V1.

## SodClaw's read

**Brief 1 if** you want a real product live before the 2026 NFL season opens — it's 4 weeks of focused nights/weekends and it sharpens what the Gazette is actually *for* (a delivery vehicle, not another fantasy website). It's the brief that respects your existing "kill-date" philosophy on the project.

**Brief 3 if** you want the Gazette to be a brand people talk about. It's higher-craft, slower to ship, and dependent on prompt voice work — which happens to be your strength. It also matches the Gazette name better than the other two.

**Brief 2 if** you want the most ambitious thing — but its 6–8 week timeline pushes you uncomfortably close to the season opener, and it's the most expensive to run at scale.

If you can only pick one: **Brief 1 to ship, with Brief 3 as the post-season-1 follow-up**. The voice work in Brief 3 will be much sharper after a season of seeing what casual players actually want.

---

*Drafted 2026-05-29 from X.com pain-point research, casual-player target persona. Open to a deeper dive on any one of these before we commit.*
