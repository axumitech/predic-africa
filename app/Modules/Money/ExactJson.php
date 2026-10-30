<?php

namespace App\Modules\Money;

final class ExactJson
{
    /** Preserve decimal JSON tokens as strings before json_decode can turn them into binary floats. */
    public static function decode(string $json): array
    {
        if (strlen($json) > 1048576) {
            throw new \UnexpectedValueException('Réponse trop volumineuse.');
        }
        $tokens = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/',
            fn ($match) => strpbrk($match[0], '.eE') === false ? $match[0] : '"'.$match[0].'"', $json);
        $decoded = json_decode($tokens, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (! is_array($decoded)) {
            throw new \UnexpectedValueException('Objet JSON attendu.');
        }

        return $decoded;
    }
}
