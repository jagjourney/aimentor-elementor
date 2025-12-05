=== AiMentor Elementor ===
Contributors: jagjourney
Tags: elementor, ai, grok, page builder, xai
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
🚀 FREE AI Page Builder - Generate full Elementor layouts with AiMentor. One prompt = complete pages!

* ⚡ Rapid section, page, and template generation inside Elementor.
* 🔁 Multi-provider switching with provider-specific defaults and limits.
* 🔒 Secure key handling, visibility toggles, and connection health badges.
* 📊 Error logging and rate-limit visibility for confident production use.

AiMentor was built for agencies that need reliable AI output without leaving WordPress. Bring your preferred provider, align the models with your workflow, and let AiMentor keep the content flowing.

== Features ==
* **Provider model presets:** Ship with tuned defaults for Grok and OpenAI so each provider loads with recommended models, temperature, and safety guardrails tailored to their API capabilities.
* **Multi-style canvas cards:** Review multiple layout variations per run, insert the favorite option, and log the chosen design in the canvas history automatically.
* **Knowledge packs & grounded context:** Capture reusable brand, product, or process notes in the Knowledge Base tab and apply them to every Elementor generation or rewrite.
* **Auto-Insert toggle:** Decide if generated layouts land directly in the Elementor canvas or wait for manual placement—perfect for teams that want review checkpoints.
* **Theme styling controls:** Keep generated sections on-brand by toggling AiMentor's theme-aware styling helpers on or off per site.
* **Token limit management:** Configure per-provider token ceilings to prevent runaway prompts and stay inside your contract limits.
* **API key visibility controls:** Reveal or mask keys instantly with the built-in eye toggle so only trusted teammates can view sensitive credentials.
* **Connection status badges:** Test each provider and get immediate success, pending, or error badges that confirm when an integration is ready for production.
* **Built-in error log viewer:** Inspect the most recent API issues without leaving WordPress; the underlying log file lives at `wp-content/uploads/aimentor/aimentor-errors.log` (with automatic fallbacks if that path is unavailable).
* **Tone-aware rewrites:** Highlight Elementor copy and apply the “Rewrite with Tone” action to instantly align language with the saved brand keywords or preset voices.

== Frame Workflow ==
1. **Generate** layouts inside Elementor using AiMentor canvas requests. Each run can be archived with its provider, model, and prompt metadata for later reuse.
2. **Archive** winning layouts so they appear under the private `AI Layouts` post type. Archival keeps historical prompts alongside the JSON canvas payload.
3. **Promote to frame** from **Settings → Frame Library**. Administrators can toggle curated layouts, upload fresh preview thumbnails, refine summaries, and confirm suggested sections that editors will see.
4. **Reuse** frames in Elementor. The AiMentor widget now surfaces a Frame Library panel that lists approved layouts with one-click insertion or prompt seeding to jumpstart new generations.

Tip: periodically retire stale frames or swap preview imagery to keep the gallery current. A small rotation—removing unused layouts and promoting fresh winners—keeps the experience fast for editors.

== Compatibility ==
AiMentor requires the free version of Elementor to be active for its widget, settings, and copy workflows to load. When Elementor Pro is detected, advanced experiences—including canvas JSON generation and the in-dashboard **Pro Features** toggle—automatically light up. If Elementor Pro is not available, AiMentor gracefully downgrades canvas requests to copy output while keeping all text generation flows first-class for free users, so teams can still ship content without compromise.

=== Roadmap / Suggestions ===
We're exploring deeper Pro-aware enhancements such as exporting full template kits, supporting Elementor Theme Builder sections, and broader automation hooks so Pro customers can unlock richer handoff options while the free tier keeps its streamlined experience.

== Elementor Widget Experience ==
AiMentor's widget mirrors the native Elementor workflow: pick your provider from the branded badge selector, drop a prompt into the focused input field, and trigger generation without losing context. Provider-colored badges keep the active model obvious, while the same connection health indicators used in the settings surface directly in the widget so editors know when it's safe to launch a run.

Need to adjust existing copy? Select any text within the Elementor editor or reuse the prompt field, choose a tone preset in the AiMentor modal, and click **Rewrite with Tone**. The widget posts your selection to WordPress, rewrites it through the active AI provider, and swaps the response directly into the targeted control while logging the tone that was applied. Status messaging in the modal confirms when the rewrite completes or if additional input is required.

== Operational Tooling ==
* **Error log access:** Every request writes structured context to `wp-content/uploads/aimentor/aimentor-errors.log`, and the settings screen renders the last 10 entries with timestamps and provider names for fast triage.

== WP-CLI ==
Run AiMentor generations from the command line once the plugin is active and the provider API keys are configured.

* **Prerequisites:** Install [WP-CLI](https://wp-cli.org/) on the WordPress host, ensure the AiMentor plugin is active, and store the necessary provider API key in the plugin settings.
* **Generate content:** `wp aimentor generate --prompt="Homepage hero for a bakery" --provider=grok`
* **Generate canvas JSON to a file:** `wp aimentor generate --prompt="Landing page wireframe" --task=canvas --out=canvas.json`
* **Override defaults:** Use `--tier`, `--max_tokens`, or `--provider` to align the run with stored presets without altering the saved configuration.
* **Connection health badges:** The settings dashboard keeps a persistent badge per provider—Success, Pending, Error, or Idle—so agencies can verify API uptime before teams start building.

== Installation ==
1. Upload ZIP.
2. Bring API credentials for your preferred provider—generate an xAI Grok key at https://x.ai/api or create an OpenAI key at https://platform.openai.com/account/api-keys.
3. Open **Settings → AiMentor Elementor** and choose **xAI Grok** or **OpenAI** under **Provider** to set the active radio option.
4. Paste your keys and click the **Test Connection** buttons to verify each provider so you know when their API keys are valid.

== Automatic Updates ==
AiMentor Elementor now advertises GitHub Releases as its canonical update source. The plugin bootstrap includes an `Update URI`
that matches this repository, so WordPress core surfaces each published release in the **Plugins** screen.

* Enable **Automatic updates** for AiMentor Elementor in **Plugins → Installed Plugins** to let WordPress install new GitHub
  releases as soon as they are published.
* Prefer manual control? When a new AiMentor Elementor version appears, click **Update now** in the plugin list to fetch the
  GitHub-hosted ZIP without leaving the dashboard.

No additional AiMentor-specific updater endpoints are required—the historical PHP/JSON feed has been retired in favor of the
native GitHub channel.

== Development ==
* Source code: https://github.com/jagjourney/aimentor-elementor
* Latest ZIP (tagged releases): https://github.com/jagjourney/aimentor-elementor/releases
* Release automation: `.github/workflows/release.yml` validates PHP syntax, builds the ZIP package, attaches it to the release,
  and refreshes the lightweight manifest on `gh-pages`.
* Release checklist and versioning guide: `docs/release-guide.md`.
* Quick actions developer guide: `docs/developers/quick-actions.md`.

== Hooks ==
* `aimentor_compiled_knowledge_payload` — Filter the raw compiled knowledge packs before they are cached for provider consumption.
* `aimentor_provider_knowledge_context` — Filter the provider-ready knowledge payload (including summaries, guidance, and IDs) before each request is dispatched.

== Deployment ==
Tagged releases automatically produce `aimentor-elementor-v<version>.zip` via GitHub Actions and attach the artifact to the corresponding release. A JSON manifest maintained on the `gh-pages` branch mirrors the latest tag for any external tooling that still references it.
Refer to `docs/release-guide.md` for detailed tagging, workflow, and post-release steps.

Need to stage a build before publication? Save the GitHub release as a draft—the automation now packages and uploads the official ZIP while deferring the manifest update until you publish. Manual ZIP uploads remain optional for bespoke artifacts.

== Frequently Asked Questions ==
= Does AiMentor support multiple AI providers? =
Yes. The control center lets you pick between xAI Grok and OpenAI today, with room to add additional providers as new APIs are released. Each provider keeps its own defaults and usage limits.

= Where are my API keys stored? =
Keys are saved in your WordPress database using the standard options table. You can hide or show keys on demand from the settings screen, and revoke them at any time from your AI provider dashboard.

= Can I disable automatic insertion into the canvas? =
Absolutely. Use the **Auto-Insert** toggle in the settings to decide if generated content drops straight into Elementor or if you prefer manual placement.

== Screenshots ==
1. Drag AiMentor widget to canvas
2. Enter prompt in left panel
3. Generate content in middle canvas

== Changelog ==
= 2.3.0 =
* **Pro Extensibility**: Added comprehensive hooks system for AiMentor Pro add-on integration.
* Introduced `AiMentor_Pro_Hooks` class with 20+ action and filter hooks for Pro features.
* Added generation hooks: `aimentor_before_generation`, `aimentor_after_generation`, `aimentor_generation_prompt`, `aimentor_generation_result`.
* Added provider hooks: `aimentor_available_providers`, `aimentor_provider_settings`.
* Added branding hooks: `aimentor_branding`, `aimentor_admin_menu`, `aimentor_widget_settings`.
* Added pipeline hooks: `aimentor_pipeline_triggers`, `aimentor_pipeline_actions`, `aimentor_pipeline_before_run`, `aimentor_pipeline_after_run`.
* Added template hooks: `aimentor_section_templates`, `aimentor_page_types`.
* Added analytics hooks: `aimentor_track_usage`, `aimentor_track_generation`.
* Added team/agency hooks: `aimentor_user_capabilities`, `aimentor_team_limits`.
* Added export/import hooks: `aimentor_export_data`, `aimentor_import_data`.
* Helper functions: `aimentor_is_pro_active()`, `aimentor_get_branding()`, `aimentor_track_event()`, `aimentor_user_can()`, `aimentor_get_team_limits()`.
* Pro add-on can now seamlessly extend core functionality with white-label, analytics, team management, and agency features.

= 2.2.0 =
* **Automation Suite**: Complete automation system with pipelines, calendar, and integrations.
* Added Content Pipeline system with 6 trigger types (schedule, manual, webhook, post publish, form submit, RSS).
* Pipelines support 11 action types including content generation, translation, SEO, posting, and notifications.
* Conditions and template variables enable dynamic, context-aware pipeline execution.
* Introduced Content Calendar for scheduling and managing AI content generation.
* Calendar supports 5 content types: blog posts, pages, social media, newsletters, and product descriptions.
* Visual calendar with drag-and-drop rescheduling and bulk scheduling capabilities.
* Added Zapier integration with webhook subscriptions and REST API endpoints.
* 6 trigger events for Zapier: content generated, page generated, image generated, post created, pipeline completed, scheduled content ready.
* 6 action endpoints for Zapier: generate content, generate image, translate, create post, run pipeline, schedule content.
* Added Slack integration with rich notifications and customizable alerts.
* Slack notifications for pipeline completion/failure, scheduled content status, and API errors.
* Rich block-formatted Slack messages with action buttons and context.
* Pipeline logging with execution history and error tracking.

= 2.1.0 =
* **AI Capabilities Expansion**: Major feature release with image generation, SEO integration, multi-language support, and tone profiles.
* Added AI image generation support via DALL-E 3 and Stability AI (SDXL).
* Image generator supports multiple sizes (square, landscape, portrait) and style presets.
* Integrated SEO optimization with Yoast SEO and Rank Math auto-detection.
* AI-powered meta title, description, and focus keyword generation.
* Added content SEO analysis with keyword density and readability suggestions.
* Introduced multi-language support with 30+ languages including RTL support.
* Content translation capabilities for both text and full Elementor layouts.
* Language detection for existing content.
* Created 12 predefined voice/tone profiles (Professional, Friendly, Casual, Formal, Playful, Inspirational, Technical, Luxury, Bold, Empathetic, Storytelling, Minimalist).
* Custom tone profile creation from sample content.
* Tone analysis to detect existing content style.
* Content rewriting in any selected tone while preserving meaning.

= 2.0.0 =
* **Section Templates & Page Wizard**: Major expansion with pre-built section templates and guided page generation.
* Added 12 professionally designed section templates (hero, features, testimonials, pricing, CTA, FAQ, stats, contact, team).
* Introduced Page Wizard system supporting 8 page types (landing, about, services, pricing, contact, portfolio, blog, coming soon).
* Enhanced prompt builder now includes real JSON template examples for improved AI generation accuracy.
* Template-aware prompts automatically reference matching templates for each section type.
* Page generation mode ensures consistent styling across multi-section pages.
* Added template categories for organized browsing: hero, features, testimonials, pricing, cta, faq, stats, contact, team.
* Each template includes widget definitions, recommended settings, and complete Elementor JSON structure.

= 1.9.0 =
* **Foundation Release**: Major improvements to Elementor JSON generation reliability.
* Added Elementor JSON schema validator for comprehensive structure validation.
* Added automatic JSON repair system that fixes common AI generation errors.
* Introduced enhanced prompt builder with detailed Elementor schema examples.
* Implemented retry/repair pipeline - failed generations are automatically repaired and retried.
* Added provider fallback system - automatically tries secondary providers when primary fails.
* Created comprehensive widget definitions catalog covering 25+ widget types.
* All providers (Grok, OpenAI, Anthropic) now use schema-aware system instructions.
* Verified compatibility with WordPress 6.9 and Elementor 3.27.

= 1.8.2 =
* Enriched the Elementor modal payload so both the editor and settings scripts receive enabled quick-action metadata, prompt/system defaults, and lookup helpers.
* Localized new quick-action toolbar labels, running-state messages, and missing-content prompts for translators.
* Updated script localization wiring to share the expanded payload between admin settings and the Elementor editor.

= 1.8.1 =
* Added a quick-actions management panel on the Brand tab so administrators can enable or disable actions and edit their default prompts and system guidance.
* Extended the admin settings script to render the new controls, sync field changes, and persist updates through the REST API with inline validation messaging.

= 1.8.0 =
* Added an Automation settings tab so administrators can define recurring jobs, choose content sources, pick prompts, and ground runs with knowledge packs.
* Registered a cron-powered automation runner that reuses the existing generation pipeline, queues AI Layout drafts, and logs rate-limit cooldowns for review.
* Surfaced automation queues and approval actions on the Logs tab plus new documentation outlining safeguards and recommended cadences.

= 1.7.0 =
* Added a Knowledge Base settings tab so administrators can capture reusable packs, manage CRUD via REST, and compile summaries for provider context injection.
* Wired the Elementor generator modal and rewrite workflow to fetch knowledge packs, surface a multi-select with live summaries, and send the chosen IDs with every AJAX or REST request.
* Blended knowledge directives into Grok, OpenAI, and Anthropic prompt suffixes so generations and rewrites stay grounded in the selected packs across providers.
* Introduced the `aimentor_provider_knowledge_context` filter to let developers adjust the provider-ready knowledge payload before requests are dispatched.

= 1.6.2 =
* Added a “Rewrite with Tone” workflow inside the Elementor modal that validates capabilities, honors stored brand tone keywords, and rewrites copy via the selected provider.
* Surfaced preset tone selectors alongside rewrite status messaging so editors can preview and apply voices before sending requests.
* Localized new UI strings, updated documentation, and expanded test coverage around tone sanitization and rewrite prompt construction.

= 1.6.1 =
* Refined the Elementor widget canvas placeholder so it now renders a minimal launch button while keeping generation tools in the modal.
* Relocated the layout history carousel and curated frame library into the generator modal with updated styling aligned to Elementor.
* Updated modal bindings to power the new sidebar tabs and ensure history/frame data loads within the dialog.

= 1.6.0 =
* Added multi-variation canvas support across providers with a new selection UI in the Elementor modal.
* Persisted variation selections in the canvas history and tuned rate-limit messaging for multi-option runs.
* Documented the variation workflow with updated screenshots and localization strings.

= 1.5.1 =
* Ensured the settings tab loading spinner uses block-level dimensions so it renders consistently during AJAX fetches.

= 1.5.0 =
* Added a dedicated Frame Library admin tab so curators can approve archived AI Layouts, manage thumbnails, and tune copy before exposing frames to editors.
* Extended the Elementor widget with a Frame Library panel that loads curated frames, previews metadata, and supports one-click insertion or prompt seeding.
* Published frame-aware REST responses and AJAX payload metadata so the editor, presets, and history views can reuse approved layouts consistently.
* Documented the generate → archive → promote → reuse workflow and refreshed the update manifest to announce version 1.5.0 via the GitHub-based updater.

= 1.3.18 =
* Added a Saved Prompts settings tab with personal and shared tables, inline creation form, and secure REST-backed delete controls.
* Wired the new Saved Prompts UI to the existing REST endpoints so updates apply instantly and stay protected by WordPress nonces.
* Synced the Elementor widget dropdown with Saved Prompt changes in real time to avoid page refreshes after editing prompts.

= 1.3.17 =
* Added an automatic update preference with status messaging so administrators can opt-in or block AiMentor Elementor auto updates from the settings screen.
* Hooked into the WordPress plugin auto-update flow to respect the saved preference during update checks.

= 1.3.16 =
* Added a dedicated settings sidebar that surfaces support, tutorial, and JagJourney contact resources sourced from a filterable helper.

= 1.3.15 =
* Added persistent success and failure counters for each provider so connection tests now track long-term reliability.
* Surfaced per-provider test totals and success rates in the settings panel to keep administrators informed at a glance.
* Rendered inline sparkline charts beside each provider badge to visualize recent test outcomes directly in the dashboard.

= 1.3.14 =
* Added per-provider override settings for timeout and temperature so advanced teams can fine-tune request behavior.
* Introduced an "Advanced" accordion on the settings screen with granular numeric controls for the new overrides.
* Applied stored override values within Grok and OpenAI payload builders to honor customized request tuning.
* Updated plugin metadata, manifests, and documentation to reference the jagjourney-hosted release assets and repository URLs.

= 1.3.12 =
* Updated the plugin update metadata and manifest defaults to reference the `jagjourney/aimentor-elementor` repository so auto-update clients fetch the published ZIP.
* Pointed the bundled updater and documentation at the new GitHub Pages manifest location.

= 1.3.11 =
* Added a `wp aimentor generate` WP-CLI command that mirrors the AJAX generation flow, records history, and optionally saves output to disk.
* Documented the WP-CLI workflow with prerequisites and usage examples for both content and canvas exports.

= 1.3.01 =
* Hardened the WordPress option stubs and regression tests to ensure preloaded option values stay untouched when default seeding runs.

= 1.3.00 =
* Registered the private `ai_layout` post type so archived layouts stay isolated from public content and ship with provider context.
* Added settings controls for enabling archival and exposing the admin list table when teams want to browse saved layouts.
* Persisted successful canvas and content payloads when archival is enabled so editors can revisit generated output alongside prompts.

= 1.2.02 =
* Ensured the GitHub-backed updater loads regardless of Elementor status so WordPress always surfaces new releases.

= 1.2.00 =
* Captured successful canvas payloads in a reusable history so editors can revisit recent layouts without leaving Elementor.
* Added an in-widget carousel that previews recent layouts and lets editors inject saved canvas JSON instantly.
* Extended the Elementor widget script to cache canvas runs locally, update the history UI in real time, and reuse layouts without round-tripping to the API.

= 1.1.11 =
* Synced plugin metadata, manifests, and landing collateral with the finalized cooldown messaging release.
* Documented the rate-limit guidance improvements so Elementor users know why the widget may pause between runs.

= 1.1.00 =
* Parsed rate-limit headers from Grok and OpenAI responses so the widget knows when cooldowns expire.
* Returned structured cooldown metadata on both success and error paths to keep retry guidance consistent.
* Added an Elementor widget notice that surfaces localized retry timing when providers throttle requests.

= 1.0.10 =
* Rolled the plugin metadata, changelog references, and distribution manifest back to version 1.0.10 so the published ZIP and WordPress update prompts match the intended release.
* Scheduled a daily provider health check that re-tests active API connections and keeps the cron aligned with the settings toggle.
* Recorded consecutive connection failures, clearing counters on success and surfacing administrator alerts when thresholds are exceeded.
* Added settings toggles to control automated checks, enable alert emails, and configure notification recipients directly from the dashboard.

= 1.0.04 =
* Hardened the CI guardrail so every change to the repository requires a synchronized version bump before merging.

= 1.0.03 =
* Added an opt-in daily provider health check that re-tests stored API keys with WP-Cron.
* Captured consecutive connection failures and alerted administrators once the threshold is reached.
* Introduced settings to toggle automated checks and manage notification recipients.

= 1.0.00 =
* Reset the public changelog to establish AiMentor Elementor as the new baseline.
* Rebranded the plugin from JagGrok to AiMentor Elementor with compatibility shims for existing installs.
* Added migrations to mirror legacy options and asset paths so previous settings continue to work.
* Updated scripts, classes, and AJAX endpoints to use the new AiMentor handles across WordPress and Elementor.
