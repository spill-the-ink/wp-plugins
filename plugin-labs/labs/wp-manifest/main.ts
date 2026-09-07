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
// Values are the labs demo runtime; the renderer produces the same wp-polyfill-*
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
  // Meta Tags – per-context rows
  default_description: "Thoughts on code, WordPress and the open web.",
  default_og_image: 42,
  default_title: "{{ site.name }}",
  home_description: "{{ site.description }}",
  home_og_image: 0,
  home_title: "{{ site.name }} {{ sep }} {{ site.description }}",
  post_type_description: {},
  post_type_og_image: {},
  post_type_title: {
    post: "{{ post.title }} {{ sep }} {{ site.name }}",
    page: "{{ post.title }} {{ sep }} {{ site.name }}",
  },
  taxonomy_description: {},
  taxonomy_og_image: {},
  taxonomy_title: {
    category: "{{ term.name }} {{ sep }} {{ site.name }}",
    post_tag: "{{ term.name }} {{ sep }} {{ site.name }}",
  },
  author_description: "",
  author_og_image: 0,
  author_title: "{{ author.name }} {{ sep }} {{ site.name }}",
  post_type_noindex: { post: true, page: false },
  taxonomy_noindex: { category: true, post_tag: true },
  noindex_authors: true,
  // Site Identity
  canonical_site_url: "https://example.com",
  // Indexing
  noindex_dates: true,
  noindex_search: true,
  noindex_paged: true,
  block_ai_bots: ["gptbot", "claudebot", "ccbot"],
  block_ai_bots_extra: "",
  llms_enabled: true,
  llms_summary: "A personal site about WordPress plugin development.",
  llms_max_entries: 20,
  schema_org_enabled: true,
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

// Read a member value out of a nested DEMO_RUNTIME map (maps mirror the
// PHP post_type_title[]/taxonomy_noindex[]-style option keys).
function mem(key: string, member: string | undefined, fallback: unknown): unknown {
  if (!member) {
    return val(key, fallback);
  }
  const map = DEMO_RUNTIME[key];
  if (map && typeof map === "object" && (map as Record<string, unknown>)[member] !== undefined) {
    return (map as Record<string, unknown>)[member];
  }
  return fallback;
}

// Composite per-context row field (type 'context'), mirroring
// Settings_Page::register_context_field() in settings-page.php.
function contextField(
  id: string,
  label: string,
  spec: Record<string, string>,
  member?: string,
): Record<string, unknown> {
  const values: Record<string, unknown> = {};
  Object.keys(spec).forEach((key) => {
    values[key] = mem(
      spec[key],
      member,
      key === "og_image" ? 0 : key === "noindex" ? false : "",
    );
  });
  const field: Record<string, unknown> = {
    id,
    label,
    type: "context",
    rows: 2,
    namePath: spec.title + (member ? "[" + member + "]" : ""),
    context: spec,
    values,
  };
  if (member) {
    field.member = member;
  }
  return field;
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
    '<div class="wp-manifest-og-preview" data-wp-manifest-og-preview="1">' +
    '<p class="wp-manifest-og-preview__heading">Shared link preview</p>' +
    '<div class="wp-manifest-og-preview__card">' +
    '<div class="wp-manifest-og-preview__media wp-manifest-og-preview__media--empty"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></div>' +
    '<div class="wp-manifest-og-preview__body">' +
    '<div class="wp-manifest-og-preview__domain" data-wp-manifest-og-domain>' + domain + "</div>" +
    '<div class="wp-manifest-og-preview__title" data-wp-manifest-og-title>' + title + "</div>" +
    '<div class="wp-manifest-og-preview__desc" data-wp-manifest-og-desc>' + desc + "</div>" +
    "</div></div>" +
    '<p class="wp-manifest-og-preview__note description">How this site appears when a link is shared on social platforms. Updates as you edit the settings above.</p>' +
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

// Available template variables (mirrors helpers.php wp_manifest_template_variables()).
const TEMPLATE_VARS: Array<[string, string]> = [
  ["{{ site.name }}", "Site name"],
  ["{{ site.description }}", "Site tagline"],
  ["{{ site.url }}", "Site URL"],
  ["{{ post.title }}", "Post title"],
  ["{{ post.excerpt }}", "Post excerpt"],
  ["{{ post.url }}", "Post URL"],
  ["{{ term.name }}", "Term name"],
  ["{{ term.description }}", "Term description"],
  ["{{ term.url }}", "Term URL"],
  ["{{ author.name }}", "Author display name"],
  ["{{ author.bio }}", "Author bio"],
  ["{{ post_type.name }}", "Post type name"],
  [
    "{{ current.type }}",
    "Current context (home/singular/term/author/post_type_archive)",
  ],
  ["{{ page.number }}", "Archive page number"],
  ["{{ sep }}", "Separator — collapsed and deduplicated on output"],
];

function templateVariablesHtml(): string {
  const items = TEMPLATE_VARS.map(
    ([token, label]) => "<li><code>" + token + "</code> — " + label + "</li>",
  ).join("");
  return (
    '<ul class="wp-polyfill-template-vars" style="margin:0;padding-left:1.2em;columns:2;" role="list">' +
    items +
    "</ul>"
  );
}

const page = {
  pageTitle: "Manifest",
  formId,
  optionName: opt,
  tabs: [
    {
      id: "identity",
      label: "Site Identity",
      sections: [
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
              placeholder: "https://example.com",
              value: val("canonical_site_url", ""),
            },
          ],
        },
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
      id: "meta",
      label: "Meta Tags",
      sections: [
        {
          id: "defaults",
          title: "Default",
          description:
            "Fallback used whenever no context-specific row applies, such as search results and other unmatched pages.",
          fields: [
            contextField("context_default", "All other pages", {
              title: "default_title",
              description: "default_description",
              og_image: "default_og_image",
            }),
          ],
        },
        {
          id: "homepage",
          title: "Homepage & blog index",
          description:
            "Templates for the front page and the posts index. When a static page is set as the front page, this row wins there over the Pages row.",
          fields: [
            contextField("context_home", "Homepage", {
              title: "home_title",
              description: "home_description",
              og_image: "home_og_image",
            }),
          ],
        },
        {
          id: "post_type_contexts",
          title: "Post types",
          description: "Singular pages and archives per post type. Noindex applies to both.",
          fields: [
            contextField(
              "context_post_type_post",
              "Post",
              {
                title: "post_type_title",
                description: "post_type_description",
                og_image: "post_type_og_image",
                noindex: "post_type_noindex",
              },
              "post",
            ),
            contextField(
              "context_post_type_page",
              "Page",
              {
                title: "post_type_title",
                description: "post_type_description",
                og_image: "post_type_og_image",
                noindex: "post_type_noindex",
              },
              "page",
            ),
          ],
        },
        {
          id: "taxonomy_contexts",
          title: "Taxonomies",
          description: "Term archives per taxonomy.",
          fields: [
            contextField(
              "context_taxonomy_category",
              "Category",
              {
                title: "taxonomy_title",
                description: "taxonomy_description",
                og_image: "taxonomy_og_image",
                noindex: "taxonomy_noindex",
              },
              "category",
            ),
            contextField(
              "context_taxonomy_post_tag",
              "Tag",
              {
                title: "taxonomy_title",
                description: "taxonomy_description",
                og_image: "taxonomy_og_image",
                noindex: "taxonomy_noindex",
              },
              "post_tag",
            ),
          ],
        },
        {
          id: "author_context",
          title: "Author archives",
          fields: [
            contextField("context_author", "Author archives", {
              title: "author_title",
              description: "author_description",
              og_image: "author_og_image",
              noindex: "noindex_authors",
            }),
          ],
        },
        {
          id: "template_variables",
          title: "Available template variables",
          fields: [
            {
              id: "template_variables_panel",
              label: "",
              type: "html",
              html: templateVariablesHtml(),
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
            "Apply noindex to whole template types. Individual posts, pages and archives can be overridden per context on the Meta Tags tab, or from their Manifest metabox.",
          fields: [
            ["noindex_dates", "Date archives"],
            ["noindex_search", "Search result pages"],
            ["noindex_paged", "Paginated archive pages"],
          ].map(([key, label]) => ({
            id: key,
            label,
            type: "checkbox",
            description: label + " — noindex",
            value: val(key, true),
          })),
        },
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
  const preview = document.querySelector("[data-wp-manifest-og-preview]");
  if (!preview) return;
  const titleEl = preview.querySelector("[data-wp-manifest-og-title]");
  const descEl = preview.querySelector("[data-wp-manifest-og-desc]");
  const domEl = preview.querySelector("[data-wp-manifest-og-domain]");
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

const root = document.querySelector(".wp-polyfill-admin-wrapper");
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
