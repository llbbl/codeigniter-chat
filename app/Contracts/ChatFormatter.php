<?php

namespace App\Contracts;

interface ChatFormatter
{
    /** @param list<array<string, mixed>> $messages */
    public function asXml(array $messages, ?array $pagination = null): string;

    /** @param list<array<string, mixed>> $messages @return array<string, mixed> */
    public function asJson(array $messages, ?array $pagination = null): array;
}
