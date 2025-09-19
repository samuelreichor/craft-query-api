<?php

namespace samuelreichoer\queryapi\helpers;

use InvalidArgumentException;

class Typescript
{
    public static function buildTsType(array $fields, string $name = '', string $kind = 'type', int $indent = 0, bool $inline = false): string
    {
        if (!in_array($kind, ['interface', 'type'])) {
            throw new InvalidArgumentException("Kind must be 'interface' or 'type'");
        }

        $lines = [];

        $baseIndent = str_repeat('  ', $indent);
        $innerIndent = str_repeat('  ', $indent + 2);

        if (!$inline) {
            $prefix = $kind === 'interface' ? "export interface {$name} " : "export type {$name} = ";
            $lines[] = $prefix . '{';
        } else {
            $lines[] = '{';
        }

        foreach ($fields as $key => $value) {
            $quotedKey = self::quoteKeyIfNecessary($key);

            if (is_array($value)) {
                $nested = self::buildTsType($value, '', $kind, $indent + 2, true);
                $lines[] = $innerIndent . "{$quotedKey}: {$nested}";
            } else {
                $lines[] = $innerIndent . "{$quotedKey}: {$value}";
            }
        }

        $lines[] = $baseIndent . '}';

        return implode("\n", $lines);
    }

    public static function quoteKeyIfNecessary(string $key): string
    {
        // If it matches a valid identifier (e.g. title, imageUrl), leave it unquoted
        if (preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $key)) {
            return $key;
        }

        // If it contains special characters, numbers first, spaces, etc., wrap it
        return "'" . $key . "'";
    }

    public static function modifyTypeByField($field, string $rawType): string
    {
        // rawType is, for example, CraftDateTime
        $type = $rawType;

        // type should be (CraftDateTime)[]
        $isSingleRelation = Utils::isArrayField($field);
        if ($isSingleRelation) {
            $type = '(' . $type . ')[]';
        }

        // type should be CraftDateTime | null
        $isRequiredField = Utils::isRequiredField($field);
        if (!$isRequiredField) {
            $type .= ' | null';
        }

        return $type;
    }
}
