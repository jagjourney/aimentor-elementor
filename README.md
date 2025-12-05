<div align="center">

# AiMentor Elementor

### The Free AI Page Builder for WordPress

**Generate complete Elementor layouts with a single prompt.**

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress)](https://wordpress.org/)
[![Elementor](https://img.shields.io/badge/Elementor-3.0%2B-purple)](https://elementor.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green)](LICENSE)
[![Version](https://img.shields.io/badge/Version-2.3.0-orange)](https://github.com/jagjourney/aimentor-elementor/releases)

[Features](#features) • [Installation](#installation) • [Providers](#ai-providers) • [Documentation](#documentation) • [Pro](#aimentor-pro)

---

<img src="https://jagjourney.com/wp-content/uploads/aimentor-hero.png" alt="AiMentor Elementor" width="800">

</div>

## What is AiMentor?

AiMentor transforms how you build WordPress pages. Instead of dragging widgets and tweaking settings for hours, just describe what you want:

```
"Create a SaaS landing page with a gradient hero section,
3-column features grid, pricing table with monthly/yearly toggle,
testimonials carousel, and a contact form"
```

**AiMentor generates production-ready Elementor JSON** that drops directly into your canvas. Real widgets. Real styling. Real pages — in seconds.

---

## Features

### Core Generation
- **Full Page Generation** — Complete multi-section pages from a single prompt
- **Section Templates** — 12+ pre-built section types (hero, features, pricing, testimonials, FAQ, etc.)
- **Page Wizard** — Guided generation for 8 page types (landing, about, services, portfolio, etc.)
- **Multi-Variation Output** — Generate multiple layout options and pick your favorite
- **Smart JSON Repair** — Automatic fixing of AI generation errors

### AI Capabilities
- **Image Generation** — DALL-E 3 and Stability AI integration
- **SEO Optimization** — Yoast SEO and Rank Math auto-integration
- **30+ Languages** — Full multi-language support with RTL
- **Tone Profiles** — 12 voice presets + custom tone creation

### Automation Suite
- **Content Pipelines** — Chain AI actions with triggers and conditions
- **Content Calendar** — Schedule AI generations for blog posts, pages, social media
- **Zapier Integration** — Connect to 5,000+ apps via webhooks
- **Slack Notifications** — Real-time alerts for pipeline completions

### Developer Experience
- **WP-CLI Support** — Generate content from the command line
- **Extensibility Hooks** — 20+ filters and actions for customization
- **Knowledge Base** — Ground generations with brand context and guidelines
- **Error Logging** — Built-in log viewer for debugging

---

## AI Providers

AiMentor is **BYOK (Bring Your Own Key)** — use your preferred AI provider:

| Provider | Models | Best For |
|----------|--------|----------|
| **xAI Grok** | Grok-2, Grok-2-mini | Fast, creative generations |
| **OpenAI** | GPT-4o, GPT-4-turbo, GPT-3.5 | Reliable, well-structured output |
| **Anthropic** | Claude 3.5 Sonnet, Claude 3 Opus | Long-form, nuanced content |
| **Google** | Gemini Pro | Cost-effective generations |
| **Groq** | Llama, Mixtral | Ultra-fast inference |
| **OpenRouter** | 100+ models | Model flexibility |

Switch providers per-task. Set token limits. Configure temperature. Full control.

---

## Installation

### From GitHub (Recommended)

1. Download the [latest release](https://github.com/jagjourney/aimentor-elementor/releases)
2. Upload to `wp-content/plugins/`
3. Activate in WordPress admin
4. Go to **Settings → AiMentor** and add your API key

### Via WP-CLI

```bash
wp plugin install https://github.com/jagjourney/aimentor-elementor/releases/latest/download/aimentor-elementor.zip --activate
```

### Requirements

- WordPress 5.0+
- Elementor 3.0+ (Free or Pro)
- PHP 7.4+

---

## Quick Start

### 1. Configure Your Provider

Navigate to **Settings → AiMentor → Provider** and:
- Select your AI provider (Grok, OpenAI, Anthropic, etc.)
- Paste your API key
- Click **Test Connection**

### 2. Generate Your First Page

1. Create a new page and open Elementor
2. Drag the **AiMentor** widget to your canvas
3. Enter a prompt like: *"Hero section for a fitness app with bold headline, subtext, and CTA button"*
4. Click **Generate**
5. Review variations and insert your favorite

### 3. Explore Advanced Features

- **Page Wizard**: Full guided page generation
- **Section Templates**: Pre-built starting points
- **Pipelines**: Automate recurring content tasks
- **Knowledge Base**: Add brand context for consistent output

---

## WP-CLI Commands

```bash
# Generate content
wp aimentor generate --prompt="Homepage hero for a bakery" --provider=grok

# Generate to file
wp aimentor generate --prompt="Landing page wireframe" --task=canvas --out=canvas.json

# With options
wp aimentor generate --prompt="Product description" --provider=openai --max_tokens=500
```

---

## Documentation

| Guide | Description |
|-------|-------------|
| [Getting Started](docs/getting-started.md) | First-time setup and configuration |
| [Provider Setup](docs/providers.md) | API keys and provider-specific settings |
| [Pipelines Guide](docs/pipelines.md) | Automation workflows |
| [Quick Actions](docs/developers/quick-actions.md) | Developer customization |
| [Release Guide](docs/release-guide.md) | Contributing and releases |

---

## AiMentor Pro

Unlock advanced features for agencies and power users:

| Feature | Free | Pro |
|---------|:----:|:---:|
| AI Page Generation | ✅ | ✅ |
| Multi-Provider Support | ✅ | ✅ |
| Section Templates | ✅ | ✅ |
| Content Pipelines | ✅ | ✅ |
| **White Label Branding** | ❌ | ✅ |
| **Analytics Dashboard** | ❌ | ✅ |
| **Team Management** | ❌ | ✅ |
| **Agency Features** | ❌ | ✅ |
| **Pro Templates** | ❌ | ✅ |
| **Priority Support** | ❌ | ✅ |

[Learn more about Pro →](https://jagjourney.com/aimentor-pro)

---

## Hooks & Filters

AiMentor is fully extensible:

```php
// Modify generation prompt
add_filter( 'aimentor_generation_prompt', function( $prompt, $context ) {
    return $prompt . "\n\nAlways use brand colors: #3788d8";
}, 10, 2 );

// Track generations
add_action( 'aimentor_after_generation', function( $result, $prompt, $context ) {
    // Log to analytics, notify team, etc.
}, 10, 3 );

// Add custom providers
add_filter( 'aimentor_available_providers', function( $providers ) {
    $providers['custom'] = 'My Custom Provider';
    return $providers;
});
```

[See all hooks →](docs/developers/hooks.md)

---

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

---

## Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/jagjourney/aimentor-elementor/issues)
- **Website**: [jagjourney.com](https://jagjourney.com)

---

## License

AiMentor Elementor is open-source software licensed under the [GPLv2](LICENSE).

---

<div align="center">

**Built with ❤️ for the WordPress community**

[⬆ Back to Top](#aimentor-elementor)

</div>
