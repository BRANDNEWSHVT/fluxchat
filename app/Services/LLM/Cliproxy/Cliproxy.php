<?php

declare(strict_types=1);

namespace App\Services\LLM\Cliproxy;

use App\Services\LLM\Cliproxy\Handlers\Stream;
use App\Services\LLM\Cliproxy\Handlers\Text;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Override;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

final class Cliproxy extends Provider
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $url,
    ) {}

    public function client(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->baseUrl($this->url)
            ->timeout(120);
    }

    #[Override]
    public function text(TextRequest $request): TextResponse
    {
        return (new Text($this))->handle($request);
    }

    #[Override]
    public function stream(TextRequest $request): Generator
    {
        return (new Stream($this))->handle($request);
    }
}
