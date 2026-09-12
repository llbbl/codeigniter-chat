<?php

namespace App\Services;

use App\Contracts\ChatFormatter as ChatFormatterContract;
use App\Helpers\ChatHelper;

final class ChatFormatter implements ChatFormatterContract
{
    public function asXml(array $messages, ?array $pagination = null): string
    {
        return ChatHelper::formatAsXml($messages, $pagination);
    }

    public function asJson(array $messages, ?array $pagination = null): array
    {
        return ChatHelper::formatAsJson($messages, $pagination);
    }
}
