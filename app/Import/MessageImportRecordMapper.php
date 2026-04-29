<?php

declare(strict_types=1);

namespace App\Import;

use App\Models\Area;
use DateTimeInterface;
use Golded\Ftn\ControlLine;
use Golded\Ftn\MessageControlLines;
use Golded\Ftn\MessageProvenance;
use Golded\Ftn\ParsedMessage;
use JsonException;

class MessageImportRecordMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(ParsedMessage $message, Area $area, string $importerSourceType): array
    {
        $sourceType = $this->sourceType($message, $area, $importerSourceType);
        $externalId = $this->externalId($message->externalId);
        $now = now();

        return [
            'area_id' => $area->id,
            'msgno' => $message->msgno,
            'source_type' => $sourceType,
            'source_uid' => $this->sourceUid($message, $sourceType, $externalId),
            'source_locator' => $this->nonEmptyString($message->provenance?->sourcePath),
            'source_offset' => $message->provenance?->sourceOffset,
            'external_id' => $externalId,
            'subject' => $this->utf8($message->subject),
            'from_name' => $this->utf8($message->fromName),
            'from_address' => $this->nullableUtf8($message->fromAddress),
            'to_name' => $this->utf8($message->toName),
            'to_address' => $this->nullableUtf8($message->toAddress),
            'body_text' => $this->utf8($message->bodyText),
            'reply_to_msgno' => $message->replyToMsgno,
            'reply_to_external_id' => $this->nonEmptyString($message->controlLines?->reply),
            'reply1st_msgno' => $message->reply1stMsgno,
            'replynext_msgno' => $message->replyNextMsgno,
            'attributes_raw' => $message->attributesRaw,
            'posted_at' => $message->postedAt,
            'control_lines_json' => $this->json($this->controlLines($message->controlLines)),
            'provenance_json' => $this->json($this->provenance($message->provenance)),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function sourceType(ParsedMessage $message, Area $area, string $importerSourceType): string
    {
        return $this->nonEmptyString($message->provenance?->sourceType)
            ?? $this->nonEmptyString($area->source_type)
            ?? $importerSourceType;
    }

    private function externalId(?string $externalId): ?string
    {
        return $this->nonEmptyString($externalId);
    }

    private function sourceUid(ParsedMessage $message, string $sourceType, ?string $externalId): string
    {
        $sourcePath = $this->nonEmptyString($message->provenance?->sourcePath);
        $sourceId = $this->nonEmptyString($message->provenance?->sourceId);
        $sourceOffset = $message->provenance?->sourceOffset;

        if ($sourceType === 'msg' && $sourcePath !== null) {
            return 'msg:file:'.basename(str_replace('\\', '/', $sourcePath));
        }

        if (in_array($sourceType, ['jam', 'squish'], true) && $sourceOffset !== null) {
            return "{$sourceType}:offset:{$sourceOffset}";
        }

        if ($sourceType === 'hudson' && $sourceOffset !== null && $sourceId !== null) {
            return "hudson:offset:{$sourceOffset}:source-id:{$sourceId}";
        }

        if ($sourceId !== null) {
            return "{$sourceType}:source-id:{$sourceId}";
        }

        if ($externalId !== null) {
            return 'external:'.sha1($externalId);
        }

        return 'content:'.$this->contentHash($message, $sourceType);
    }

    private function contentHash(ParsedMessage $message, string $sourceType): string
    {
        return md5(implode("\0", [
            $sourceType,
            $this->utf8($message->fromName),
            $this->utf8($message->toName),
            $this->utf8($message->subject),
            $this->dateValue($message->postedAt),
            $this->utf8($message->bodyText),
        ]));
    }

    private function dateValue(?DateTimeInterface $date): string
    {
        return $date?->format(DATE_ATOM) ?? '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function controlLines(?MessageControlLines $controlLines): ?array
    {
        if (! $controlLines instanceof MessageControlLines) {
            return null;
        }

        return [
            'kludges' => array_map(
                fn (ControlLine $line): array => [
                    'name' => $line->name,
                    'value' => $this->utf8($line->value),
                    'raw' => $this->utf8($line->raw),
                ],
                $controlLines->kludges,
            ),
            'msgid' => $this->nullableUtf8($controlLines->msgid),
            'reply' => $this->nullableUtf8($controlLines->reply),
            'charset' => $this->nullableUtf8($controlLines->charset),
            'seen_by' => array_map($this->utf8(...), $controlLines->seenBy),
            'path' => array_map($this->utf8(...), $controlLines->path),
            'tearline' => $this->nullableUtf8($controlLines->tearline),
            'origin' => $this->nullableUtf8($controlLines->origin),
            'origin_address' => $controlLines->originAddress?->toString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function provenance(?MessageProvenance $provenance): ?array
    {
        if (! $provenance instanceof MessageProvenance) {
            return null;
        }

        return [
            'source_type' => $this->utf8($provenance->sourceType),
            'source_path' => $this->nullableUtf8($provenance->sourcePath),
            'source_id' => $this->nullableUtf8($provenance->sourceId),
            'source_offset' => $provenance->sourceOffset,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function json(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $json = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        if (! is_string($json)) {
            throw new JsonException('Could not encode FTN import metadata.');
        }

        return $json;
    }

    private function nullableUtf8(?string $value): ?string
    {
        return $value === null ? null : $this->utf8($value);
    }

    private function utf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'CP850');

        return is_string($converted) ? $converted : '';
    }

    private function nonEmptyString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($this->utf8($value));

        return $value === '' ? null : $value;
    }
}
