# Market2Door — Coming Soon landing page

Pre-launch site for the Market2Door app (foodstuff sourcing and delivery, parcel delivery, van and truck hire, business services) at **https://market2door.ng**. It tells people what's coming and collects a waitlist. It's built the same way as oqevira.com: a static page with no build step, a small PHP backend and an admin area.

```
index.html             the landing page, built on the oqevira.com template: hero (photo, store buttons,
                       postcard + phone), services grid, app showcase, market-to-meal cards, photo strip,
                       about, news, waitlist, app download, footer
privacy.html           privacy policy for the waitlist (NDPA 2023)
terms.html             terms and conditions (pre-launch placeholder; full terms before the app launches)
credits.html           photo credits (Unsplash photographers and Wikimedia Commons licences)
assets/css/styles.css  the oqevira.com stylesheet in Market2Door colours, plus styles for the simple pages
assets/js/main.js      the oqevira.com script: launch config, header/menu, reveals, store buttons, waitlist form,
                       news and photo strip from the admin
assets/fonts/          Manrope (hosted with the site, SIL Open Font Licence)
assets/img/            photos: u-*.jpg from Unsplash, the rest from Wikimedia Commons (see credits.html)
assets/brand/          official logo: original JPG, transparent colour PNGs for light backgrounds,
                       "-reversed" PNGs (green ink turned cream) for the dark header, footer and emails,
                       the M2 monogram and wordmark on their own, favicon, iOS and manifest icons
waitlist.php           waitlist handler: validates, saves and emails each sign-up
unsubscribe.php        unsubscribe link from update emails
api/content.php        published updates + gallery photos as JSON for the home page
backend/               shared PHP (config, database, auth, mail, uploads), blocked from the web
admin/                 the admin pages
uploads/               images uploaded in the admin (scripts blocked)
```

## Run it locally

Start Apache in XAMPP and open `http://localhost/market2door/`. The page also works if you open `index.html` directly, but the waitlist and the admin need PHP.

`backend/config.php` is already set up on this machine. The database is created automatically on the first request at `C:\xampp\htdocs\market2door_data\market2door.sqlite`. The admin login is in `deploy/ADMIN-LOGIN.txt`; that file isn't in git.

Emails aren't sent locally because XAMPP has no mail server. Sign-ups are still saved, and the admin shows which emails didn't go out.

## Deploy (cPanel, account `marketdo`)

1. Upload everything **except** `deploy/`, `README.md` and `.git/` into `public_html`.
2. On the server, copy `backend/config.example.php` to `backend/config.php` and fill it in:
   - `app_secret`: 64 random hex characters (different from the local one);
   - `admin.password_hash`: run `php -r "echo password_hash('your password', PASSWORD_DEFAULT);"`.
   `data_dir` already points to `/home/marketdo/market2door_data`, next to `public_html` and outside the web root.
3. Create the mailbox **support@market2door.ng** in cPanel. Emails are sent from it, and new sign-up alerts go to it.
4. Make sure SSL is active (it is, per cPanel), then visit `https://market2door.ng/admin/` and log in.
5. In Admin → Settings, press **Save and send me a test** to check that email works.

When you change `styles.css` or `main.js`, bump the `?v=` on their links in `index.html` (and in `privacy.html`, `terms.html` and `credits.html` for the CSS). The server tells browsers to cache them for a year.

## Launch configuration

At the top of `assets/js/main.js`:

```js
const M2D_CONFIG = {
  appStoreUrl: '',     // real App Store listing URL
  googlePlayUrl: '',   // real Google Play listing URL
  signupEndpoint: ...  // waitlist.php on market2door.ng (worked out from the page's host)
};
```

While the store URLs are empty, both store buttons say "Coming soon" and show a short notice instead of linking anywhere.

## Waitlist

The form follows the oqevira.com layout in three steps. **1 About you:** full name, email, phone (WhatsApp if they have it) and state. **2 How will you use Market2Door?:** customer, rider, van or truck owner, or business. Riders say what they ride, vehicle owners give their vehicle type and fleet size, and businesses give their name and type. **3 Optional:** what they're excited about, how often they shop for foodstuff, how they heard about us, and a message. Consent is required.

`waitlist.php` checks every field again on the server, then:

1. saves the sign-up in SQLite (`~/market2door_data/market2door.sqlite`, never web-accessible);
2. emails it to the alert address in Admin → Settings, with Reply-To set to the person;
3. sends the person a branded welcome email (wording editable in Admin → Settings).

**Spam protection:** a hidden honeypot field, a minimum fill time, a limit of 5 sign-ups per IP address per hour (stored as a salted hash, never the raw IP), and only market2door.ng may post to it. An email that's already on the list isn't added twice.

## Admin (https://market2door.ng/admin/)

| Page | What it does |
| --- | --- |
| Dashboard | Totals, who's signing up by role, what they want, the top states, latest sign-ups |
| Waitlist | Search by name, email, phone, city or business. Filter by role, state and status. Email, WhatsApp or call people, mark them as contacted, resend the welcome email, delete them, or download as CSV (the export matches the filters) |
| Updates | Post news with a photo. Published posts appear in "Latest from Market2Door" on the home page. Optionally email a post to everyone or to one role (customers, riders, van and truck owners, businesses) or one person. Each email has an unsubscribe link, and large lists go out 80 per click to stay within the host's mail limit |
| Gallery | Upload, reorder, hide and delete the photos in "Fresh from the market" |
| Settings | Alert address and the welcome email text, with a test send |
| Account | Your name and password |

Security: sessions expire after 2 hours idle, CSRF tokens on every form, login throttling (10 per IP / 5 per email per 15 minutes), a strict Content-Security-Policy, and `noindex` on every admin page.

## Design

The page uses the oqevira.com template: the same layout, components and behaviour, recoloured to Market2Door's forest green (#123F2A), deep green (#092B1D), warm gold (#D6AE58) and cream (#F8F6EF). In the CSS the original token names are kept: `--navy` is forest green and `--coral` is gold.

The hero, like Oqevira's, has a full-screen photo, the headline with a hand-drawn gold underline, App Store and Google Play buttons marked "Coming soon", a waitlist link, a tilted photo postcard and an illustrative phone screen.

## Content rules followed

The copy describes what Market2Door is building, using the brief's wording. Every service is marked **Coming soon**. It doesn't claim a launch date, launch cities, nationwide coverage, user numbers, partners, reviews or testimonials. Contact details and social links say "coming soon" until they're confirmed.

## Still needed from the Market2Door team

| Item | Where |
| --- | --- |
| Contact details and social media links for the footer (they say "coming soon" for now), and confirmation that **support@market2door.ng** is the address emails are sent from | footer in `index.html`, `backend/mailer.php` |
| Full terms and conditions before the app launches | `terms.html` (marked `TODO(market2door)`) |
| Registered company name and address, and a legal review of the privacy notice | `privacy.html` (marked `TODO(market2door)`), footer copyright |
| App Store and Google Play URLs | `M2D_CONFIG` in `main.js` |
| Your own photography, when available | Admin → Gallery, or replace files in `assets/img/` |

## Photography

Most photos are from [Unsplash](https://unsplash.com) (files named `u-*.jpg`), used under the Unsplash Licence, which allows free commercial use. Garri, palm oil and yam come from Wikimedia Commons under Creative Commons licences. Every photo is credited on `credits.html`. Each one has WebP copies at 640, 1280 and 1920px that the page uses through `srcset`; the JPGs are fallbacks.

## Accessibility and motion

- Skip link, keyboard-accessible menu (Esc closes it), visible focus rings in green on light backgrounds and gold on dark ones.
- Gold buttons use deep-green text. Small gold text on light backgrounds uses the darker `--gold-ink` to meet WCAG AA contrast.
- Form errors appear next to each field, set `aria-invalid`, and are announced through `aria-live`.
- `prefers-reduced-motion` turns off reveals, parallax, the tagline marquee and smooth scrolling. If the script fails to load, all content still shows.

## SEO

`robots.txt`, `sitemap.xml`, `site.webmanifest`, Organization and WebSite structured data, Open Graph and Twitter tags with a 1200×630 share image, canonical URL, and `en-NG` language. `.htaccess` sends `www.` and `http://` to `https://market2door.ng`.
