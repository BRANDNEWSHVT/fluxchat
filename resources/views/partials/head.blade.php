<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- SEO Meta Tags --}}
<title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') . ' - AI Chat Assistant' }}</title>
<meta name="description" content="{{ $description ?? 'FluxChat is a modern, multi-provider AI chat application. Connect to OpenAI, Anthropic, Google Gemini, Ollama, and more.' }}" />
<meta name="keywords" content="AI chat, ChatGPT, Claude, Gemini, LLM, artificial intelligence, chatbot, OpenAI, Anthropic" />
<meta name="author" content="FluxChat" />
<meta name="robots" content="index, follow" />

{{-- Open Graph / Facebook --}}
<meta property="og:type" content="website" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:title" content="{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}" />
<meta property="og:description" content="{{ $description ?? 'FluxChat is a modern, multi-provider AI chat application. Connect to OpenAI, Anthropic, Google Gemini, Ollama, and more.' }}" />
<meta property="og:image" content="{{ asset('og-image.png') }}" />
<meta property="og:site_name" content="{{ config('app.name') }}" />

{{-- Twitter --}}
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:url" content="{{ url()->current() }}" />
<meta name="twitter:title" content="{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}" />
<meta name="twitter:description" content="{{ $description ?? 'FluxChat is a modern, multi-provider AI chat application. Connect to OpenAI, Anthropic, Google Gemini, Ollama, and more.' }}" />
<meta name="twitter:image" content="{{ asset('og-image.png') }}" />

{{-- Favicon --}}
<link rel="icon" href="/favicon.svg" type="image/svg+xml" />
<link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png" />
<link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png" />
<link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180" />
<link rel="manifest" href="/site.webmanifest" />
<meta name="theme-color" content="#18181B" />

{{-- Canonical URL --}}
<link rel="canonical" href="{{ url()->current() }}" />

{{-- Fonts --}}
<link rel="preconnect" href="https://fonts.bunny.net" />
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

{{-- Alpine.js cloak --}}
<style>[x-cloak] { display: none !important; }</style>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
