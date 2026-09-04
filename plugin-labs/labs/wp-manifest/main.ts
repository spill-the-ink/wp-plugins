import "./global.css";

// Stub wp.media for the labs environment (no real WordPress media library).
if (typeof (window as any).wp === "undefined") {
  (window as any).wp = {};
}
(window as any).wp.media = function () {
  return {
    _selectCallback: null as (() => void) | null,
    on(event: string, cb: () => void) {
      if (event === "select") this._selectCallback = cb;
      return this;
    },
    open() {
      const id = prompt("Labs demo: enter a numeric attachment ID:");
      if (id && this._selectCallback) {
        this._selectCallback();
      }
      return this;
    },
    state() {
      return {
        get() {
          return {
            first() {
              return { toJSON() { return { id: 0, url: "" }; } };
            },
          };
        },
      };
    },
  };
};

// The wp-manifest settings schema mirrored 1:1 from
// plugins/wp-manifest/includes/settings-page.php (tabs/sections/fields).
// Values are the labs demo runtime; the renderer produces the same ss-*
// flat-table markup the PHP Settings_Page renders in WordPress.
const AI_BOT_AGENTS: Record<string, string> = {
  gptbot: "GPTBot",
  "chatgpt-user": "ChatGPT-User",
  "oai-searchbot": "OAI-SearchBot",
  claudebot: "ClaudeBot",
  "claude-web": "Claude-Web",
  perplexitybot: "PerplexityBot",
  "perplexity-user": "Perplexity-User",
  bytespider: "Bytespider",
  "applebot-extended": "Applebot-Extended",
  "google-extended": "Google-Extended",
  ccbot: "CCBot",
  "anthropic-ai": "anthropic-ai",
  "meta-externalagent": "Meta-ExternalAgent",
  amazonbot: "Amazonbot",
  "cohere-ai": "cohere-ai",
};

const DEMO_RUNTIME: Record<string, unknown> = {
  default_description: "Thoughts on code, WordPress and the open web.",
  default_og_image: 42,
  canonical_site_url: "https://example.com",
  noindex_categories: false,
  noindex_tags: false,
  noindex_authors: true,
  noindex_dates: true,
  noindex_search: true,
  noindex_paged: true,
  block_ai_bots: ["gptbot", "claudebot", "ccbot"],
  block_ai_bots_extra: "",
  llms_enabled: true,
  llms_summary: "A personal site about WordPress plugin development.",
  llms_max_entries: 20,
  schema_org_enabled: true,
  org_logo: 42,
  org_same_as: "https://github.com/spill-the-ink",
  manifest_enabled: true,
  manifest_name: "Example Site",
  manifest_short_name: "Example",
  manifest_description: "",
  manifest_icon_192: 0,
  manifest_icon_512: 0,
  manifest_theme_color: "#4a90d9",
  manifest_background_color: "#ffffff",
  manifest_display: "standalone",
  manifest_start_url: "/",
};

function val(key: string, fallback: unknown): unknown {
  return DEMO_RUNTIME[key] !== undefined ? DEMO_RUNTIME[key] : fallback;
}

const formId = "wpm-settings";
const opt = "wp_manifest_settings";

// Panel: Open Graph shared-link preview (mirrors helpers.php wp_manifest_render_og_preview).
function ogPreviewHtml(): string {
  const domain = "example.com";
  const title = "Example Site";
  const desc =
    String(val("default_description", "") || "") ||
    "A short, descriptive summary of this site will appear here once a default description is set.";
  return (
    '<div class="wpm-og-preview" data-wpm-og-preview="1">' +
    '<p class="wpm-og-preview__heading">Shared link preview</p>' +
    '<div class="wpm-og-preview__card">' +
    '<div class="wpm-og-preview__media wpm-og-preview__media--empty"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></div>' +
    '<div class="wpm-og-preview__body">' +
    '<div class="wpm-og-preview__domain" data-wpm-og-domain>' + domain + "</div>" +
    '<div class="wpm-og-preview__title" data-wpm-og-title>' + title + "</div>" +
    '<div class="wpm-og-preview__desc" data-wpm-og-desc>' + desc + "</div>" +
    "</div></div>" +
    '<p class="wpm-og-preview__note description">How this site appears when a link is shared on social platforms. Updates as you edit the settings above.</p>' +
    "</div>"
  );
}

// Panel: core settings review (mirrors settings.php wp_manifest_render_core_settings_check,
// using representative demo statuses since there is no real WP install here).
const CORE_CHECK_ITEMS: Array<{ status: string; color: string; title: string; body: string }> = [
  {
    status: "OK",
    color: "#00a32a",
    title: "Indexing",
    body: "This site is publicly indexable, so the metadata below is applied as configured.",
  },
  {
    status: "Action needed",
    color: "#d63638",
    title: "Permalink structure",
    body: "Plain permalinks are active. Use a permanent structure such as /%postname%/.",
  },
  {
    status: "OK",
    color: "#00a32a",
    title: "Canonical domain",
    body: "Canonical domain matches the current host.",
  },
  {
    status: "Recommended",
    color: "#2271b1",
    title: "Site timezone",
    body: "Timezone is UTC. Set your location so structured-data timestamps are correct.",
  },
  {
    status: "Recommended",
    color: "#2271b1",
    title: "Site language",
    body: "og:locale is derived from the site language.",
  },
  {
    status: "Recommended",
    color: "#2271b1",
    title: "Image sizes",
    body: "og:image uses the “large” size. Upload featured images at 1200px+ wide.",
  },
];

function coreCheckHtml(): string {
  const rows = CORE_CHECK_ITEMS.map(
    (item) =>
      '<tr>' +
      '<td style="width:1%;white-space:nowrap;"><span style="color:' +
      item.color +
      ";font-weight:600;\">" +
      item.status +
      "</span></td>" +
      '<td style="width:22%;"><strong>' +
      item.title +
      "</strong></td>" +
      "<td>" +
      item.body +
      "</td></tr>"
  ).join("");
  return (
    '<div class="wpm-core-check"><h2>Settings to review</h2>' +
    "<p>Settings that affect the metadata rendered by this plugin:</p>" +
    '<table class="widefat striped" style="max-width:960px;"><tbody>' +
    rows +
    "</tbody></table></div>"
  );
}

const page = {
  pageTitle: "WordPress Manifest",
  formId,
  optionName: opt,
  tabs: [
    {
      id: "identity",
      label: "Site Identity",
      sections: [
        {
          id: "defaults",
          title: "Site defaults",
          fields: [
            {
              id: "default_description",
              label: "Default meta description",
              type: "textarea",
              description:
                "Used whenever a page, post or archive has no specific description. WordPress core outputs none by default.",
              rows: 4,
              value: val("default_description", ""),
            },
          ],
        },
        {
          id: "canonical",
          title: "Canonical domain",
          description:
            "Base URL that canonicals, Open Graph URLs and structured-data URLs should point to. Useful to prepare a staging copy for its production domain.",
          fields: [
            {
              id: "canonical_site_url",
              label: "Canonical domain",
              type: "text",
              description:
                "Base URL (with scheme) that canonicals, Open Graph URLs and structured-data URLs should point to. Useful to prepare a staging copy for its production domain.",
              placeholder: "https://example.com",
              value: val("canonical_site_url", ""),
            },
          ],
        },
        {
          id: "identity_media",
          title: "Fallback media",
          description:
            "Images used as fallbacks across the plugin when a post has no specific image.",
          fields: [
            {
              id: "default_og_image",
              label: "Default Open Graph image",
              type: "media",
              description: "Fallback image for og:image when a post has no featured image.",
              value: val("default_og_image", 0),
            },
            {
              id: "org_logo",
              label: "Organization logo",
              type: "media",
              description: "Used in JSON-LD Organization schema and web app manifest.",
              value: val("org_logo", 0),
            },
          ],
        },
        {
          id: "social_profiles",
          title: "Social profiles",
          description: "Added to the Organization schema as sameAs links.",
          fields: [
            {
              id: "org_same_as",
              label: "Social profile URLs",
              type: "textarea",
              description: "One URL per line.",
              rows: 4,
              value: val("org_same_as", ""),
            },
          ],
        },
        {
          id: "core_check",
          title: "Core settings",
          fields: [
            {
              id: "core_check_panel",
              label: "",
              type: "html",
              html: coreCheckHtml(),
            },
          ],
        },
      ],
    },
    {
      id: "indexing",
      label: "Indexing",
      sections: [
        {
          id: "index",
          title: "Indexability",
          description:
            "Apply noindex to whole template types. Individual posts/pages can be overridden from their Manifest metabox.",
          fields: [
            "noindex_categories",
            "noindex_tags",
            "noindex_authors",
            "noindex_dates",
            "noindex_search",
            "noindex_paged",
          ].map((key) => ({
            id: key,
            label: {
              noindex_categories: "Category archives",
              noindex_tags: "Tag archives",
              noindex_authors: "Author archives",
              noindex_dates: "Date archives",
              noindex_search: "Search result pages",
              noindex_paged: "Paginated archive pages",
            }[key],
            type: "checkbox",
            description: `${{
              noindex_categories: "Category archives",
              noindex_tags: "Tag archives",
              noindex_authors: "Author archives",
              noindex_dates: "Date archives",
              noindex_search: "Search result pages",
              noindex_paged: "Paginated archive pages",
            }[key]} — noindex`,
            value: val(key, false),
          })),
        },
      ],
    },
    {
      id: "social",
      label: "Social & Sharing",
      sections: [
        {
          id: "open_graph_preview",
          title: "Open Graph preview",
          description: "How this site appears when a link is shared on social platforms.",
          fields: [
            {
              id: "og_preview_panel",
              label: "",
              type: "html",
              html: ogPreviewHtml(),
            },
          ],
        },
      ],
    },
    {
      id: "ai",
      label: "AI & Crawlers",
      sections: [
        {
          id: "ai_bots",
          title: "AI crawler policy",
          description:
            "Applied to the virtual robots.txt that WordPress generates when no physical file exists. A physical robots.txt in the site root takes precedence.",
          fields: [
            {
              id: "block_ai_bots",
              label: "Block these crawlers",
              type: "checkbox_group",
              options: AI_BOT_AGENTS,
              value: val("block_ai_bots", []),
            },
            {
              id: "block_ai_bots_extra",
              label: "Additional user agents to block",
              type: "textarea",
              description: "One user agent per line, e.g. MyBot. Empty lines are ignored.",
              rows: 4,
              value: val("block_ai_bots_extra", ""),
            },
          ],
        },
        {
          id: "llms",
          title: "llms.txt",
          description:
            "A standards/plain-text file AI crawlers look for at site root. A physical llms.txt in the site root takes precedence.",
          fields: [
            {
              id: "llms_enabled",
              label: "Enable llms.txt",
              type: "checkbox",
              description: "Serve llms.txt at /llms.txt",
              value: val("llms_enabled", true),
            },
            {
              id: "llms_summary",
              label: "Site summary",
              type: "textarea",
              description: "Plain-text summary served to AI crawlers. Empty uses the site tagline.",
              rows: 4,
              value: val("llms_summary", ""),
            },
            {
              id: "llms_max_entries",
              label: "Max links per section",
              type: "number",
              description:
                "Maximum pages and posts linked from llms.txt per section. 0 omits the lists.",
              min: 0,
              max: 200,
              value: val("llms_max_entries", 20),
            },
          ],
        },
      ],
    },
    {
      id: "schema",
      label: "Structured Data",
      sections: [
        {
          id: "schema",
          title: "Structured data",
          description:
            "JSON-LD structured data emitted on the front end. WordPress core adds none by default.",
          fields: [
            {
              id: "schema_org_enabled",
              label: "Enable JSON-LD",
              type: "checkbox",
              description:
                "Emit Organization, WebSite, Article, FAQPage and BreadcrumbList markup",
              value: val("schema_org_enabled", true),
            },
          ],
        },
      ],
    },
    {
      id: "webmanifest",
      label: "Web Manifest",
      sections: [
        {
          id: "webmanifest",
          title: "Web app manifest",
          description:
            "A lightweight manifest.json that tells browsers how to display and bookmark the site. No service worker or offline support — just metadata.",
          fields: [
            {
              id: "manifest_enabled",
              label: "Enable manifest.json",
              type: "checkbox",
              description:
                'Serve /manifest.json and add <link rel="manifest"> to the front end.',
              value: val("manifest_enabled", true),
            },
            {
              id: "manifest_name",
              label: "App name",
              type: "text",
              description:
                "Full name shown in install prompts. Empty uses the site name.",
              value: val("manifest_name", ""),
            },
            {
              id: "manifest_short_name",
              label: "Short name",
              type: "text",
              description:
                "Name shown on home screen. Keep to 12 characters. Empty uses the app name.",
              value: val("manifest_short_name", ""),
            },
            {
              id: "manifest_description",
              label: "Description",
              type: "textarea",
              description: "App description. Empty uses the site tagline.",
              rows: 2,
              value: val("manifest_description", ""),
            },
          ],
        },
        {
          id: "manifest_icons",
          title: "Icons",
          description:
            "Icons are used by operating systems for app icons, splash screens and bookmarks.",
          fields: [
            {
              id: "manifest_icon_192",
              label: "192 × 192 icon",
              type: "media",
              description: "PNG or SVG. Required for Android home screen.",
              value: val("manifest_icon_192", 0),
            },
            {
              id: "manifest_icon_512",
              label: "512 × 512 icon",
              type: "media",
              description: "PNG or SVG. Used for splash screens and PWA store icons.",
              value: val("manifest_icon_512", 0),
            },
          ],
        },
        {
          id: "manifest_appearance",
          title: "Appearance",
          fields: [
            {
              id: "manifest_theme_color",
              label: "Theme color",
              type: "text",
              description: "Hex color for the browser toolbar, e.g. #4a90d9.",
              placeholder: "#4a90d9",
              value: val("manifest_theme_color", ""),
            },
            {
              id: "manifest_background_color",
              label: "Background color",
              type: "text",
              description: "Hex color for the splash screen background, e.g. #ffffff.",
              placeholder: "#ffffff",
              value: val("manifest_background_color", ""),
            },
            {
              id: "manifest_display",
              label: "Display mode",
              type: "select",
              description:
                "How the browser displays the site when launched from the home screen.",
              options: {
                standalone: "Standalone — no browser UI",
                fullscreen: "Fullscreen",
                "minimal-ui": "Minimal UI — back/forward buttons",
                browser: "Browser — full chrome",
              },
              value: val("manifest_display", "standalone"),
            },
            {
              id: "manifest_start_url",
              label: "Start URL",
              type: "text",
              description:
                "Page opened when the site is launched from the home screen.",
              placeholder: "/",
              value: val("manifest_start_url", "/"),
            },
          ],
        },
      ],
    },
  ],
};

function wireOgPreview() {
  const preview = document.querySelector("[data-wpm-og-preview]");
  if (!preview) return;
  const titleEl = preview.querySelector("[data-wpm-og-title]");
  const descEl = preview.querySelector("[data-wpm-og-desc]");
  const domEl = preview.querySelector("[data-wpm-og-domain]");
  const descInput = document.getElementById(formId + "-default_description") as HTMLTextAreaElement | null;
  const domInput = document.getElementById(formId + "-canonical_site_url") as HTMLInputElement | null;
  function read() {
    if (descEl && descInput) {
      descEl.textContent =
        descInput.value ||
        "A short, descriptive summary of this site will appear here once a default description is set.";
    }
    if (domEl && domInput && domInput.value) {
      domEl.textContent = String(domInput.value)
        .replace(/^https?:\/\//, "")
        .replace(/\/$/, "");
    }
  }
  if (titleEl) titleEl.textContent = "Example Site";
  if (descInput) descInput.addEventListener("input", read);
  if (domInput) domInput.addEventListener("input", read);
}

const root = document.querySelector(".ss-admin-wrapper");
if (root) {
  // renderSharedSettingsPage is attached to window by renderer.js (imported below).
  import("../../../tools/shared-settings/renderer.js").then(() => {
    const html = (window as any).renderSharedSettingsPage(page);
    root.innerHTML = html;
    wireOgPreview();
    // Enhance after the DOM is in place.
    import("../../../tools/shared-settings/shared-settings.js");
  });
}
