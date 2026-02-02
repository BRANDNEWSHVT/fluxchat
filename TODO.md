# FluxUI Chat - Implementation TODO

> Auto-generated from PRD.md | Track progress by checking off completed items

---

## Phase 1: MVP (2-3 weeks)

### 1.1 Database & Models

- [x] **Create Provider model and migration**
  - Fields: id, user_id, name, api_key (encrypted), base_url, extra_config (json), is_active, timestamps
  - Create factory and seeder
  - Add encryption cast for api_key

- [x] **Create AiModel model and migration**
  - Fields: id, provider_id, model_id, display_name, context_window, supports_vision, supports_streaming, is_available, timestamps
  - Create factory with states for each provider
  - Seed with default models for Anthropic

- [x] **Create Conversation model and migration**
  - Fields: id, user_id, folder_id (nullable), title, is_archived, last_message_at, timestamps
  - Create factory
  - Add soft deletes

- [x] **Create Message model and migration**
  - Fields: id, conversation_id, model_id, role (enum: user/assistant/system), content (text), attachments (json), token_count, metadata (json), timestamps
  - Create factory with states for user/assistant messages

- [x] **Create Folder model and migration**
  - Fields: id, user_id, name, sort_order, timestamps
  - Create factory

- [x] **Set up model relationships**
  - Provider hasMany AiModel
  - Provider belongsTo User
  - Conversation hasMany Message
  - Conversation belongsTo User
  - Conversation belongsTo Folder (nullable)
  - Message belongsTo Conversation
  - Message belongsTo AiModel
  - Folder hasMany Conversation
  - Folder belongsTo User

### 1.2 LLM Service Layer

- [x] **Create LLMService interface/contract**
  - Define methods: sendMessage(), streamMessage(), listModels()
  - Return types for responses

- [x] **Implement AnthropicProvider service**
  - Use Laravel Prism package
  - Support streaming responses
  - Handle API errors gracefully

- [x] **Create ConversationService**
  - Create conversation
  - Add message to conversation
  - Get conversation with messages
  - Update conversation title
  - Delete conversation (soft delete)

### 1.3 Livewire Components (Volt)

- [x] **Create main chat layout page**
  - File: `resources/views/pages/chat/index.blade.php`
  - Two-column layout (sidebar + chat area)
  - Responsive design

- [x] **Create ChatInterface Volt component**
  - Display messages list
  - Welcome screen when no messages
  - Auto-scroll to bottom on new messages

- [x] **Create MessageInput Volt component**
  - Textarea with auto-resize
  - Submit on Enter (Shift+Enter for newline)
  - Model selector dropdown
  - Loading state during response

- [x] **Create ModelSelector Volt component**
  - Dropdown showing provider + model name
  - Group models by provider
  - Show model capabilities (vision, etc.)

- [x] **Create MessageBubble component**
  - User messages (right-aligned, brand color)
  - Assistant messages (left-aligned, neutral)
  - Markdown rendering
  - Code block with copy button
  - Timestamp display

- [x] **Create ConversationList Volt component**
  - List conversations grouped by date
  - Active conversation highlighting
  - New conversation button
  - Delete conversation action

### 1.4 Streaming Responses

- [x] **Implement SSE endpoint for streaming**
  - Route: POST /chat/stream
  - Use Laravel Prism streaming
  - Send delta events

- [x] **Create frontend streaming handler**
  - EventSource or fetch with ReadableStream
  - Append tokens to message content
  - Handle connection errors
  - Show typing indicator
  - Added: Stop generation button, retry on error, token usage display

### 1.5 Settings Page

- [x] **Create settings page**
  - File: `resources/views/pages/settings/providers.blade.php`
  - Provider management interface

- [x] **Create ProviderSettings Volt component**
  - Add/edit/delete API keys
  - Mask API keys in display (show last 4 chars)
  - Test connection button
  - Set default provider/model
  - Auto-seed default models for each provider

### 1.6 Tests (Phase 1)

- [x] **Unit tests for models**
  - Provider encryption/decryption
  - Conversation soft delete
  - Message role validation

- [x] **Feature tests for chat**
  - Create conversation
  - Send message
  - List conversations
  - Delete conversation

- [x] **Feature tests for providers**
  - Add provider with API key
  - Update provider
  - Delete provider

---

## Phase 2: Multi-Provider (2 weeks)

### 2.1 Additional Providers

- [x] **Implement OpenAIProvider service**
  - Use Laravel Prism
  - Support GPT-4o, GPT-4 Turbo, GPT-3.5 Turbo
  - Handle function calling (optional)

- [x] **Implement GeminiProvider service**
  - Use Laravel Prism
  - Support Gemini 1.5 Pro, Flash, 1.0 Pro

- [x] **Implement OllamaProvider service**
  - Custom HTTP client for local Ollama
  - Dynamic model fetching from /api/tags
  - Base URL configuration

- [x] **Create provider factory pattern**
  - ProviderFactory to instantiate correct provider
  - Register providers in service container

### 2.2 Dynamic Model Management

- [x] **Create model sync command**
  - `php artisan models:sync`
  - Fetch models from each provider API
  - Update is_available status

- [x] **Update ModelSelector for multi-provider**
  - Group by provider
  - Show provider icon/logo
  - Filter by capability (vision, streaming)

### 2.3 Mid-Conversation Model Switching

- [x] **Update Message model**
  - Track which model generated each response
  - Store model_id on each message

- [x] **Update ChatInterface**
  - Allow model change mid-conversation
  - Show model badge on each message
  - Handle context window limits

### 2.4 Provider Management UI

- [x] **Enhance ProviderSettings component**
  - Add all 4 providers (OpenAI, Gemini, Ollama, CliproxyAPI)
  - Provider-specific configuration fields
  - Enable/disable providers

- [x] **Add Ollama configuration**
  - Base URL input
  - Refresh models button
  - Connection test

### 2.5 Tests (Phase 2)

- [x] **Unit tests for each provider**
  - Mock API responses
  - Test error handling

- [x] **Feature tests for model switching**
  - Switch model mid-conversation
  - Verify model recorded on message
  - Custom base URL
  - Custom headers (auth, etc.)
  - OpenAI-compatible API format

- [x] **Create CliproxyAPI configuration UI**
  - Base URL input
  - Headers key-value editor
  - Authentication type selector
  - Test connection

- [x] **Add custom model definition**
  - Manually add models for CliproxyAPI
  - Set capabilities manually

### 2.6 Tests (Phase 2)

- [x] **Unit tests for each provider**
  - Mock API responses
  - Test error handling

- [x] **Feature tests for model switching**
  - Switch model mid-conversation
  - Verify model recorded on message

---

## Phase 3: Enhanced UX (2 weeks)

### 3.1 Search

- [ ] **Add full-text search to conversations**
  - Search by message content
  - Search by conversation title
  - Highlight matching text

- [ ] **Create SearchConversations Volt component**
  - Search input in sidebar
  - Real-time results (debounced)
  - Navigate to conversation on click

### 3.2 Folders/Organization

- [ ] **Create FolderManager Volt component**
  - Create new folder
  - Rename folder
  - Delete folder (move conversations to uncategorized)
  - Drag-drop conversations to folders

- [ ] **Update ConversationList for folders**
  - Collapsible folder sections
  - Folder icons
  - "Uncategorized" section

### 3.3 Dark Mode

- [ ] **Implement theme toggle**
  - Store preference in localStorage
  - Detect system preference
  - Toggle button in header

- [ ] **Update Tailwind config for dark mode**
  - Use class-based dark mode
  - Ensure all components support dark

- [ ] **Create theme-aware color palette**
  - Primary, secondary, accent colors
  - Message bubble colors
  - Sidebar colors

### 3.4 Keyboard Shortcuts

- [ ] **Implement keyboard shortcut system**
  - Cmd/Ctrl + K: Search
  - Cmd/Ctrl + N: New conversation
  - Cmd/Ctrl + /: Focus input
  - Escape: Close modals

- [ ] **Create keyboard shortcuts modal**
  - Show all available shortcuts
  - Triggered by Cmd + ?

### 3.5 File/Image Attachments

- [ ] **Create file upload component**
  - Drag-drop zone
  - File type validation (images for vision models)
  - Size limit (configurable)
  - Preview before send

- [ ] **Update MessageInput for attachments**
  - Attachment button
  - Show attachment previews
  - Remove attachment option

- [ ] **Store attachments**
  - Use Laravel Storage
  - Store file metadata in message attachments JSON
  - Cleanup orphaned files

- [ ] **Send attachments to vision models**
  - Base64 encode images
  - Only for vision-capable models
  - Show error if model doesn't support vision

### 3.6 Tests (Phase 3)

- [ ] **Feature tests for search**
  - Search by content
  - Search by title
  - Empty results

- [ ] **Feature tests for folders**
  - Create folder
  - Move conversation
  - Delete folder

- [ ] **Feature tests for attachments**
  - Upload file
  - Send with message
  - Invalid file type

---

## Phase 4: Power Features (2 weeks)

### 4.1 Prompt Library

- [ ] **Create PromptLibrary model and migration**
  - Fields: id, user_id, title, content, category, is_favorite, timestamps
  - Create factory

- [ ] **Create PromptLibrary Volt component**
  - List prompts by category
  - Search prompts
  - Add/edit/delete prompts
  - Favorite toggle

- [ ] **Integrate prompts into chat**
  - Quick-insert button in MessageInput
  - Modal to select prompt
  - Insert prompt into textarea

### 4.2 Quick Action Presets

- [ ] **Create preset system**
  - Define presets: Fast, In-depth, Magic, Holistic
  - Each preset has: system prompt, temperature, model preference

- [ ] **Create QuickActions Volt component**
  - Pill buttons below input
  - Active state indication
  - Apply preset on click

- [ ] **Allow custom presets (optional)**
  - User-defined presets
  - Save preset from current settings

### 4.3 Export Conversations

- [ ] **Create export service**
  - Export as Markdown
  - Export as JSON
  - Export as PDF (using dompdf or similar)

- [ ] **Add export button to conversation**
  - Dropdown with format options
  - Download file

### 4.4 Usage/Cost Tracking

- [ ] **Track token usage per message**
  - Store input_tokens, output_tokens
  - Calculate from API response

- [ ] **Create usage dashboard**
  - Total tokens by provider
  - Cost estimation (based on pricing)
  - Usage over time chart

- [ ] **Add usage display to conversation**
  - Show tokens per message
  - Total conversation tokens

### 4.5 Tests (Phase 4)

- [ ] **Feature tests for prompt library**
  - CRUD operations
  - Insert into chat

- [ ] **Feature tests for export**
  - Export as Markdown
  - Export as JSON

- [ ] **Feature tests for usage tracking**
  - Token counting
  - Cost calculation

---

## Technical Debt & Polish

### Code Quality

- [ ] **Run Laravel Pint on all files**
- [ ] **Add PHPDoc blocks to all services**
- [ ] **Create API documentation (optional)**
- [ ] **Add request validation to all endpoints**

### Performance

- [ ] **Add database indexes**
  - conversations.user_id
  - conversations.last_message_at
  - messages.conversation_id
  - messages.created_at

- [ ] **Implement pagination**
  - Conversation list
  - Message history (infinite scroll)

- [ ] **Add caching**
  - Model list per provider
  - User preferences

### Security

- [ ] **Implement rate limiting**
  - Per-user limits on chat endpoints
  - Configurable in .env

- [ ] **Add input sanitization**
  - XSS prevention on message display
  - File upload validation

- [ ] **Security audit**
  - API key exposure check
  - CORS configuration review

### Accessibility

- [ ] **Keyboard navigation**
  - Tab through UI elements
  - Enter to activate buttons

- [ ] **Screen reader support**
  - ARIA labels
  - Role attributes

- [ ] **Focus management**
  - Focus trap in modals
  - Return focus after close

---

## Legend

| Symbol | Meaning |
|--------|---------|
| `[ ]` | Not started |
| `[x]` | Completed |
| `[~]` | In progress |
| `[-]` | Blocked/Skipped |

---

**Last Updated:** February 2025
