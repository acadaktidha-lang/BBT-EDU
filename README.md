# Big Binary Trainings - Static Site

Self-contained HTML/CSS/JS site. No build step, no framework, no dependencies you need to install.

## What's inside

```
export/
├── home.html           # /
├── courses.html        # /courses    (was "Tracks")
├── curriculum.html     # /curriculum (was "Freestyle Courses")
├── about.html          # /about
├── franchise.html      # /franchise
├── enroll.html         # /enroll
├── assets/             # logos + photos used across the site
│   ├── bbt-logo.png
│   ├── bbt-logo-dark.png
│   ├── bbt-building.png
│   └── logos/          # partner & accreditor logos
└── README.md
```

## Running locally

Either:

**Option 1 - Open in browser**
Just double-click `home.html`. Everything works (Google Fonts load over the network, all other assets are local).

**Option 2 - Run a tiny static server** (recommended; needed for some browsers' file:// security)
```bash
cd export
python3 -m http.server 8000
# then open http://localhost:8000/home.html
```

Or with Node:
```bash
npx serve .
```

## Tech notes

- **CSS**: Inline `<style>` blocks per page. Tokens (colors, spacing, type scale) are repeated in each page so any page can ship standalone.
- **Fonts**: Google Fonts CDN - Bricolage Grotesque (display), IBM Plex Sans (body), IBM Plex Mono (mono/labels).
- **JavaScript**: ~zero. The hamburger menu uses a single inline `onclick`. Hero animations use native SVG `<animateMotion>`. Scroll-driven header morph uses CSS `animation-timeline:scroll()` (graceful degradation in older browsers - the header just stays its default style).
- **Responsive**: Mobile-first breakpoints at 980px and 640px. Hamburger menu activates below 980px.
- **Browser support**: Modern evergreen (Chrome, Safari, Firefox, Edge latest). `animation-timeline` is progressive enhancement; everything else degrades gracefully.

## Color system

| Role | Value |
|---|---|
| Navy (primary text) | `#0D1B2A` |
| Navy hover/deeper | `#13294B` |
| Amber (accent) | `#F5A623` |
| Amber deep | `#D98A07` |
| Cream (amber bg) | `#FCEFD4` |
| Page gray | `#F4F6F9` |
| Page gray (alt) | `#EEF2F7` |
| Border | `#DCE3EC` |
| Body text | `#475569` |

## Type scale

- H1: `clamp(3rem, 2rem + 5vw, 6.5rem)` / line-height `.92` / letter-spacing `-.035em`
- H2 (section title): `clamp(2.4rem, 2rem + 3vw, 4rem)`
- Body: `1rem` / line-height `1.6`
- Eyebrow / mono labels: `10-12px` uppercase / letter-spacing `.18em`

Headings: Bricolage Grotesque · Body: IBM Plex Sans · Labels: IBM Plex Mono.

## Deploying

Drag the `export/` folder into:
- **Netlify** - drag-and-drop deploy at app.netlify.com/drop
- **Vercel** - `vercel deploy`
- **Cloudflare Pages** - upload folder
- **GitHub Pages** - push to a repo, point Pages at the folder
- **S3 / any static host** - upload as-is

No env vars, no build command.
