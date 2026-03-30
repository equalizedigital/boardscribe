# Premium Feature Ideas

Based on the target market (government agencies, HOAs, school boards, nonprofits, boards of directors).

---

## Document Management

- **File upload support** — attach the actual minutes PDF/Word doc directly to the post rather than linking to an external URL
- **Document versioning** — track amended/corrected minutes with a revision history
- **Auto-generate agenda from template** — predefined agenda templates per meeting type (regular, special, emergency)

---

## Compliance & Governance

- **Meeting status workflow** — Draft → Pending Approval → Approved/Published with role-gated transitions
- **Approval signatures** — board member sign-off on minutes with timestamps (important for HOAs and nonprofits under Robert's Rules)
- **Quorum tracking** — record attendance, flag meetings that didn't meet quorum
- **Action item tracking** — extract action items from minutes, assign to people, mark resolved, roll forward to next meeting's agenda

---

## Display & Output

- **Gutenberg block** with visual inspector controls (no shortcode knowledge needed)
- **Multiple display templates** — table, accordion, card grid, timeline view
- **Export to CSV/PDF** — for records requests (very common in government/HOA contexts)
- **Print-optimized stylesheet** — clean printable view of the minutes table
- **Widget/block for "most recent meeting"** — sidebar or footer display
- **WCAG/accessibility compliance reporting** — built-in audit of the output table's accessibility; a strong differentiator for the government market given the existing Accessibility Checker Pro integration

---

## Search & Discovery

- **Full-text search** across meeting minutes content
- **Category/committee filtering** — separate finance committee from board meetings, etc.
- **Date range picker** on the frontend (not just year filtering)
- **Archive page** — auto-generated browseable archive without needing a shortcode

---

## Notifications & Automation

- **Email notifications** when new minutes are published — subscribers can opt in
- **RSS feed** for meeting minutes specifically
- **Reminder emails** before scheduled meetings
- **Auto-publish scheduling** — publish at a set time after meeting date

---

## Integrations

- **Accessibility Checker Pro deeper integration** — already has hooks for this, could be a bundled add-on
- **Gravity Forms / WPForms** — public comment submission tied to a meeting
- **Google Drive / Dropbox** — link or sync documents from cloud storage
- **Zoom/calendar integration** — pull meeting link or add to Google Calendar button
- **iCal/calendar feed** — let users subscribe to meeting dates in Google Calendar, Outlook, or Apple Calendar; government bodies get asked for this constantly

---

## Admin Experience

- **Bulk import via CSV** — migrate years of historical minutes in one step (high value for new customers with existing data)
- **Duplicate meeting** — clone a past meeting record as a starting point
- **Meeting series** — link recurring meetings together (e.g., "Monthly Board Meeting" series)
- **Custom columns** — let admins add/remove columns beyond the defaults

---

## Highest-Value Bets

If prioritizing for an initial premium release, these have the strongest purchase intent for the target market:

1. **Bulk CSV import** — every new customer has years of historical data to migrate
2. **Approval workflow** — boards legally need documented approval of minutes
3. **Email notifications** — passive value, set-and-forget
4. **Export to PDF/CSV** — public records requests are a real and recurring pain point
5. **Multiple display templates / Gutenberg block** — lowers the barrier for non-technical site managers
6. **iCal/calendar feed** — frequently requested by government and HOA clients, low implementation effort relative to perceived value

---

## Freemium Split Recommendation

Deciding the free/premium line before writing new code drives every architecture decision.

**Free (WordPress.org)**
- Core CPT + shortcode with default table display
- Basic year filtering and pagination
- ACF integration

**Premium**
- Gutenberg block with inspector controls
- Additional display templates
- Export to CSV/PDF
- Email notifications and RSS feed
- iCal/calendar feed
- Bulk CSV import
- Approval workflow and quorum tracking
- Action item tracking
- Document versioning
- WCAG accessibility compliance reporting
- Deeper Accessibility Checker Pro integration
