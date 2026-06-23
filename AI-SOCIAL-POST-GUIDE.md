# Big Binary Trainings - AI Social Post Generation Guide

> **Purpose of this file.** This is a self-contained instruction set for an AI
> (Claude, GPT, an image-gen pipeline, or a human designer) to generate
> **on-brand Instagram and LinkedIn posts** for Big Binary Trainings (BBT).
> Feed this whole file in as context/system prompt. Follow it literally. It
> encodes two things: (1) the **BBT visual identity** (the same design language
> as the website and `global.css`), and (2) the **carousel/post design rules**
> we follow (Z-pattern, 60-30-10, minimalism, Hook→Points→CTA, 2026 sizing).

---

## 0. How to use this guide (for the AI)

When asked to "make a post about X":

1. **Pick a format** → carousel, single image, or LinkedIn text+image (§2).
2. **Lock the canvas size** for that platform (§3).
3. **Write the copy first** using the slide structure Hook → Points → CTA (§6),
   in the BBT voice (§7).
4. **Lay it out** obeying the non-negotiable design rules (§5) and the
   60-30-10 color split (§4).
5. **Output** in the requested form: either a slide-by-slide spec (§8), an
   HTML/CSS render that reuses BBT tokens, or an image-gen prompt (§9).

Never invent new brand colors or fonts. Never center-align body text. Always
end a carousel with a CTA slide.

---

## 1. Brand essence (the "why" behind every choice)

| Attribute | BBT is… |
|-----------|---------|
| **Who** | Pakistan's premium IT flagship training ecosystem. |
| **Promise** | "More than courses - a complete career-growth ecosystem." |
| **Signature model** | Trainee → Internee → Shadow → Expert (4 phases). |
| **Personality** | Senior, technical, confident, calm. Engineering-grade, not hype. |
| **Feeling a post should give** | "These people are serious and know their craft." |

**Tone in one line:** *Premium and technical, never loud or salesy.* Think a
well-designed developer tool's landing page - not a discount coupon.

---

## 2. Post formats we produce

1. **Instagram carousel** (primary) - 3–9 slides, "DON'T / DO" or
   "Hook → 3 points → CTA" structure. Highest reach; this is the default.
2. **Instagram single image** - one strong statement + logo. For announcements
   (new cohort, event, result).
3. **LinkedIn carousel (PDF/document)** - same content, slightly more formal
   copy, denser allowed.
4. **LinkedIn single image + caption** - thought-leadership; the image carries a
   headline, the caption carries the depth.

---

## 3. Canvas sizes (USE THE 2026 SIZES - never the old squares)

> The reference decks are explicit: **don't use old 1080×1080 / 1080×1350.**
> Design tall, for the 2026 feed.

| Platform / format | Pixel size | Aspect | Notes |
|-------------------|-----------|--------|-------|
| **Instagram carousel / portrait** | **1080 × 1440** | 3:4 | ✅ Primary. The new tall feed standard. |
| Instagram full-bleed portrait | 1080 × 1350 | 4:5 | Acceptable fallback only. |
| Instagram single (square) | 1080 × 1080 | 1:1 | ❌ Avoid unless explicitly requested. |
| Instagram Story / Reel cover | 1080 × 1920 | 9:16 | For stories/reels only. |
| LinkedIn carousel (document) | 1080 × 1350 | 4:5 | Portrait reads best in-feed. |
| LinkedIn single image | 1200 × 1200 or 1200 × 1500 | 1:1 / 4:5 | Either works. |

**Safe margins:** keep all text and key graphics within a **90px inset** on
every edge (matches the reference's "180px top / 50px sides" safe-zone idea,
scaled). Nothing important touches the edge - IG UI overlaps corners.

**Thumbnail rule:** the headline must be **legible at thumbnail size** (≈150px
wide in the grid). If you'd have to squint at 1/7 scale, the text is too small
or too long. Size type FOR the thumbnail, not the full canvas.

---

## 4. Color system + the 60-30-10 rule

These are the **only** colors. They are the same tokens as the website
(`global.css`). Do not introduce others except the two functional signals.

### Palette

| Role | Hex | Use |
|------|-----|-----|
| **Canvas (dominant)** | `#F4F6F9` light grey · `#FFFFFF` white | Slide backgrounds. |
| **Canvas alt** | `#EEF2F7` | Alternate slide background for rhythm. |
| **Navy primary** | `#0D1B2A` | Headlines, dark slides, primary text. |
| **Navy secondary** | `#13294B` | Sub-headings, supporting text. |
| **Slate body** | `#475569` | Body copy on light. |
| **Muted** | `#8A99A8` | Captions, slide numbers, "// labels". |
| **Amber accent** | `#F5A623` | THE accent - one highlight per slide, CTA fill. |
| **Amber deep** | `#D98A07` | Amber text on light backgrounds. |
| **Amber wash** | `#FCEFD4` | Highlight blocks, chips behind a word. |
| **Border / hairline** | `#DCE3EC` | Dividers, card outlines, the dotted grid. |
| **Success (signal only)** | `#16794D` | "Seats open", "✓ done". Sparingly. |
| **Red (signal only)** | use for the word **DON'T** only | Matches the DON'T/DO reference format. |

### The 60-30-10 rule (apply to EVERY slide)

- **60% - dominant:** the light canvas (`#F4F6F9` / `#FFFFFF`). The slide should
  feel mostly empty and bright.
- **30% - secondary:** navy (`#0D1B2A` / `#13294B`) - the headline mass and any
  dark panel/diagram.
- **10% - accent:** amber (`#F5A623`) - ONE highlight: a key word, an arrow, the
  CTA button, a dot. Never flood a slide with amber.

> If a slide has more than ~10% amber, it's wrong. Amber is a spice, not a base.

**Dark-slide variant:** flip it for emphasis slides - 60% navy `#0D1B2A`
background, 30% white text, 10% amber accent. Use for the Hook or CTA slide to
create contrast in the carousel.

---

## 5. Non-negotiable design rules (from the reference decks)

These are hard constraints. Each maps to a "DON'T → DO" from our references.

1. **❌ Don't center-align body text → ✅ Left-align everything.**
   Left-aligned text creates a clear reading start point. Only a short
   2–4 word display headline or a CTA button label may be centered.

2. **✅ Design for the Z-pattern / F-pattern.**
   The eye enters top-left. Place in order: small kicker label (top-left) →
   big headline → supporting line/visual → CTA or "swipe" cue (bottom-right).
   Lead the eye 1 → 2 → 3 → 4 down and across.

3. **✅ Use the 2026 tall sizes (1080×1440)** - see §3. Never old squares.

4. **❌ Don't add too much graphics → ✅ Keep it minimalistic.**
   One idea per slide. Lots of whitespace (the 60% canvas). At most one
   illustration/diagram/screenshot per slide. No clutter, no stickers, no
   gradients-on-gradients.

5. **❌ Don't add slides randomly → ✅ Use a fixed structure.**
   Every carousel follows: **Hook → Point 1 → Point 2 → Point 3 → CTA.**
   (Or the DON'T/DO pairs format.) There is always a deliberate flow.

6. **❌ Don't use basic Claude/Canva templates → ✅ Use BBT brand colors.**
   Generic AI/Canva look is banned. It must use our navy + amber + the dotted
   grid background and our fonts. It should look like *us*, not a template.

7. **❌ Don't shrink the text → ✅ Size it for thumbnails.**
   Headlines big and bold. If text must shrink to fit, cut words instead.
   Legible in the feed grid at small scale (see §3 thumbnail rule).

8. **✅ 60-30-10 color discipline** on every slide (§4).

---

## 6. Slide structure & content flow

### Default carousel skeleton (5–7 slides)

| Slide | Role | Content |
|-------|------|---------|
| **1 - Hook** | Stop the scroll | A bold claim, question, or number. 3–7 words. Often a **dark navy slide** for contrast. Add a subtle "swipe →" cue. |
| **2–4 - Points** | Deliver value | One insight per slide. Kicker label + headline + 1–2 supporting lines + optional small visual. Number them (01/02/03). |
| **5 - Proof / structure** | Make it concrete | A simple diagram (the Hook→Point→CTA flow, the 4-phase path, a before/after). Built from BBT shapes - squares, arrows, the amber rail. |
| **Last - CTA** | Convert | Clear next action + handle. Amber button look. e.g. "Enroll in Cohort 07 → link in bio" / "DM us 'TRACK'". |

### DON'T / DO format (alternate, matches the references)

Each concept = a **pair**: top half `DON'T` (word in **red**) + the wrong
example, bottom half `DO` (word in **green** `#16794D`) + the right example,
split by a horizontal hairline. Use this for "tips" / educational carousels.

### Every slide carries (the persistent furniture)

- **Dotted-grid background** (faint `#DCE3EC` dots on the light canvas) - our
  signature texture from the website hero.
- **Slide counter** top-right (e.g. `3/9`) in muted mono.
- **BBT logo mark** small, bottom-left or top-left, consistent across slides.
- **A mono "// kicker"** label above headlines for the technical voice.

---

## 7. Typography & copy voice

### Fonts (same as the site)

| Role | Font | Weight | Use |
|------|------|--------|-----|
| **Display / headlines** | **Bricolage Grotesque** | 700–800 | Every big headline. Tight leading (~0.92), negative letter-spacing (−0.035em). |
| **Body / supporting** | **IBM Plex Sans** | 400–600 | Sub-text, descriptions, captions. |
| **Technical labels / code / numbers** | **IBM Plex Mono** | 400–600 | Kickers ("// cohort_07"), slide numbers, durations, diagram labels. UPPERCASE, wide tracking (0.18em). |

> Fallbacks if a font is unavailable: Display → any bold grotesque; Body →
> Inter/system-ui; Mono → any monospace. Never substitute a serif or a script.

### Copy rules

- **Headlines:** 3–8 words. Punchy. One amber-highlighted keyword max.
- **Sentence case** for headlines (not Title Case, not ALL CAPS) - except the
  DON'T/DO labels and mono kickers which are UPPERCASE.
- **Body:** short. Max ~2 lines per slide. Plain, senior, specific. No fluff
  adjectives, no "🚀 game-changer" hype.
- **Numbers and specifics win:** "9-month path", "4 phases", "Cohort 07",
  "seats open" - concrete beats vague.
- **Roman-Urdu is allowed** in captions when the audience expects it (the
  reference example "US clients ko local +1 number se call karein" shows mixed
  voice is fine for relatability) - but keep *on-slide* headline copy in clean
  English for premium feel.
- **End every carousel** with one clear CTA and the handle/link.

### Caption (the post text, not on the image)

- 1 hook line → 2–4 value lines → CTA → 3–6 relevant hashtags.
- Hashtag set (rotate, don't spam): `#BigBinaryTrainings #ITtrainingPakistan
  #LearnToCode #CareerInTech #FullStack #AIengineering #Pakistan`.
- LinkedIn caption: more formal, can be longer (a short story + lesson + CTA),
  fewer hashtags (3–5).

---

## 8. Output format A - slide-by-slide spec (default text output)

When not rendering an image, return the post as a structured spec the designer
can build directly. Use exactly this shape:

```
PLATFORM: Instagram carousel
SIZE: 1080 x 1440 (3:4)
SLIDES: 6
THEME: navy + amber, dotted grid, 60-30-10

- SLIDE 1 (HOOK) - [dark navy background variant]
  Kicker (mono, amber):   // cohort_07
  Headline (Bricolage):   Stop "learning to code." Start a career.
  Accent:                 amber underline on "career"
  Footer:                 swipe →   |   BBT logo bottom-left
  Layout:                 left-aligned, headline fills 60% of height

- SLIDE 2 (POINT 01) -
  Kicker:                 // 01 · the problem
  Headline:               Courses end. Careers don't.
  Body (Plex Sans):       Most bootcamps stop at a certificate. We don't.
  Visual:                 small amber arrow Trainee → Expert
  Color split:            60% #F4F6F9 / 30% navy text / 10% amber arrow

... (repeat for each slide) ...

- SLIDE 6 (CTA) - [amber accent button]
  Headline:               Cohort 07 is enrolling.
  Button:                 Enroll now → link in bio
  Handle:                 @bigbinarytrainings

CAPTION:
  <hook line>
  <2–4 value lines>
  <CTA>
  <hashtags>
```

---

## 9. Output format B - HTML/CSS render (pixel-accurate, reuses brand tokens)

When asked for a *rendered* slide, output a single self-contained HTML file
sized to the canvas, using the BBT tokens. This guarantees brand accuracy and
can be screenshotted to PNG. Skeleton:

```html
<!DOCTYPE html><html><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  /* ONE slide = the exact export canvas */
  .slide{
    width:1080px;height:1440px;position:relative;overflow:hidden;
    background:#F4F6F9;                       /* 60% canvas */
    font-family:'IBM Plex Sans',sans-serif;color:#475569;
    padding:90px;                              /* safe margin */
    display:flex;flex-direction:column;justify-content:space-between;
  }
  /* signature dotted grid */
  .slide::before{content:"";position:absolute;inset:0;
    background-image:radial-gradient(#DCE3EC 1.2px,transparent 1.2px);
    background-size:32px 32px;opacity:.6;pointer-events:none;}
  .kicker{font-family:'IBM Plex Mono',monospace;font-size:24px;
    letter-spacing:.18em;text-transform:uppercase;color:#D98A07;}
  .headline{font-family:'Bricolage Grotesque',sans-serif;font-weight:800;
    font-size:120px;line-height:.92;letter-spacing:-.035em;color:#0D1B2A;
    margin:24px 0;text-align:left;}            /* never center body/headline */
  .headline .hi{color:#D98A07;}                /* the 10% amber word */
  .body{font-size:34px;line-height:1.5;color:#475569;max-width:820px;}
  .counter{position:absolute;top:36px;right:48px;font-family:'IBM Plex Mono',
    monospace;font-size:22px;color:#8A99A8;}
  .cta{align-self:flex-start;background:#F5A623;color:#0D1B2A;font-weight:700;
    font-family:'IBM Plex Mono',monospace;text-transform:uppercase;
    letter-spacing:.06em;padding:22px 36px;border-radius:999px;font-size:30px;}
  /* dark variant for Hook/CTA slides */
  .slide--dark{background:#0D1B2A;color:#fff;}
  .slide--dark .headline{color:#fff;}
</style></head>
<body>
  <div class="slide">
    <div>
      <div class="kicker">// 01 · the problem</div>
      <div class="headline">Courses end.<br>Careers <span class="hi">don't.</span></div>
      <div class="body">Most bootcamps stop at a certificate. We take you all the way to a role.</div>
    </div>
    <div class="counter">2/6</div>
    <div class="cta">Swipe →</div>
  </div>
</body></html>
```

Produce one such block per slide (or stack them). They can be screenshotted at
1080×1440 for a ready-to-post PNG.

---

## 10. Output format C - image-generation prompt (for AI image tools)

When the target is a text-to-image model, translate the slide into a prompt
that pins the brand. Template:

```
A premium tech-education Instagram carousel slide, 1080x1440 portrait.
Background: light grey #F4F6F9 with a faint dotted grid texture.
Layout: LEFT-ALIGNED. Top-left small uppercase monospace label in amber #D98A07
reading "<kicker>". Below it a large bold Bricolage Grotesque headline in navy
#0D1B2A reading "<headline>", with the word "<keyword>" in amber #F5A623.
Lots of whitespace (minimalist). One small clean diagram bottom area: <visual>.
Bottom-right a subtle "swipe →" cue. Small BBT logo bottom-left.
Strict 60-30-10 color rule: ~60% light canvas, ~30% navy, ~10% amber accent.
Style: engineering-grade, calm, premium, NOT a generic Canva template.
No clutter, no extra colors, no center-aligned text.
```

Fill `<kicker>`, `<headline>`, `<keyword>`, `<visual>` per slide.

---

## 11. Quick pre-publish checklist

Before any post is final, verify all of these:

- [ ] Size is **1080×1440** (or correct per-platform §3), not an old square.
- [ ] **60-30-10** respected - slide is mostly light, navy mass, ~10% amber.
- [ ] Body text is **left-aligned**.
- [ ] Headline is **legible at thumbnail** scale; text not shrunk to fit.
- [ ] **One idea per slide**; minimalist; ≤1 visual per slide.
- [ ] Carousel follows **Hook → Points → CTA** (or DON'T/DO pairs).
- [ ] Uses **Bricolage Grotesque + IBM Plex Sans/Mono** only.
- [ ] Uses **only BBT colors**; doesn't look like a generic template.
- [ ] **CTA slide** present with handle/link.
- [ ] Logo + slide counter on every slide.
- [ ] Caption has hook + value + CTA + relevant hashtags.

---

## 12. Brand quick-reference (copy block)

```
FONTS    Display: Bricolage Grotesque (700–800)
         Body:    IBM Plex Sans (400–600)
         Mono:    IBM Plex Mono (400–600)

COLORS   Canvas    #F4F6F9 / #FFFFFF   (60%)
         Navy      #0D1B2A / #13294B   (30%)
         Amber     #F5A623             (10% accent)
         Amber dk  #D98A07   Wash #FCEFD4
         Slate     #475569   Muted #8A99A8   Border #DCE3EC
         Signals   #16794D success · red = DON'T label only

SIZES    IG carousel 1080×1440 · LinkedIn 1080×1350 · Story 1080×1920
         Safe margin 90px. Legible at thumbnail.

RULES    Left-align · Z-pattern · minimal · Hook→Points→CTA ·
         brand colors only · big text · 60-30-10

VOICE    Premium, technical, senior, calm. Specific numbers. No hype.
         Trainee → Internee → Shadow → Expert.
```
