# Product Requirements Document (PRD)

## **FluxUI Chat - Multi-Provider LLM Chatbot**

---

## 1. Executive Summary

### 1.1 Product Vision
Build a modern, intuitive AI chatbot application that empowers users to interact with multiple LLM providers through a unified interface. Users can seamlessly switch between providers and models while maintaining conversation history and preferences.

### 1.2 Product Name
**FluxUI Chat**

### 1.3 Target Users
- Developers exploring different LLM capabilities
- Power users who want flexibility in AI model selection
- Teams needing a self-hosted chat solution with provider choice
- Users with existing API keys across multiple providers

---

## 2. Problem Statement

### 2.1 Current Pain Points
1. **Vendor Lock-in**: Most chat interfaces are tied to a single provider
2. **Cost Optimization**: Users can't easily switch to cheaper models for simple tasks
3. **Model Comparison**: No easy way to compare responses across providers
4. **Privacy Concerns**: Some users need local/self-hosted options (Ollama)
5. **Custom Integrations**: Enterprise users need custom API endpoints (CliproxyAPI)

### 2.2 Solution
A unified chat interface that abstracts provider complexity while giving users full control over their AI interactions.

---

## 3. Goals & Success Metrics

### 3.1 Primary Goals
| Goal | Description |
|------|-------------|
| **G1** | Enable seamless provider/model switching within conversations |
| **G2** | Provide a clean, modern UI inspired by best-in-class chat apps |
| **G3** | Support both cloud and local LLM providers |
| **G4** | Maintain conversation history with full search capability |

### 3.2 Success Metrics (KPIs)
| Metric | Target | Measurement |
|--------|--------|-------------|
| User Retention | 60% weekly active | Analytics |
| Provider Diversity | Avg 2+ providers configured per user | Database |
| Response Latency | < 500ms to first token | Monitoring |
| Conversation Length | Avg 10+ messages per conversation | Database |

---

## 4. User Stories & Requirements

### 4.1 Epic 1: Provider & Model Management

| ID | User Story | Priority | Acceptance Criteria |
|----|------------|----------|---------------------|
| US-1.1 | As a user, I want to add my API keys for different providers | **P0** | Can save/update/delete API keys securely |
| US-1.2 | As a user, I want to see available models for each provider | **P0** | Models are fetched dynamically or from curated list |
| US-1.3 | As a user, I want to set a default provider/model | **P1** | Default persists across sessions |
| US-1.4 | As a user, I want to switch models mid-conversation | **P1** | Model switch is reflected in message metadata |
| US-1.5 | As a user, I want to configure custom API endpoints (CliproxyAPI) | **P0** | Support base URL, headers, auth customization |

### 4.2 Epic 2: Chat Interface

| ID | User Story | Priority | Acceptance Criteria |
|----|------------|----------|---------------------|
| US-2.1 | As a user, I want to send messages and receive AI responses | **P0** | Streaming responses with typing indicator |
| US-2.2 | As a user, I want to see a model selector in the chat input | **P0** | Dropdown shows provider + model (e.g., "Claude 3.5 Sonnet") |
| US-2.3 | As a user, I want quick action buttons (like "Fast", "In-depth") | **P2** | Preset system prompts or temperature settings |
| US-2.4 | As a user, I want to attach files/images to my messages | **P1** | Support for vision-capable models |
| US-2.5 | As a user, I want markdown rendering in responses | **P0** | Code blocks, tables, lists render properly |
| US-2.6 | As a user, I want to copy code blocks easily | **P0** | One-click copy button on code blocks |
| US-2.7 | As a user, I want to regenerate a response | **P1** | Regenerate with same or different model |

### 4.3 Epic 3: Conversation Management

| ID | User Story | Priority | Acceptance Criteria |
|----|------------|----------|---------------------|
| US-3.1 | As a user, I want to see my conversation history in a sidebar | **P0** | List with titles, dates, organized by recency |
| US-3.2 | As a user, I want to search across all conversations | **P0** | Full-text search with highlighting |
| US-3.3 | As a user, I want to organize conversations into folders | **P1** | Create, rename, delete folders |
| US-3.4 | As a user, I want to archive old conversations | **P1** | Archived section separate from recent |
| US-3.5 | As a user, I want to delete conversations | **P0** | Soft delete with undo option |
| US-3.6 | As a user, I want auto-generated conversation titles | **P1** | AI-generated from first messages |

### 4.4 Epic 4: Advanced Features

| ID | User Story | Priority | Acceptance Criteria |
|----|------------|----------|---------------------|
| US-4.1 | As a user, I want a prompt library for reusable prompts | **P2** | Save, categorize, quick-insert prompts |
| US-4.2 | As a user, I want to toggle "Think" mode for reasoning models | **P2** | Extended thinking for supported models |
| US-4.3 | As a user, I want dark/light mode | **P1** | System preference detection + manual toggle |
| US-4.4 | As a user, I want keyboard shortcuts | **P2** | Cmd+K search, Cmd+N new chat, etc. |
| US-4.5 | As a user, I want to export conversations | **P2** | Export as Markdown, JSON, PDF |

---

## 5. Supported Providers & Models

### 5.1 Provider Configuration

| Provider | Auth Method | Models (Initial) | Features |
|----------|-------------|------------------|----------|
| **Anthropic** | API Key | Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Haiku | Streaming, Vision |
| **OpenAI** | API Key | GPT-4o, GPT-4 Turbo, GPT-3.5 Turbo | Streaming, Vision, Functions |
| **Google Gemini** | API Key | Gemini 1.5 Pro, Gemini 1.5 Flash, Gemini 1.0 Pro | Streaming, Vision |
| **Ollama** | Base URL (local) | Dynamic (fetch from /api/tags) | Streaming, Local |
| **CliproxyAPI** | Custom Config | User-defined | Custom headers, auth |

### 5.2 Model Metadata
Each model should store:
- `id` - Unique identifier
- `name` - Display name
- `provider` - Parent provider
- `context_window` - Max tokens
- `supports_vision` - Boolean
- `supports_streaming` - Boolean
- `pricing_input` - Cost per 1M input tokens (optional)
- `pricing_output` - Cost per 1M output tokens (optional)

---

## 6. Technical Architecture

### 6.1 Tech Stack (Laravel/Livewire)

| Layer | Technology |
|-------|------------|
| **Frontend** | Livewire Volt, Alpine.js, Tailwind CSS, FluxUI |
| **Backend** | Laravel 12, PHP 8.3+ |
| **Database** | SQLite/MySQL/PostgreSQL |
| **LLM Integration** | Laravel Prism (multi-provider) |
| **Real-time** | Laravel Reverb / SSE for streaming |
| **Auth** | Laravel Breeze/Jetstream (optional) |

### 6.2 Database Schema (Core Tables)

```
providers
├── id
├── user_id
├── name (anthropic, openai, gemini, ollama, cliproxy)
├── api_key (encrypted)
├── base_url (nullable, for ollama/cliproxy)
├── extra_config (json - headers, etc.)
├── is_active
└── timestamps

models
├── id
├── provider_id
├── model_id (e.g., "claude-3-5-sonnet-20241022")
├── display_name
├── context_window
├── supports_vision
├── supports_streaming
├── is_available
└── timestamps

conversations
├── id
├── user_id
├── folder_id (nullable)
├── title
├── is_archived
├── last_message_at
└── timestamps

messages
├── id
├── conversation_id
├── model_id
├── role (user, assistant, system)
├── content
├── attachments (json)
├── token_count
├── metadata (json - timing, etc.)
└── timestamps

folders
├── id
├── user_id
├── name
├── sort_order
└── timestamps

prompt_library
├── id
├── user_id
├── title
├── content
├── category
├── is_favorite
└── timestamps
```

### 6.3 Component Architecture

```
app/
├── Livewire/
│   ├── Chat/
│   │   ├── ChatInterface.php      # Main chat area
│   │   ├── MessageList.php        # Scrollable messages
│   │   ├── MessageInput.php       # Input with model selector
│   │   └── ModelSelector.php      # Provider/model dropdown
│   ├── Sidebar/
│   │   ├── ConversationList.php   # History sidebar
│   │   ├── SearchConversations.php
│   │   └── FolderManager.php
│   └── Settings/
│       ├── ProviderSettings.php   # API key management
│       └── ModelSettings.php
├── Services/
│   ├── LLM/
│   │   ├── LLMService.php         # Unified interface
│   │   ├── AnthropicProvider.php
│   │   ├── OpenAIProvider.php
│   │   ├── GeminiProvider.php
│   │   ├── OllamaProvider.php
│   │   └── CliproxyProvider.php
│   └── ConversationService.php
└── Models/
    ├── Provider.php
    ├── Model.php
    ├── Conversation.php
    ├── Message.php
    └── Folder.php
```

---

## 7. UI/UX Specifications

### 7.1 Layout Structure

```
┌─────────────────────────────────────────────────────────────────┐
│  Logo  [User Avatar ▼]                        [⚙️] [🌙]        │
├────────────────────┬────────────────────────────────────────────┤
│                    │                                            │
│  🔍 Search         │         [Avatar/Logo]                      │
│                    │                                            │
│  ─────────────     │      Hey! I'm FluxUI Chat                  │
│  🏠 Home           │                                            │
│  🤖 Ask AI         │      Tell me everything you need           │
│  📚 Prompt Library │                                            │
│  🧩 Extensions     │    ┌────────────────────────────────┐      │
│  📁 Folders        │    │ Ask anything...                │      │
│                    │    │                                │      │
│  ─────────────     │    │ [📎] [🔍 Deep] [💭 Think]  [Model ▼]│  │
│  RECENT            │    └────────────────────────────────┘      │
│  • Conversation 1  │                                            │
│  • Conversation 2  │    [⚡Fast] [🔍In-depth] [✨Magic] [🌐Holistic]│
│  • Conversation 3  │                                            │
│                    │                                            │
│  ARCHIVED          │                                            │
│  • Old conv...     │       ⚠️ AI can make mistakes              │
│                    │                                            │
└────────────────────┴────────────────────────────────────────────┘
```

### 7.2 Key UI Components

| Component | Description |
|-----------|-------------|
| **Sidebar** | Collapsible, 240px width, dark/light theme aware |
| **Chat Area** | Centered content (max-width 768px), scrollable |
| **Model Selector** | Dropdown in input area showing "Provider + Model" |
| **Message Bubbles** | User (right-aligned, brand color), AI (left, neutral) |
| **Quick Actions** | Pill buttons below input for preset modes |
| **Welcome Screen** | Shown on new chat, branded with logo + tagline |

### 7.3 Responsive Breakpoints

| Breakpoint | Behavior |
|------------|----------|
| Desktop (≥1024px) | Sidebar visible, full layout |
| Tablet (768-1023px) | Collapsible sidebar (hamburger) |
| Mobile (<768px) | Bottom nav, full-screen chat |

---

## 8. API Contracts

### 8.1 Internal API Endpoints

```
POST   /api/chat/send              # Send message, get streaming response
GET    /api/conversations          # List conversations
POST   /api/conversations          # Create new conversation
GET    /api/conversations/{id}     # Get conversation with messages
DELETE /api/conversations/{id}     # Delete conversation
PATCH  /api/conversations/{id}     # Update title, folder, archive status

GET    /api/providers              # List configured providers
POST   /api/providers              # Add provider config
PATCH  /api/providers/{id}         # Update provider (keys, etc.)
DELETE /api/providers/{id}         # Remove provider

GET    /api/models                 # List available models (all providers)
GET    /api/models/{provider}      # List models for specific provider
POST   /api/ollama/refresh         # Refresh Ollama model list
```

### 8.2 Streaming Response Format (SSE)

```
event: message
data: {"type": "start", "model": "claude-3-5-sonnet"}

event: message  
data: {"type": "delta", "content": "Hello"}

event: message
data: {"type": "delta", "content": " there!"}

event: message
data: {"type": "end", "usage": {"input_tokens": 10, "output_tokens": 5}}
```

---

## 9. Security Considerations

| Concern | Mitigation |
|---------|------------|
| **API Key Storage** | Encrypted at rest using Laravel's `Crypt` facade |
| **Key Exposure** | Never return full API keys in responses (masked) |
| **Rate Limiting** | Per-user rate limits on chat endpoints |
| **Input Validation** | Sanitize all user inputs, especially file uploads |
| **CORS** | Restrict to same-origin for API endpoints |
| **Auth** | Require authentication for all chat features |

---

## 10. Phased Rollout

### Phase 1: MVP (2-3 weeks)
- [ ] Basic chat interface with single provider (Anthropic)
- [ ] Conversation history (list, create, delete)
- [ ] Model selector dropdown
- [ ] Streaming responses
- [ ] Basic settings page for API key

### Phase 2: Multi-Provider (2 weeks)
- [ ] Add OpenAI, Gemini, Ollama providers
- [ ] Provider management UI
- [ ] Dynamic model fetching
- [ ] Switch models mid-conversation

### Phase 3: Enhanced UX (2 weeks)
- [ ] Search conversations
- [ ] Folders/organization
- [ ] Dark mode
- [ ] Keyboard shortcuts
- [ ] File/image attachments

### Phase 4: Power Features (2 weeks)
- [ ] Prompt library
- [ ] Quick action presets (Fast, In-depth, etc.)
- [ ] CliproxyAPI custom provider
- [ ] Export conversations
- [ ] Usage/cost tracking

---

## 11. Open Questions

| # | Question | Owner | Status |
|---|----------|-------|--------|
| 1 | Should we support multi-user/teams? | PM | Open |
| 2 | Do we need usage/cost tracking per conversation? | PM | Open |
| 3 | Should model presets (Fast, In-depth) be customizable? | Design | Open |
| 4 | What's the max file size for attachments? | Eng | Open |
| 5 | Do we need a browser extension like reference app? | PM | Open |

---

## 12. Appendix

### 12.1 Competitor Analysis

| App | Strengths | Weaknesses |
|-----|-----------|------------|
| ChatGPT | Polish, reliability | Single provider |
| Claude.ai | Great UX, artifacts | Single provider |
| Square.ai | Multi-mode, clean UI | Unknown provider support |
| OpenRouter | Many models | Complex pricing, no native UI |
| TypingMind | Multi-provider | Electron-only, dated UI |

### 12.2 Reference Links
- [Laravel Prism](https://github.com/echolabsdev/prism) - Multi-provider LLM for Laravel
- [Livewire Volt](https://livewire.laravel.com/docs/volt) - Single-file Livewire components
- [FluxUI](https://fluxui.dev) - UI components for Livewire

---

## Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Product Manager | | | |
| Tech Lead | | | |
| Design Lead | | | |

---

**Document Version:** 1.0  
**Last Updated:** January 2025  
**Status:** Draft - Pending Review
