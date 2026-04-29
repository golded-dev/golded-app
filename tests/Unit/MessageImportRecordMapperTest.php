<?php

use App\Import\MessageImportRecordMapper;
use App\Models\Area;
use Golded\Ftn\ControlLine;
use Golded\Ftn\MessageControlLines;
use Golded\Ftn\MessageProvenance;
use Golded\Ftn\ParsedMessage;

it('uses the MSG source filename as source_uid', function (): void {
    $record = mapperRecord(
        provenance: new MessageProvenance(
            sourceType: 'msg',
            sourcePath: '/tmp/DEMO/1.msg',
            sourceId: '1',
        ),
        importerSourceType: 'msg',
    );

    expect($record['source_type'])->toBe('msg')
        ->and($record['source_uid'])->toBe('msg:file:1.msg')
        ->and($record['source_locator'])->toBe('/tmp/DEMO/1.msg');
});

it('prefers provenance source type over area and importer source types', function (): void {
    $record = mapperRecord(
        areaSourceType: 'jam',
        provenance: new MessageProvenance(
            sourceType: 'squish',
            sourceOffset: 256,
        ),
        importerSourceType: 'msg',
    );

    expect($record['source_type'])->toBe('squish')
        ->and($record['source_uid'])->toBe('squish:offset:256');
});

it('uses area source type when provenance does not provide one', function (): void {
    $record = mapperRecord(
        areaSourceType: 'jam',
        provenance: null,
        importerSourceType: 'msg',
    );

    expect($record['source_type'])->toBe('jam');
});

it('uses source offsets for JAM and Squish messages', function (string $sourceType): void {
    $record = mapperRecord(
        provenance: new MessageProvenance(
            sourceType: $sourceType,
            sourcePath: "/tmp/{$sourceType}/test",
            sourceId: '7',
            sourceOffset: 256,
        ),
        importerSourceType: $sourceType,
    );

    expect($record['source_uid'])->toBe("{$sourceType}:offset:256")
        ->and($record['source_offset'])->toBe(256);
})->with(['jam', 'squish']);

it('uses external id hash before content hash when provenance is missing', function (): void {
    $record = mapperRecord(
        externalId: '2:230/150 abc',
        provenance: null,
        importerSourceType: 'jam',
    );

    expect($record['source_uid'])->toBe('external:'.sha1('2:230/150 abc'));
});

it('uses Hudson offset plus source id as source_uid', function (): void {
    $record = mapperRecord(
        provenance: new MessageProvenance(
            sourceType: 'hudson',
            sourcePath: '/tmp/HUDSON/MSGTXT.BBS',
            sourceId: '42',
            sourceOffset: 0,
        ),
        importerSourceType: 'hudson',
    );

    expect($record['source_uid'])->toBe('hudson:offset:0:source-id:42');
});

it('falls back without using raw msgno as identity', function (): void {
    $record = mapperRecord(
        msgno: 999,
        externalId: '   ',
        provenance: null,
        importerSourceType: 'jam',
    );

    expect($record['external_id'])->toBeNull()
        ->and($record['source_uid'])->toStartWith('content:')
        ->and($record['source_uid'])->not->toContain('999');
});

it('stores control lines and provenance as JSON strings', function (): void {
    $record = mapperRecord(
        controlLines: new MessageControlLines(
            kludges: [new ControlLine('MSGID', '2:230/150 abc', "\x01MSGID: 2:230/150 abc")],
            msgid: '2:230/150 abc',
            reply: '2:230/150 parent',
        ),
        provenance: new MessageProvenance(
            sourceType: 'jam',
            sourcePath: '/tmp/JAM/test.jhr',
            sourceId: '7',
            sourceOffset: 256,
        ),
        importerSourceType: 'jam',
    );

    expect(json_decode((string) $record['control_lines_json'], true))->toMatchArray([
        'msgid' => '2:230/150 abc',
        'reply' => '2:230/150 parent',
    ])->and(json_decode((string) $record['provenance_json'], true))->toMatchArray([
        'source_type' => 'jam',
        'source_offset' => 256,
    ])->and($record['reply_to_external_id'])->toBe('2:230/150 parent');
});

it('normalizes raw FTN control metadata before JSON encoding', function (): void {
    $record = mapperRecord(
        controlLines: new MessageControlLines(
            kludges: [new ControlLine('NOTE', "CP850 \x82", "\x01NOTE: CP850 \x82")],
            tearline: "--- GoldED \x82",
        ),
    );

    $decoded = json_decode((string) $record['control_lines_json'], true, flags: JSON_THROW_ON_ERROR);
    $accent = mb_convert_encoding("\x82", 'UTF-8', 'CP850');

    expect($decoded['kludges'][0]['value'])->toBe('CP850 '.$accent)
        ->and($decoded['kludges'][0]['raw'])->toBe("\x01NOTE: CP850 ".$accent)
        ->and($decoded['tearline'])->toBe('--- GoldED '.$accent);
});

function mapperRecord(
    int $msgno = 1,
    ?string $externalId = '2:230/150 abc',
    string $areaSourceType = 'jam',
    ?MessageControlLines $controlLines = null,
    ?MessageProvenance $provenance = null,
    string $importerSourceType = 'jam',
): array {
    $area = new Area(['source_type' => $areaSourceType]);
    $area->id = 123;

    return (new MessageImportRecordMapper)->map(
        new ParsedMessage(
            msgno: $msgno,
            fromName: 'Alice',
            toName: 'Bob',
            subject: 'Hello',
            bodyText: 'Body',
            attributesRaw: 0,
            externalId: $externalId,
            controlLines: $controlLines,
            provenance: $provenance,
        ),
        $area,
        $importerSourceType,
    );
}
